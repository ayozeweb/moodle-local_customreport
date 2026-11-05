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
 * Builds the ACCION_FORMATIVA element returned by the web service.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\sepe;

use local_customreport\local\cmrole_manager;
use local_customreport\report\followup;

/**
 * Takes the authorisation data received through crearAccion and completes it, depending on the lifecycle
 * phase, with tutors, use of the platform, participants and results of the linked Moodle action.
 */
class builder {
    /** @var \DOMDocument */
    private $doc;

    /** @var \DOMXPath */
    private $xp;

    /** @var \stdClass */
    private $ws;

    /** @var \stdClass|null Linked local action. */
    private $action;

    /** @var int */
    private $now;

    /**
     * Build the element.
     *
     * @param \stdClass $ws Row of local_customreport_wsaction.
     * @param int|null $now
     * @return \DOMDocument Document whose root is entsal:ACCION_FORMATIVA.
     */
    public static function build(\stdClass $ws, ?int $now = null): \DOMDocument {
        global $DB;
        $builder = new self();
        $builder->now = $now ?? time();
        $builder->ws = $ws;
        $builder->doc = new \DOMDocument('1.0', 'UTF-8');
        $builder->doc->loadXML($ws->payload);
        $builder->xp = new \DOMXPath($builder->doc);
        $builder->action = $ws->actionid ? ($DB->get_record('local_customreport_action', ['id' => $ws->actionid]) ?: null) : null;
        $phase = lifecycle::phase((int) $ws->startdate, (int) $ws->enddate, $builder->now);
        if ($builder->action) {
            $builder->apply_real_dates();
            if (lifecycle::reports_tracking_data($phase)) {
                $builder->fill_tracking();
            }
        }
        return $builder->doc;
    }

    /**
     * Dates already happened are reported as real dates (data model point 16).
     */
    private function apply_real_dates(): void {
        foreach (['FECHA_INICIO' => 'startreal', 'FECHA_FIN' => 'endreal'] as $tag => $field) {
            if ($this->action->$field) {
                foreach ($this->xp->query("/*/$tag") as $node) {
                    $node->nodeValue = store::format_date((int) $this->action->$field);
                }
            }
        }
    }

    /**
     * Fill tutors, use and participants.
     */
    private function fill_tracking(): void {
        $courseid = (int) $this->action->courseid;
        $learners = $this->learner_ids();
        $data = $learners ? followup::build($this->action, $learners) : null;
        foreach ($this->xp->query('/*/ESPECIALIDADES_ACCION/ESPECIALIDAD') as $spec) {
            $this->replace_children($this->xp->query('TUTORES_FORMADORES', $spec)->item(0), $this->tutors($courseid));
            $this->replace_children($this->xp->query('USO', $spec)->item(0), $this->usage($courseid, $learners));
        }
        $this->replace_children($this->xp->query('/*/PARTICIPANTES')->item(0), $data ? $this->participants($data) : []);
    }

    /**
     * Learners that are enrolled (or were) in the action.
     *
     * @return int[]
     */
    private function learner_ids(): array {
        global $DB;
        $ids = array_map('intval', $DB->get_fieldset_select(
            'local_customreport_progress',
            'DISTINCT userid',
            'courseid = ?',
            [$this->action->courseid]
        ));
        if ($this->action->groupid) {
            $members = array_map('intval', array_keys(groups_get_members($this->action->groupid, 'u.id')));
            $ids = array_values(array_intersect($ids, $members));
        }
        return $ids;
    }

    /**
     * Replace the children of an element.
     *
     * @param \DOMElement|null $parent
     * @param \DOMNode[] $children
     */
    private function replace_children(?\DOMElement $parent, array $children): void {
        if (!$parent) {
            return;
        }
        while ($parent->firstChild) {
            $parent->removeChild($parent->firstChild);
        }
        foreach ($children as $child) {
            $parent->appendChild($child);
        }
    }

    /**
     * Create an element without namespace (local elements of the schema are unqualified).
     *
     * @param string $name
     * @param string|int|null $text
     * @param \DOMElement[] $children
     * @return \DOMElement
     */
    private function el(string $name, $text = null, array $children = []): \DOMElement {
        $el = $this->doc->createElement($name);
        if ($text !== null) {
            $el->appendChild($this->doc->createTextNode((string) $text));
        }
        foreach ($children as $child) {
            $el->appendChild($child);
        }
        return $el;
    }

    /**
     * ID element with the document of a user.
     *
     * @param string $name Element name (ID_TUTOR or ID_PARTICIPANTE).
     * @param \stdClass $idoc
     * @return \DOMElement
     */
    private function id_el(string $name, \stdClass $idoc): \DOMElement {
        return $this->el($name, null, [$this->el('TIPO_DOCUMENTO', $idoc->doctype),
            $this->el('NUM_DOCUMENTO', str_pad($idoc->docnumber, 10)), $this->el('LETRA_NIF', $idoc->docletter)]);
    }

