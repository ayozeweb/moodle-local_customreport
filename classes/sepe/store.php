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
 * Persistence of the web service data.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\sepe;

/**
 * Centre, actions, identity documents and log of the follow-up web service.
 */
class store {
    /**
     * Parse a dd/mm/yyyy date into a timestamp (server timezone midnight).
     *
     * @param string $text
     * @return int 0 when empty or invalid.
     */
    public static function parse_date(string $text): int {
        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', trim($text), \core_date::get_server_timezone_object());
        return $date ? $date->getTimestamp() : 0;
    }

    /**
     * Format a timestamp as dd/mm/yyyy.
     *
     * @param int $time
     * @return string
     */
    public static function format_date(int $time): string {
        return (new \DateTimeImmutable('@' . $time))->setTimezone(\core_date::get_server_timezone_object())->format('d/m/Y');
    }

    /**
     * The stored centre.
     *
     * @return \stdClass|null
     */
    public static function get_center(): ?\stdClass {
        global $DB;
        $rows = $DB->get_records('local_customreport_wscenter', null, 'id', '*', 0, 1);
        return $rows ? reset($rows) : null;
    }

    /**
     * Save the centre identification from a DATOS_IDENTIFICATIVOS element.
     *
     * @param \DOMElement $el
     */
    public static function save_center(\DOMElement $el): void {
        global $DB;
        $text = fn(string $path) => trim((new \DOMXPath($el->ownerDocument))->evaluate("string($path)", $el));
        $record = (object) [
            'origin' => $text('ID_CENTRO/ORIGEN_CENTRO'), 'code' => $text('ID_CENTRO/CODIGO_CENTRO'),
            'name' => $text('NOMBRE_CENTRO'), 'urlplatform' => $text('URL_PLATAFORMA'),
            'urltracking' => $text('URL_SEGUIMIENTO'), 'phone' => $text('TELEFONO'), 'email' => $text('EMAIL'),
            'timemodified' => time(),
        ];
        $existing = self::get_center();
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_customreport_wscenter', $record);
        } else {
            $DB->insert_record('local_customreport_wscenter', $record);
        }
    }

    /**
     * Find a stored action by its identifier.
     *
     * @param string $origin
     * @param string $code
     * @return \stdClass|null
     */
    public static function get_action(string $origin, string $code): ?\stdClass {
        global $DB;
        return $DB->get_record('local_customreport_wsaction', ['origin' => trim($origin), 'code' => trim($code)]) ?: null;
    }

    /**
     * Save a new action from an ACCION_FORMATIVA element and link it to a local action with the same code.
     *
     * @param \DOMElement $el
     * @param int|null $now
     * @return \stdClass
     */
    public static function save_action(\DOMElement $el, ?int $now = null): \stdClass {
        global $DB;
        $xp = new \DOMXPath($el->ownerDocument);
        $text = fn(string $path) => trim($xp->evaluate("string($path)", $el));
        $code = $text('ID_ACCION/CODIGO_ACCION');
        $record = (object) [
            'origin' => $text('ID_ACCION/ORIGEN_ACCION'), 'code' => $code,
            'situation' => (int) $text('SITUACION'),
            'startdate' => self::parse_date($text('FECHA_INICIO')), 'enddate' => self::parse_date($text('FECHA_FIN')),
            'payload' => self::standalone_xml($el),
            'actionid' => (int) $DB->get_field(
                'local_customreport_action',
                'id',
                ['actioncode' => $code, 'groupid' => 0],
                IGNORE_MULTIPLE
            ),
            'timecreated' => $now ?? time(), 'timemodified' => $now ?? time(),
        ];
        $record->id = $DB->insert_record('local_customreport_wsaction', $record);
        return $record;
    }

    /**
     * Serialise an element with its namespace declarations (they may live on an ancestor of the request).
     *
     * @param \DOMElement $el
     * @return string
     */
    private static function standalone_xml(\DOMElement $el): string {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->appendChild($doc->importNode($el, true));
        return $doc->saveXML($doc->documentElement);
    }

    /**
     * Delete a stored action.
     *
     * @param int $id
     */
    public static function delete_action(int $id): void {
        global $DB;
        $DB->delete_records('local_customreport_wsaction', ['id' => $id]);
    }

    /**
     * Actions already started (for obtenerListaAcciones), ordered by code.
     *
     * @param int $now
     * @return \stdClass[]
     */
    public static function started_actions(int $now): array {
        global $DB;
        return array_filter(
            $DB->get_records('local_customreport_wsaction', null, 'code'),
            fn($a) => lifecycle::has_started((int) $a->situation, (int) $a->startdate, $now)
        );
    }

    /**
     * Log a request and response.
     *
     * @param string $operation
     * @param string $username
     * @param string $remoteaddr
     * @param int $returncode
     * @param string $request
     * @param string $response
     * @param int|null $now
     */
    public static function log(
        string $operation,
        string $username,
        string $remoteaddr,
        int $returncode,
        string $request,
        string $response,
        ?int $now = null
    ): void {
        global $DB;
        // Never keep the password of the WS-Security header in the log.
        $request = preg_replace('~(<(?:\w+:)?Password\b[^>]*>)[^<]*(</)~i', '$1***$2', $request);
        $DB->insert_record('local_customreport_wslog', (object) ['timecreated' => $now ?? time(),
            'operation' => \core_text::substr($operation, 0, 40), 'username' => \core_text::substr($username, 0, 100),
            'remoteaddr' => \core_text::substr($remoteaddr, 0, 45), 'returncode' => $returncode,
            'request' => $request, 'response' => $response]);
    }

    /**
     * Delete log entries older than the retention period.
     *
     * @param int|null $now
     * @return int Number of deleted rows.
     */
    public static function purge_log(?int $now = null): int {
        global $DB;
        $days = (int) (get_config('local_customreport', 'logretentiondays') ?: 365);
        $limit = ($now ?? time()) - $days * DAYSECS;
        $count = $DB->count_records_select('local_customreport_wslog', 'timecreated < ?', [$limit]);
        $DB->delete_records_select('local_customreport_wslog', 'timecreated < ?', [$limit]);
        return $count;
    }
}
