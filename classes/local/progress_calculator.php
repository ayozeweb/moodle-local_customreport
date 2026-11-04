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
 * Progress calculation per module/unit.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Stores completed activities and grades per user and module/unit in local_customreport_progress.
 */
class progress_calculator {
    /**
     * Recalculate the progress of every action of a course.
     *
     * @param int $courseid
     * @return int Number of progress rows written.
     */
    public static function update_course(int $courseid): int {
        global $DB;
        $written = 0;
        $modinfo = get_fast_modinfo($courseid);
        $sectioncms = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sectioncms[$section->id] = $modinfo->sections[$section->sectionnum] ?? [];
        }
        $roles = cmrole_manager::get_roles($courseid);
        $grades = self::normalised_grades($courseid);

        foreach (action_manager::get_for_course($courseid) as $action) {
            $users = self::learners($courseid, (int) $action->groupid);
            $nodes = structure_manager::get_nodes($action->id);
            $groups = [0 => []];
            foreach ($nodes as $node) {
                $groups[$node->id] = [];
                foreach (structure_manager::node_sections($node) as $sectionid) {
                    $groups[$node->id] = array_merge($groups[$node->id], $sectioncms[$sectionid] ?? []);
                }
                $groups[0] = array_merge($groups[0], $groups[$node->id]);
            }
            if (!$nodes) {
                $groups[0] = array_keys($modinfo->cms);
            }
            foreach ($groups as $nodeid => $cmids) {
                $cmids = array_values(array_unique(array_map('intval', $cmids)));
                $tracked = array_values(array_filter($cmids, function ($cmid) use ($modinfo) {
                    $cm = $modinfo->cms[$cmid] ?? null;
                    return $cm && $cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress;
                }));
                $states = self::completion_states($tracked);
                foreach ($users as $userid) {
                    $row = self::row($courseid, $userid, $nodeid, $tracked, $states, $cmids, $roles, $grades);
                    self::save($row);
                    $written++;
                }
            }
        }
        return $written;
    }

    /**
     * Build the progress row of a user and node.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $nodeid
     * @param int[] $tracked Activities with completion tracking.
     * @param array $states userid => cmid => state.
     * @param int[] $cmids All activities of the node.
     * @param string[] $roles cmid => evaluative role.
     * @param array $grades userid => cmid => grade on a 0-10 scale.
     * @return \stdClass
     */
    private static function row(
        int $courseid,
        int $userid,
        int $nodeid,
        array $tracked,
        array $states,
        array $cmids,
        array $roles,
        array $grades
    ): \stdClass {
        $done = 0;
        foreach ($tracked as $cmid) {
            $state = $states[$userid][$cmid] ?? COMPLETION_INCOMPLETE;
            if ($state == COMPLETION_COMPLETE || $state == COMPLETION_COMPLETE_PASS) {
                $done++;
            }
        }
        $process = [];
        $final1 = [];
        $final2 = [];
        foreach ($cmids as $cmid) {
            if (!isset($grades[$userid][$cmid])) {
                continue;
            }
            $role = $roles[$cmid] ?? 'none';
            if ($role === 'process') {
                $process[] = $grades[$userid][$cmid];
            } else if ($role === 'final1') {
                $final1[] = $grades[$userid][$cmid];
            } else if ($role === 'final2') {
                $final2[] = $grades[$userid][$cmid];
            }
        }
        return (object) [
            'courseid' => $courseid, 'userid' => $userid, 'nodeid' => $nodeid,
            'activitiestotal' => count($tracked), 'activitiesdone' => $done,
            'processavg' => $process ? array_sum($process) / count($process) : null,
            'final1' => $final1 ? max($final1) : null,
            'final2' => $final2 ? max($final2) : null,
            'timemodified' => time(),
        ];
    }

    /**
     * Insert or update a progress row.
     *
     * @param \stdClass $row
     */
    private static function save(\stdClass $row): void {
        global $DB;
        $existing = $DB->get_record(
            'local_customreport_progress',
            ['courseid' => $row->courseid, 'userid' => $row->userid, 'nodeid' => $row->nodeid],
            'id'
        );
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_customreport_progress', $row);
        } else {
            $DB->insert_record('local_customreport_progress', $row);
        }
    }

    /**
     * Ids of the active learners of a course (or of a group).
     *
     * @param int $courseid
     * @param int $groupid
     * @return int[]
     */
    private static function learners(int $courseid, int $groupid): array {
        $context = \context_course::instance($courseid);
        return array_map('intval', array_keys(get_enrolled_users(
            $context,
            'moodle/course:isincompletionreports',
            $groupid,
            'u.id',
            null,
            0,
            0,
            true
        )));
    }

    /**
     * Completion states of the given activities.
     *
     * @param int[] $cmids
     * @return array userid => cmid => state
     */
    private static function completion_states(array $cmids): array {
        global $DB;
        $states = [];
        if (!$cmids) {
            return $states;
        }
        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $rs = $DB->get_recordset_select(
            'course_modules_completion',
            "coursemoduleid $insql",
            $params,
            '',
            'id, coursemoduleid, userid, completionstate'
        );
        foreach ($rs as $row) {
            $states[$row->userid][$row->coursemoduleid] = (int) $row->completionstate;
        }
        $rs->close();
        return $states;
    }

    /**
     * Final grades of the course activities normalised to a 0-10 scale.
     *
     * @param int $courseid
     * @return array userid => cmid => grade
     */
    private static function normalised_grades(int $courseid): array {
        global $DB;
        $sql = "SELECT gg.id, gg.userid, cm.id AS cmid, gg.finalgrade, gi.grademin, gi.grademax
                  FROM {grade_items} gi
                  JOIN {modules} m ON m.name = gi.itemmodule
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = gi.iteminstance AND cm.course = gi.courseid
                  JOIN {grade_grades} gg ON gg.itemid = gi.id
                 WHERE gi.courseid = :courseid AND gi.itemtype = 'mod' AND gg.finalgrade IS NOT NULL
                       AND gi.grademax > gi.grademin";
        $grades = [];
        foreach ($DB->get_recordset_sql($sql, ['courseid' => $courseid]) as $row) {
            $value = ($row->finalgrade - $row->grademin) / ($row->grademax - $row->grademin) * 10;
            $grades[$row->userid][$row->cmid] = max(0, min(10, $value));
        }
        return $grades;
    }
}