    /**
     * Tutors-trainers of the course (only those with an identity document).
     *
     * @param int $courseid
     * @return \DOMElement[]
     */
    private function tutors(int $courseid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $context = \context_course::instance($courseid);
        $users = get_enrolled_users(
            $context,
            'local/customreport:viewgroupreports',
            (int) $this->action->groupid,
            'u.id',
            null,
            0,
            0,
            true
        );
        $result = [];
        foreach ($users as $user) {
            $idoc = $DB->get_record('local_customreport_idoc', ['userid' => $user->id]);
            if (!$idoc) {
                continue;
            }
            $profile = (array) profile_user_record($user->id, false);
            $int = fn($key) => (int) preg_replace('/\D/', '', (string) ($profile[$key] ?? '0'));
            $result[] = $this->el('TUTOR_FORMADOR', null, [
                $this->id_el('ID_TUTOR', $idoc),
                $this->el('ACREDITACION_TUTOR', \core_text::substr((string) ($profile['cr_tutor_edulevel'] ?? ''), 0, 200)),
                $this->el('EXPERIENCIA_PROFESIONAL', $int('cr_tutor_competenceyears')),
                $this->el('COMPETENCIA_DOCENTE', $this->competence_code((string) ($profile['cr_tutor_teachingcompetence'] ?? ''))),
                $this->el('EXPERIENCIA_MODALIDAD_TELEFORMACION', $int('cr_tutor_onlinehours')),
                // The catalogue of online training certificates is not in the repository documents (TODO-NORMATIVA).
                $this->el('FORMACION_MODALIDAD_TELEFORMACION', ''),
            ]);
        }
        return $result;
    }

    /**
     * Teaching competence code (01-06) from the stored menu label.
     *
     * @param string $label
     * @return string
     */
    private function competence_code(string $label): string {
        for ($i = 1; $i <= 6; $i++) {
            if ($label === get_string('opt_tc0' . $i, 'local_customreport')) {
                return '0' . $i;
            }
        }
        return '';
    }

