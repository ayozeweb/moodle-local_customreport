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
 * Follow-up report data (visit report, Annex V.B).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\report;

use local_customreport\local\profile_manager;
use local_customreport\local\score_calculator;
use local_customreport\local\structure_manager;

/**
 * Builds the data of the follow-up report for an action from the aggregated tables only.
 */
class followup {
    /**
     * Build the report.
     *
     * @param \stdClass $action Training action record.
     * @param int[] $userids Learners included (already filtered by the caller's permissions).
     * @param int $from Start of the interval (0 = no limit).
     * @param int $to End of the interval (0 = no limit).
     * @param int $nodeid Module/unit filter (0 = whole action).
     * @return array ['action', 'profile', 'filters', 'nodes', 'learners', 'summary', 'areas']
     */
    public static function build(\stdClass $action, array $userids, int $from = 0, int $to = 0, int $nodeid = 0): array {
        global $DB;
        $profile = profile_manager::get($action->profile) ?? profile_manager::get('generico');
        $nodes = structure_manager::get_nodes($action->id);
        $nodecms = self::node_cmids($action, $nodes);
        $filtercms = $nodeid && isset($nodecms[$nodeid]) ? $nodecms[$nodeid] : null;

        $learners = [];
        if ($userids) {
            $users = $DB->get_records_list(
                'user',
                'id',
                $userids,
                'lastname, firstname',
                'id, ' . implode(', ', \core_user\fields::get_name_fields())
            );
            foreach ($users as $user) {
                $learners[$user->id] = ['userid' => (int) $user->id, 'fullname' => fullname($user), 'accesses' => 0,
                    'participations' => 0, 'seconds' => 0, 'firstaccess' => 0, 'lastaccess' => 0, 'platformaccesses' => 0,
                    'total' => 0, 'done' => 0, 'percent' => 0.0, 'processavg' => null, 'final1' => null, 'final2' => null,
                    'score' => null, 'call' => null, 'passed' => false, 'status' => 'notstarted',
                    'nodes' => []];
            }
        }
        $areas = [];
        $nodeseconds = [];
        if ($learners) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($learners), SQL_PARAMS_NAMED);
            $where = "userid $insql AND courseid = :courseid";
            $params['courseid'] = $action->courseid;
            if ($from) {
                $where .= ' AND day >= :from';
                $params['from'] = \local_customreport\local\session_calculator::day_start(
                    $from,
                    \core_date::get_server_timezone_object()
                );
            }
            if ($to) {
                $where .= ' AND day <= :to';
                $params['to'] = $to;
            }
            $rs = $DB->get_recordset_select(
                'local_customreport_usage',
                $where,
                $params,
                '',
                'id, userid, cmid, component, accesses, participations, seconds, firstaccess, lastaccess'
            );
            foreach ($rs as $row) {
                if ($filtercms !== null && !in_array((int) $row->cmid, $filtercms, true)) {
                    continue;
                }
                $l = &$learners[$row->userid];
                $l['accesses'] += $row->accesses;
                $l['participations'] += $row->participations;
                $l['seconds'] += $row->seconds;
                $l['firstaccess'] = $l['firstaccess'] ? min($l['firstaccess'], $row->firstaccess ?: $l['firstaccess']) :
                    (int) $row->firstaccess;
                $l['lastaccess'] = max($l['lastaccess'], (int) $row->lastaccess);
                unset($l);
                $area = $row->component ?: 'core';
                $areas[$area] = $areas[$area] ?? ['component' => $area, 'accesses' => 0, 'seconds' => 0, 'participations' => 0];
                $areas[$area]['accesses'] += $row->accesses;
                $areas[$area]['seconds'] += $row->seconds;
                $areas[$area]['participations'] += $row->participations;
                foreach ($nodecms as $id => $cmids) {
                    if (in_array((int) $row->cmid, $cmids, true)) {
                        $nodeseconds[$id] = ($nodeseconds[$id] ?? 0) + $row->seconds;
                    }
                }
            }
            $rs->close();
            foreach (
                $DB->get_records_sql("SELECT userid, SUM(accesses) AS accesses FROM {local_customreport_usage}
                    WHERE courseid = 0 AND userid $insql GROUP BY userid", $params + []) as $row
            ) {
                $learners[$row->userid]['platformaccesses'] = (int) $row->accesses;
            }
            self::add_progress($learners, $action, $nodes, $nodeid, $profile);
            self::add_status($learners, $action);
        }
        $totalaccesses = array_sum(array_column($areas, 'accesses'));
        foreach ($areas as &$area) {
            $area['accessespercent'] = $totalaccesses ? round(100 * $area['accesses'] / $totalaccesses, 1) : 0.0;
        }
        unset($area);

