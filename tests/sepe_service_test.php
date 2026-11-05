<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the SOAP follow-up service (Annex V.A): every operation and return code.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport;

use local_customreport\local\action_manager;
use local_customreport\local\cmrole_manager;
use local_customreport\local\profile_manager;
use local_customreport\local\progress_calculator;
use local_customreport\local\structure_manager;
use local_customreport\sepe\identity;
use local_customreport\sepe\lifecycle;
use local_customreport\sepe\result_calculator;
use local_customreport\sepe\security;
use local_customreport\sepe\service;
use local_customreport\sepe\store;
use local_customreport\sepe\wsdl;

/**
 * Tests for the web service.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(security::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(store::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(wsdl::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\sepe\builder::class)]
final class sepe_service_test extends \advanced_testcase {
    /**
     * Prepare credentials.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        security::save_credential('SEPE', 'sepe', 's3cret-pass');
    }

    /**
     * Time inside the development phase of the fixture action (01/12/2026 - 31/01/2027).
     *
     * @return int
     */
    private function during(): int {
        return store::parse_date('15/12/2026') + 3600;
    }

    /**
     * Send a fixture.
     *
     * @param string $name
     * @param int|null $now
     * @return array
     */
    private function call(string $name, ?int $now = null): array {
        $xml = file_get_contents(__DIR__ . '/fixtures/ws/' . $name . '.xml');
        return service::handle($xml, '127.0.0.1', $now ?? $this->during());
    }

    /**
     * Return code and label of a response.
     *
     * @param array $result
     * @return array [code, label]
     */
    private function code(array $result): array {
        $doc = new \DOMDocument();
        $doc->loadXML($result['body']);
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('s', wsdl::NS_SALIDA);
        $code = $xp->evaluate('string(//s:*/CODIGO_RETORNO)');
        $label = $xp->query('//s:*/ETIQUETA_ERROR')->item(0);
        $nil = $label && $label->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'nil') === 'true';
        return [(int) $code, $nil ? null : ($label ? rtrim($label->textContent) : null)];
    }

    /**
     * Assert that the response element conforms to the official schema.
     *
     * @param array $result
     */
    private function assert_schema_valid(array $result): void {
        $doc = new \DOMDocument();
        $doc->loadXML($result['body']);
        $root = $doc->getElementsByTagNameNS(wsdl::NS_SALIDA, '*')->item(0);
        $this->assertNull(wsdl::validate($root, 'salida'), 'Response does not conform to salida.xsd');
    }

    /**
     * Create the centre and the action.
     */
    private function create_center_and_action(): void {
        $this->assertSame(0, $this->code($this->call('crearCentro'))[0]);
        $this->assertSame(0, $this->code($this->call('crearAccion'))[0]);
    }

    /**
     * Authentication: no token, wrong password and digest passwords are refused with a SOAP fault.
     */
    public function test_authentication(): void {
        foreach (['obtenerListaAcciones_noheader', 'obtenerListaAcciones_badpass', 'obtenerListaAcciones_digest'] as $name) {
            $result = $this->call($name);
            $this->assertSame(500, $result['http'], $name);
            $this->assertStringContainsString('FailedAuthentication', $result['body']);
        }
        $ok = $this->call('obtenerListaAcciones');
        $this->assertSame(200, $ok['http']);
        security::save_credential('SEPE', 'sepe', 'another-password-1');
        $this->assertSame(500, $this->call('obtenerListaAcciones')['http']);
        $this->assertGreaterThan(0, $GLOBALS['DB']->get_field('local_customreport_wscred', 'lastused', ['username' => 'sepe']));
    }

    /**
     * Malformed requests, DTDs and unknown operations are faults and nothing is executed.
     */
    public function test_bad_requests(): void {
        $this->assertSame(500, service::handle('not xml')['http']);
        $this->assertSame(500, service::handle('<?xml version="1.0"?><!DOCTYPE a [<!ENTITY x "y">]><a>&x;</a>')['http']);
        $env = '<s:Envelope xmlns:s="' . wsdl::NS_SOAP . '"><s:Body><x:foo xmlns:x="urn:x"/></s:Body></s:Envelope>';
        $this->assertStringContainsString('Unknown operation', service::handle($env)['body']);
    }

    /**
     * crearCentro and obtenerDatosCentro.
     */
    public function test_center_operations(): void {
        $result = $this->call('obtenerDatosCentro');
        $this->assertSame([-1, 'CENTRO_NO_CREADO'], $this->code($result));

        $result = $this->call('crearCentro');
        $this->assertSame([0, null], $this->code($result));
        $this->assert_schema_valid($result);

        $result = $this->call('obtenerDatosCentro');
        $this->assertSame([0, null], $this->code($result));
        $this->assert_schema_valid($result);
        $this->assertStringContainsString('Centro de Teleformacion', $result['body']);
        $this->assertStringContainsString('8000000000000001', $result['body']);
    }

    /**
     * crearAccion: 0 created, 1 existing, 2 parameter error with the offending tag, -1 without centre.
     */
    public function test_create_action_codes(): void {
        $this->assertSame([-1, 'CENTRO_NO_CREADO'], $this->code($this->call('crearAccion')));
        $this->call('crearCentro');

        $result = $this->call('crearAccion');
        $this->assertSame([0, null], $this->code($result));
        $this->assert_schema_valid($result);
        $this->assertStringContainsString('<CODIGO_ACCION>2026280010', $result['body']);

        $this->assertSame(1, $this->code($this->call('crearAccion'))[0]);

        $bad = $this->call('crearAccion_bad_date');
        $this->assertSame([2, 'FECHA_INICIO'], $this->code($bad));
    }

    /**
     * Semantic validation: end before start and invalid situation.
     */
    public function test_semantic_errors(): void {
        $this->call('crearCentro');
        $xml = file_get_contents(__DIR__ . '/fixtures/ws/crearAccion.xml');
        $result = service::handle(str_replace('<FECHA_FIN>31/01/2027', '<FECHA_FIN>30/11/2026', $xml), '', $this->during());
        $this->assertSame([2, 'FECHA_FIN'], $this->code($result));
        $result = service::handle(str_replace('<SITUACION>20', '<SITUACION>99', $xml), '', $this->during());
        $this->assertSame([2, 'SITUACION'], $this->code($result));
    }

    /**
     * A centre that already has actions cannot be created again (code 1).
     */
    public function test_create_center_with_actions(): void {
        $this->create_center_and_action();
        $this->assertSame(1, $this->code($this->call('crearCentro'))[0]);
    }

    /**
     * obtenerListaAcciones lists only started actions.
     */
    public function test_list_actions(): void {
        $this->create_center_and_action();
        $before = $this->call('obtenerListaAcciones', store::parse_date('01/11/2026'));
        $this->assertSame(0, $this->code($before)[0]);
        $this->assertStringNotContainsString('2026280010', $before['body']);

        $during = $this->call('obtenerListaAcciones');
        $this->assertStringContainsString('<CODIGO_ACCION>2026280010' . str_repeat(' ', 20) . '</CODIGO_ACCION>', $during['body']);
        $this->assertStringContainsString('<ORIGEN_ACCION>20</ORIGEN_ACCION>', $during['body']);
        $this->assert_schema_valid($during);
        $this->assert_schema_valid($before);
    }

    /**
     * obtenerAccion returns the action (0) or code 1 when it does not exist.
     */
    public function test_get_action(): void {
        $this->assertSame(1, $this->code($this->call('obtenerAccion'))[0]);
        $this->create_center_and_action();
        $result = $this->call('obtenerAccion');
        $this->assertSame([0, null], $this->code($result));
        $this->assert_schema_valid($result);
        $this->assertStringContainsString('Programacion de aplicaciones', $result['body']);
    }

    /**
     * eliminarAccion follows the lifecycle: allowed before the start or 30 days after the end.
     */
    public function test_delete_action_lifecycle(): void {
        $this->assertSame(1, $this->code($this->call('eliminarAccion'))[0]);
        $this->create_center_and_action();

        $during = $this->call('eliminarAccion');
        $this->assertSame([-1, 'ACCION_EN_SEGUIMIENTO'], $this->code($during));
        $this->assert_schema_valid($during);
        $this->assertSame(1, $GLOBALS['DB']->count_records('local_customreport_wsaction'));

        $late = store::parse_date('31/01/2027') + 29 * DAYSECS;
        $this->assertSame(-1, $this->code($this->call('eliminarAccion', $late))[0]);
        $after = store::parse_date('31/01/2027') + 31 * DAYSECS;
        $this->assertSame([0, null], $this->code($this->call('eliminarAccion', $after)));
        $this->assertSame(0, $GLOBALS['DB']->count_records('local_customreport_wsaction'));

        // Before the start a test action may be deleted.
        $this->call('crearAccion');
        $this->assertSame(0, $this->code($this->call('eliminarAccion', store::parse_date('01/11/2026')))[0]);
    }

    /**
     * Disabled service answers -2 for known operations.
     */
    public function test_service_unavailable(): void {
        set_config('ws_enabled', 0, 'local_customreport');
        $result = $this->call('obtenerListaAcciones');
        $this->assertSame(200, $result['http']);
        $this->assertSame(-2, $this->code($result)[0]);
        $this->assert_schema_valid($result);
    }

    /**
     * The log keeps request and response, never the password, and is purged by retention.
     */
    public function test_log_and_retention(): void {
        global $DB;
        $this->call('obtenerListaAcciones');
        $log = $DB->get_record('local_customreport_wslog', ['operation' => 'obtenerListaAcciones']);
        $this->assertNotEmpty($log);
        $this->assertStringNotContainsString('s3cret-pass', $log->request);
        $this->assertStringContainsString('***', $log->request);
        $this->assertSame('sepe', $log->username);
        $this->assertSame('127.0.0.1', $log->remoteaddr);

        set_config('logretentiondays', 10, 'local_customreport');
        $this->assertSame(0, store::purge_log($this->during()));
        $this->assertSame(1, store::purge_log($this->during() + 11 * DAYSECS));
        $this->assertSame(0, $DB->count_records('local_customreport_wslog'));
    }

    /**
     * The WSDL is delivered with the endpoint and validation names the offending tag.
     */
    public function test_wsdl(): void {
        $document = wsdl::document('https://example.com/ws?a=1&b=2');
        $this->assertStringContainsString('https://example.com/ws?a=1&amp;b=2', $document);
        $this->assertStringNotContainsString('@@ENDPOINTURL@@', $document);
        $this->assertNotFalse(simplexml_load_string($document));
    }

    /**
     * With a linked Moodle action the response carries tutors, use and participants once the action has started.
     */
    public function test_linked_action_tracking_data(): void {
        global $DB, $CFG;
        $CFG->enablecompletion = 1;
        profile_manager::install_builtin();
        $g = $this->getDataGenerator();
        $course = $g->create_course(['numsections' => 1, 'enablecompletion' => 1]);
        $ana = $g->create_and_enrol($course, 'student');
        $tutor = $g->create_and_enrol($course, 'editingteacher');
        $assign = $g->create_module('assign', ['course' => $course->id, 'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->call('crearCentro');
        // The local action exists before the administration creates it: it is linked by the action code.
        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0, 'actioncode' => '2026280010',
            'profile' => 'sepe_sce_tms369', 'name' => 'Linked']);
        $sections = array_keys(structure_manager::course_section_menu($course->id));
        structure_manager::add($actionid, 'MF', 'MF1', 'Module', 100, 0, [$sections[1]]);
        cmrole_manager::set_role($course->id, $assign->cmid, 'process');
        $this->assertSame(0, $this->code($this->call('crearAccion'))[0]);
        $this->assertSame($actionid, (int) $DB->get_field('local_customreport_wsaction', 'actionid', []));

        $completion = new \completion_info(get_course($course->id));
        $completion->update_state(get_fast_modinfo($course)->get_cm($assign->cmid), COMPLETION_COMPLETE, $ana->id);
        $day = store::parse_date('10/12/2026');
        $DB->insert_record('local_customreport_slot', (object) ['userid' => $ana->id, 'courseid' => $course->id, 'day' => $day,
            'slot' => 2, 'accesses' => 7, 'seconds' => 5400]);
        $DB->insert_record('local_customreport_slot', (object) ['userid' => $ana->id, 'courseid' => $course->id, 'day' => $day,
            'slot' => 1, 'accesses' => 1, 'seconds' => 1800]);
        $DB->insert_record('local_customreport_idoc', (object) ['userid' => $ana->id, 'doctype' => 'D', 'docnumber' => '  12345678',
            'docletter' => 'Z', 'timemodified' => time()]);
        $DB->insert_record('local_customreport_idoc', (object) ['userid' => $tutor->id, 'doctype' => 'E',
            'docnumber' => '  X1234567', 'docletter' => 'L', 'timemodified' => time()]);
        progress_calculator::update_course($course->id);

        $result = $this->call('obtenerAccion');
        $this->assertSame(0, $this->code($result)[0]);
        $this->assert_schema_valid($result);
        $doc = new \DOMDocument();
        $doc->loadXML($result['body']);
        $xp = new \DOMXPath($doc);
        $this->assertSame('1', $xp->evaluate('string(count(//PARTICIPANTE))'));
        $this->assertSame('12345678', trim($xp->evaluate('string(//PARTICIPANTE/ID_PARTICIPANTE/NUM_DOCUMENTO)')));
        $this->assertSame('Z', $xp->evaluate('string(//PARTICIPANTE/ID_PARTICIPANTE/LETRA_NIF)'));
        $this->assertSame('0', $xp->evaluate('string(//RESULTADOS/RESULTADO_FINAL)'));
        $this->assertSame('E', $xp->evaluate('string(//TUTOR_FORMADOR/ID_TUTOR/TIPO_DOCUMENTO)'));
        $this->assertSame('7', $xp->evaluate('string(//HORARIO_TARDE/NUMERO_ACCESOS)'));
        // 5400 seconds are 1.5 hours: a fraction of exactly 0.5 rounds down (data model B11.1.9.1.3).
        $this->assertSame('1', $xp->evaluate('string(//HORARIO_TARDE/DURACION_TOTAL)'));
        $this->assertSame('1', $xp->evaluate('string(//HORARIO_MANANA/NUM_PARTICIPANTES)'));
        $this->assertSame('0', $xp->evaluate('string(count(//HORARIO_NOCHE))'));
        $this->assertSame('1', $xp->evaluate('string(//SEGUIMIENTO_EVALUACION/NUMERO_ACTIVIDADES_EVALUACION)'));

        // Before the start only the authorisation data is reported.
        $early = $this->call('obtenerAccion', store::parse_date('01/11/2026'));
        $docearly = new \DOMDocument();
        $docearly->loadXML($early['body']);
        $this->assertSame(0, (new \DOMXPath($docearly))->query('//PARTICIPANTE')->length);
    }

    /**
     * Identity documents: control letters and parsing.
     */
    public function test_identity(): void {
        $this->assertSame('Z', identity::letter('D', '12345678'));
        $this->assertSame('T', identity::letter('D', '00000000'));
        $this->assertSame('L', identity::letter('E', 'X1234567'));
        $this->assertSame('M', identity::letter('E', 'Z0000000'));
        $this->assertNull(identity::letter('D', 'abc'));
        $this->assertNull(identity::letter('X', '1'));
        $parsed = identity::parse(' 12.345.678-z ');
        $this->assertSame(['type' => 'D', 'number' => '  12345678', 'letter' => 'Z'], $parsed);
        $this->assertSame(10, strlen($parsed['number']));
        $this->assertSame('E', identity::parse('X1234567L')['type']);
        $this->assertNull(identity::parse('12345678A'));
        $this->assertNull(identity::parse('hello'));
        $this->assertSame('  00000000', identity::parse('0T')['number']);
    }

    /**
     * Lifecycle phases and deletion rules.
     */
    public function test_lifecycle(): void {
        $start = 1000 * DAYSECS;
        $end = $start + 60 * DAYSECS;
        $this->assertSame(lifecycle::AUTHORISATION, lifecycle::phase($start, $end, $start - 1));
        $this->assertSame(lifecycle::DEVELOPMENT, lifecycle::phase($start, $end, $start));
        $this->assertSame(lifecycle::DEVELOPMENT, lifecycle::phase($start, $end, $end));
        $this->assertSame(lifecycle::CLOSING, lifecycle::phase($start, $end, $end + 2 * DAYSECS));
        $this->assertSame(lifecycle::CLOSING, lifecycle::phase($start, $end, $end + 90 * DAYSECS));
        $this->assertSame(lifecycle::CLOSED, lifecycle::phase($start, $end, $end + 91 * DAYSECS));
        $this->assertFalse(lifecycle::reports_tracking_data(lifecycle::AUTHORISATION));
        $this->assertTrue(lifecycle::reports_tracking_data(lifecycle::CLOSING));
        $this->assertTrue(lifecycle::may_delete(20, $start, $end, $start - DAYSECS));
        $this->assertFalse(lifecycle::may_delete(30, $start, $end, $start + DAYSECS));
        $this->assertFalse(lifecycle::may_delete(40, $start, $end, $end + 30 * DAYSECS));
        $this->assertTrue(lifecycle::may_delete(40, $start, $end, $end + 31 * DAYSECS));
        $this->assertTrue(lifecycle::has_started(30, $start, 0));
        $this->assertFalse(lifecycle::has_started(50, $start, $start + 1));
        $this->assertTrue(lifecycle::has_started(20, $start, $start));
        $this->assertFalse(lifecycle::has_started(20, $start, $start - 1));
    }

    /**
     * Result codes and number formats of the data model.
     */
    public function test_result_calculator(): void {
        $this->assertSame(1, result_calculator::code(true, 'placement', false, false, false));
        $this->assertSame(2, result_calculator::code(true, 'health', true, false, true));
        $this->assertSame(3, result_calculator::code(false, '', true, false, true));
        $this->assertSame(4, result_calculator::code(false, '', false, true, false));
        $this->assertSame(5, result_calculator::code(false, '', false, false, true));
        $this->assertSame(0, result_calculator::code(false, '', false, false, false));
        $this->assertSame(760, result_calculator::times100(7.6));
        $this->assertSame(830, result_calculator::times100(8.3));
        $this->assertEquals(6.0, result_calculator::floor_score(5.5, 6.0));
        $this->assertEquals(5.0, result_calculator::floor_score(4.0, 4.5));
        $this->assertEquals(8.0, result_calculator::floor_score(8.0, 6.0));
        $this->assertSame(1, result_calculator::hours(5400));
        $this->assertSame(2, result_calculator::hours(5401));
        $this->assertSame(0, result_calculator::hours(1800));
        $this->assertSame(0, result_calculator::hours(0));
    }
}