    /**
     * Use of the platform per time-of-day slot and follow-up of activities.
     *
     * @param int $courseid
     * @param int[] $learners
     * @return \DOMElement[]
     */
    private function usage(int $courseid, array $learners): array {
        global $DB;
        $result = [];
        if (!$learners) {
            return $result;
        }
        [$insql, $params] = $DB->get_in_or_equal($learners, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_sql(
            "SELECT slot, COUNT(DISTINCT userid) AS participants, SUM(accesses) AS accesses,
                                             SUM(seconds) AS seconds
                                        FROM {local_customreport_slot}
                                       WHERE courseid = :courseid AND userid $insql GROUP BY slot ORDER BY slot",
            ['courseid' => $courseid] + $params
        );
        foreach ([1 => 'HORARIO_MANANA', 2 => 'HORARIO_TARDE', 3 => 'HORARIO_NOCHE'] as $slot => $name) {
            if (isset($rows[$slot])) {
                $result[] = $this->el($name, null, [$this->el('NUM_PARTICIPANTES', $rows[$slot]->participants),
                    $this->el('NUMERO_ACCESOS', $rows[$slot]->accesses),
                    $this->el('DURACION_TOTAL', result_calculator::hours((int) $rows[$slot]->seconds))]);
            }
        }
        $stats = $this->activity_stats($courseid, $learners);
        if ($stats['participants']) {
            $result[] = $this->el('SEGUIMIENTO_EVALUACION', null, [
                $this->el('NUM_PARTICIPANTES', $stats['participants']),
                $this->el('NUMERO_ACTIVIDADES_APRENDIZAJE', $stats['learning']),
                $this->el('NUMERO_INTENTOS', $stats['attempts']),
                $this->el('NUMERO_ACTIVIDADES_EVALUACION', $stats['evaluation']),
            ]);
        }
        return $result;
    }

    /**
     * Completed learning and evaluation activities. Attempts are approximated by the completions of learning
     * activities plus the extra finished quiz attempts (TODO-NORMATIVA: exact definition of attempts).
     *
     * @param int $courseid
     * @param int[] $learners
     * @return array
     */
    private function activity_stats(int $courseid, array $learners): array {
        global $DB;
        $roles = cmrole_manager::get_roles($courseid);
        [$insql, $params] = $DB->get_in_or_equal($learners, SQL_PARAMS_NAMED);
        $sql = "SELECT cmc.id, cmc.coursemoduleid, cmc.userid
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid AND cmc.userid $insql AND cmc.completionstate IN (:c1, :c2)";
        $stats = ['participants' => 0, 'learning' => 0, 'evaluation' => 0, 'attempts' => 0];
        $users = [];
        foreach (
            $DB->get_recordset_sql($sql, ['courseid' => $courseid, 'c1' => COMPLETION_COMPLETE,
                'c2' => COMPLETION_COMPLETE_PASS] + $params) as $row
        ) {
            $role = $roles[$row->coursemoduleid] ?? 'none';
            $users[$row->userid] = true;
            if (in_array($role, ['process', 'final1', 'final2'], true)) {
                $stats['evaluation']++;
            } else if ($role === 'none') {
                $stats['learning']++;
                $stats['attempts']++;
            }
        }
        $stats['participants'] = count($users);
        return $stats;
    }

    /**
     * Participants with identity document, dates and results.
     *
     * @param array $data Output of followup::build().
     * @return \DOMElement[]
     */
    private function participants(array $data): array {
        global $DB;
        $profile = $data['profile'];
        $spec = $this->xp->query('/*/ID_ESPECIALIDAD_PRINCIPAL')->item(0);
        $causes = $DB->get_records_menu('local_customreport_cause', null, '', 'id, code');
        $keycomp = get_config('local_customreport', 'ws_keycompetence') ?: '01';
        $nodes = $DB->get_records('local_customreport_module', ['actionid' => $this->action->id, 'type' => 'MF']);
        $ended = $this->ws->enddate > 0 && $this->now > $this->ws->enddate;
        $result = [];
        foreach ($data['learners'] as $learner) {
            $idoc = $DB->get_record('local_customreport_idoc', ['userid' => $learner['userid']]);
            if (!$idoc) {
                continue;
            }
            $events = $DB->get_records('local_customreport_enrol', ['courseid' => $this->action->courseid,
                'userid' => $learner['userid']], 'eventtime, id');
            $from = 0;
            $to = 0;
            $causecode = '';
            foreach ($events as $event) {
                if ($event->eventtype === 'create' && !$from) {
                    $from = (int) $event->eventtime;
                }
                if ($event->eventtype === 'delete') {
                    $to = (int) $event->eventtime;
                    $causecode = $causes[$event->causeid] ?? '';
                } else if ($event->eventtype === 'create') {
                    $to = 0;
                }
            }
            $dropped = $learner['status'] === 'dropped';
            [$allpassed, $failed, $final, $score] = $this->evaluate($learner, $nodes, $profile);
            $code = result_calculator::code($dropped, $causecode, $allpassed, $failed, $ended);
            $results = [$this->el('RESULTADO_FINAL', $code)];
            if ($code === result_calculator::PASSED && $final !== null) {
                $results[] = $this->el('CALIFICACION_FINAL', result_calculator::times100($final));
                $results[] = $this->el('PUNTUACION_FINAL', result_calculator::times100($score));
            }
            $specialty = [$this->clone_children('ID_ESPECIALIDAD', $spec)];
            if ($from) {
                $specialty[] = $this->el('FECHA_ALTA', store::format_date($from));
            }
            if ($to && $dropped) {
                $specialty[] = $this->el('FECHA_BAJA', store::format_date($to));
            }
            // Face-to-face tutorials and final evaluation sessions are not recorded by Moodle (TODO-NORMATIVA).
            $specialty[] = $this->el('TUTORIAS_PRESENCIALES');
            $specialty[] = $this->el('EVALUACION_FINAL');
            $specialty[] = $this->el('RESULTADOS', null, $results);
            $result[] = $this->el('PARTICIPANTE', null, [
                $this->id_el('ID_PARTICIPANTE', $idoc),
                $this->el('INDICADOR_COMPETENCIAS_CLAVE', $keycomp),
                $this->el('CONTRATO_FORMACION'),
                $this->el('ESPECIALIDADES_PARTICIPANTE', null, [$this->el('ESPECIALIDAD', null, $specialty)]),
            ]);
        }
        return $result;
    }

    /**
     * Evaluate the modules of a learner.
     *
     * @param array $learner
     * @param \stdClass[] $nodes MF nodes of the action.
     * @param \stdClass $profile
     * @return array [allpassed, failed, mean final test, mean final score]
     */
    private function evaluate(array $learner, array $nodes, \stdClass $profile): array {
        $finals = [];
        $scores = [];
        $allpassed = (bool) $nodes;
        $failed = false;
        $min = (float) $profile->minfinal;
        foreach ($nodes as $node) {
            $d = $learner['nodes'][$node->id] ?? null;
            if (!$d || empty($d['passed'])) {
                $allpassed = false;
            } else {
                $final = $d['call'] === 2 ? $d['final2'] : $d['final1'];
                $finals[] = $final;
                $scores[] = result_calculator::floor_score((float) $d['score'], (float) $final);
            }
            if (
                $d && $d['final1'] !== null && $d['final1'] < $min &&
                    ((int) $profile->attempts === 1 || ($d['final2'] !== null && $d['final2'] < $min))
            ) {
                $failed = true;
            }
        }
        $final = $finals ? array_sum($finals) / count($finals) : null;
        $score = $scores ? array_sum($scores) / count($scores) : null;
        return [$allpassed, $failed, $final, $score];
    }

    /**
     * Copy of the children of an element under a new element name.
     *
     * @param string $name
     * @param \DOMElement|null $source
     * @return \DOMElement
     */
    private function clone_children(string $name, ?\DOMElement $source): \DOMElement {
        $el = $this->el($name);
        if ($source) {
            foreach ($source->childNodes as $child) {
                $el->appendChild($child->cloneNode(true));
            }
        }
        return $el;
    }
}