        return [
            'action' => $action,
            'profile' => $profile,
            'filters' => ['from' => $from, 'to' => $to, 'nodeid' => $nodeid],
            'nodes' => self::node_summary($nodes, $learners, $nodeseconds),
            'learners' => array_values($learners),
            'summary' => self::summary($learners, $nodes),
            'areas' => array_values($areas),
        ];
    }

    /**
     * Activities of each module/unit.
     *
     * @param \stdClass $action
     * @param \stdClass[] $nodes
     * @return int[][] nodeid => cmids
     */
    public static function node_cmids(\stdClass $action, array $nodes): array {
        $modinfo = get_fast_modinfo($action->courseid);
        $sectioncms = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sectioncms[$section->id] = $modinfo->sections[$section->sectionnum] ?? [];
        }
        $result = [];
        foreach ($nodes as $node) {
            $result[(int) $node->id] = [];
            foreach (structure_manager::node_sections($node) as $sectionid) {
                $cmids = array_map('intval', $sectioncms[$sectionid] ?? []);
                $result[(int) $node->id] = array_merge($result[(int) $node->id], $cmids);
            }
        }
        return $result;
    }

    /**
     * Add progress, grades and the final score to the learners.
     *
     * @param array $learners
     * @param \stdClass $action
     * @param \stdClass[] $nodes
     * @param int $nodeid
     * @param \stdClass $profile
     */
    private static function add_progress(array &$learners, \stdClass $action, array $nodes, int $nodeid, \stdClass $profile): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(array_keys($learners), SQL_PARAMS_NAMED);
        $rows = $DB->get_recordset_select(
            'local_customreport_progress',
            "courseid = :courseid AND userid $insql",
            $params + ['courseid' => $action->courseid]
        );
        foreach ($rows as $row) {
            $score = score_calculator::calculate(
                $row->processavg === null ? null : (float) $row->processavg,
                $row->final1 === null ? null : (float) $row->final1,
                $row->final2 === null ? null : (float) $row->final2,
                $profile
            );
            $data = ['nodeid' => (int) $row->nodeid, 'total' => (int) $row->activitiestotal, 'done' => (int) $row->activitiesdone,
                'percent' => $row->activitiestotal ? round(100 * $row->activitiesdone / $row->activitiestotal, 1) : 0.0,
                'processavg' => $row->processavg === null ? null : (float) $row->processavg,
                'final1' => $row->final1 === null ? null : (float) $row->final1,
                'final2' => $row->final2 === null ? null : (float) $row->final2] + $score;
            $learners[$row->userid]['nodes'][(int) $row->nodeid] = $data;
            if ((int) $row->nodeid === $nodeid) {
                $learners[$row->userid] = array_merge($learners[$row->userid], $data);
            }
        }
        $rows->close();
    }

    /**
     * Add started / completed / dropped status.
     *
     * @param array $learners
     * @param \stdClass $action
     */
    private static function add_status(array &$learners, \stdClass $action): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(array_keys($learners), SQL_PARAMS_NAMED);
        $last = [];
        $events = $DB->get_records_select(
            'local_customreport_enrol',
            "courseid = :courseid AND userid $insql",
            $params + ['courseid' => $action->courseid],
            'eventtime, id'
        );
        foreach ($events as $event) {
            $last[$event->userid] = $event->eventtype;
        }
        foreach ($learners as $userid => &$learner) {
            $accessed = $learner['accesses'] > 0 || $learner['seconds'] > 0;
            if (($last[$userid] ?? '') === 'delete') {
                $learner['status'] = 'dropped';
            } else if ($learner['total'] > 0 && $learner['done'] >= $learner['total']) {
                $learner['status'] = 'completed';
            } else if ($accessed) {
                $learner['status'] = 'started';
            }
        }
    }

    /**
     * Per module/unit statistics.
     *
     * @param \stdClass[] $nodes
     * @param array $learners
     * @param int[] $nodeseconds
     * @return array
     */
    private static function node_summary(array $nodes, array $learners, array $nodeseconds): array {
        $result = [];
        $count = count($learners);
        foreach ($nodes as $node) {
            $passed = 0;
            foreach ($learners as $learner) {
                $data = $learner['nodes'][$node->id] ?? null;
                if ($data && self::node_passed($node, $data)) {
                    $passed++;
                }
            }
            $seconds = $nodeseconds[$node->id] ?? 0;
            $result[] = ['nodeid' => (int) $node->id, 'type' => $node->type, 'code' => $node->code, 'name' => $node->name,
                'hours' => (int) $node->hours, 'passed' => $passed,
                'passedpercent' => $count ? round(100 * $passed / $count, 1) : 0.0,
                'seconds' => $seconds, 'avgseconds' => $count ? (int) round($seconds / $count) : 0];
        }
        return $result;
    }

    /**
     * Whether a node counts as passed: score pass for modules/units, all activities done for practice.
     *
     * @param \stdClass $node
     * @param array $data
     * @return bool
     */
    private static function node_passed(\stdClass $node, array $data): bool {
        if ($node->type === 'MP') {
            return $data['total'] > 0 && $data['done'] >= $data['total'];
        }
        return !empty($data['passed']);
    }

    /**
     * Global totals, averages and percentages.
     *
     * @param array $learners
     * @param \stdClass[] $nodes
     * @return array
     */
    private static function summary(array $learners, array $nodes): array {
        $count = count($learners);
        $pct = fn($n) => $count ? round(100 * $n / $count, 1) : 0.0;
        $by = array_count_values(array_column($learners, 'status'));
        $started = count(array_filter($learners, fn($l) => $l['accesses'] > 0 || $l['seconds'] > 0 || $l['done'] > 0));
        $scores = array_filter(array_column($learners, 'score'), fn($v) => $v !== null);
        $mfnodes = array_filter($nodes, fn($n) => $n->type === 'MF');
        $passall = 0;
        $passfct = 0;
        $hasfct = false;
        foreach ($learners as $learner) {
            $ok = (bool) $mfnodes;
            foreach ($mfnodes as $node) {
                $ok = $ok && !empty($learner['nodes'][$node->id]) && self::node_passed($node, $learner['nodes'][$node->id]);
            }
            $passall += $ok ? 1 : 0;
            foreach ($nodes as $node) {
                if ($node->type === 'MP') {
                    $hasfct = true;
                    $data = $learner['nodes'][$node->id] ?? null;
                    $passfct += $data && self::node_passed($node, $data) ? 1 : 0;
                }
            }
        }
        $div = fn($sum) => $count ? $sum / $count : 0;
        return [
            'learners' => $count, 'started' => $started, 'startedpercent' => $pct($started),
            'completed' => $by['completed'] ?? 0, 'completedpercent' => $pct($by['completed'] ?? 0),
            'dropped' => $by['dropped'] ?? 0, 'droppedpercent' => $pct($by['dropped'] ?? 0),
            'totalseconds' => array_sum(array_column($learners, 'seconds')),
            'avgseconds' => (int) round($div(array_sum(array_column($learners, 'seconds')))),
            'accesses' => array_sum(array_column($learners, 'accesses')),
            'participations' => array_sum(array_column($learners, 'participations')),
            'avgscore' => $scores ? round(array_sum($scores) / count($scores), 2) : null,
            'passall' => $passall, 'passallpercent' => $pct($passall),
            'passfct' => $hasfct ? $passfct : null, 'passfctpercent' => $hasfct ? $pct($passfct) : null,
        ];
    }
}
