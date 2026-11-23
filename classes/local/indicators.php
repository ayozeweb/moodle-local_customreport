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
 * Tutor indicators.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Indicators for the tutor: learners without access, behind the planned pace and overdue activities.
 */
class indicators {
    /** @var int Percentage points of tolerance before a learner counts as behind the planned pace. */
    const PACE_TOLERANCE = 10;

    /**
     * Expected completion percentage at a given time according to the planned dates.
     *
     * @param \stdClass $action
     * @param int $now
     * @return float|null Null when the dates are unknown.
     */
    public static function expected_percent(\stdClass $action, int $now): ?float {
        $start = (int) ($action->startreal ?: $action->startplanned);
        $end = (int) ($action->endreal ?: $action->endplanned);
        if (!$start || !$end || $end <= $start) {
            return null;
        }
        return max(0.0, min(100.0, 100 * ($now - $start) / ($end - $start)));
    }

    /**
     * Compute the indicators of the learners of an action.
     *
     * @param \stdClass $action
     * @param int[] $userids Learners to evaluate.
     * @param int|null $now
     * @param int|null $days Days without access (defaults to the setting).
     * @return array userid => ['noaccess' => bool, 'lastaccess' => int, 'behind' => bool, 'overdue' => int]
     */
    public static function for_learners(\stdClass $action, array $userids, ?int $now = null, ?int $days = null): array {
        global $DB;
        $now = $now ?? time();
        $days = $days ?? (int) (get_config('local_customreport', 'inactivitydays') ?: 7);
        $result = [];
        if (!$userids) {
            return $result;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $last = $DB->get_records_sql("SELECT userid, MAX(lastaccess) AS lastaccess FROM {local_customreport_usage}
                WHERE courseid = :courseid AND userid $insql GROUP BY userid", ['courseid' => $action->courseid] + $params);
        $progress = $DB->get_records_select(
            'local_customreport_progress',
            "courseid = :courseid AND nodeid = 0 AND userid $insql",
            ['courseid' => $action->courseid] + $params,
            '',
            'userid, activitiestotal, activitiesdone'
        );
        $expected = self::expected_percent($action, $now);
        $overdue = self::overdue_counts((int) $action->courseid, $userids, $now);

        foreach ($userids as $userid) {
            $lastaccess = isset($last[$userid]) ? (int) $last[$userid]->lastaccess : 0;
            $percent = 0.0;
            if (isset($progress[$userid]) && $progress[$userid]->activitiestotal) {
                $percent = 100 * $progress[$userid]->activitiesdone / $progress[$userid]->activitiestotal;
            }
            $result[$userid] = [
                'noaccess' => $lastaccess === 0 || $lastaccess < $now - $days * DAYSECS,
                'lastaccess' => $lastaccess,
                'behind' => $expected !== null && $percent + self::PACE_TOLERANCE < $expected,
                'overdue' => $overdue[$userid] ?? 0,
            ];
        }
        return $result;
    }

    /**
     * Number of activities per user whose expected completion date passed without being completed.
     *
     * @param int $courseid
     * @param int[] $userids
     * @param int $now
     * @return int[] userid => count
     */
    private static function overdue_counts(int $courseid, array $userids, int $now): array {
        global $DB;
        $cms = $DB->get_fieldset_select(
            'course_modules',
            'id',
            'course = :course AND completionexpected > 0 AND completionexpected < :now AND deletioninprogress = 0 AND visible = 1',
            ['course' => $courseid, 'now' => $now]
        );
        if (!$cms) {
            return [];
        }
        [$cmsql, $cmparams] = $DB->get_in_or_equal($cms, SQL_PARAMS_NAMED, 'cm');
        $done = [];
        $rs = $DB->get_recordset_select(
            'course_modules_completion',
            "coursemoduleid $cmsql AND completionstate IN (:c1, :c2)",
            $cmparams + ['c1' => COMPLETION_COMPLETE, 'c2' => COMPLETION_COMPLETE_PASS],
            '',
            'id, coursemoduleid, userid'
        );
        foreach ($rs as $row) {
            $done[$row->userid][$row->coursemoduleid] = true;
        }
        $rs->close();
        $counts = [];
        foreach ($userids as $userid) {
            $counts[$userid] = count($cms) - count($done[$userid] ?? []);
        }
        return $counts;
    }
}
