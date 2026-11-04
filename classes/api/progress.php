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
 * Public progress API.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\api;

use local_customreport\local\action_manager;
use local_customreport\local\structure_manager;

/**
 * Progress per module/unit for course formats (for example format_customtiles).
 * Everything is read from pre-aggregated tables, nothing is recalculated here.
 */
class progress {
    /**
     * Whether the current user may see the progress of a user in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function can_view(int $courseid, int $userid): bool {
        global $USER;
        $context = \context_course::instance($courseid);
        if ((int) $USER->id === $userid) {
            return has_capability('local/customreport:viewownreport', $context);
        }
        return has_capability('local/customreport:viewgroupreports', $context)
            || has_capability('local/customreport:viewallreports', \context_system::instance());
    }

    /**
     * Progress of a user in every module/unit of the action of a course.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $groupid Action scope (0 = whole course).
     * @return array nodeid => ['nodeid', 'type', 'code', 'name', 'hours', 'total', 'done', 'percent', 'processavg',
     *               'final1', 'final2']; nodeid 0 is the whole action.
     * @throws \required_capability_exception When the current user cannot see this user's data.
     */
    public static function get_user_progress(int $courseid, int $userid, int $groupid = 0): array {
        global $DB;
        if (!self::can_view($courseid, $userid)) {
            throw new \required_capability_exception(
                \context_course::instance($courseid),
                'local/customreport:viewownreport',
                'nopermissions',
                ''
            );
        }
        $action = action_manager::get($courseid, $groupid);
        if (!$action) {
            return [];
        }
        $rows = $DB->get_records('local_customreport_progress', ['courseid' => $courseid, 'userid' => $userid], '', 'nodeid, *');
        $result = [];
        $total = $rows[0] ?? null;
        if ($total) {
            $result[0] = self::format(
                0,
                'action',
                (string) $action->actioncode,
                (string) $action->name,
                (int) $action->hours,
                $total
            );
        }
        foreach (structure_manager::get_nodes($action->id) as $node) {
            if (isset($rows[$node->id])) {
                $result[(int) $node->id] = self::format(
                    (int) $node->id,
                    $node->type,
                    $node->code,
                    $node->name,
                    (int) $node->hours,
                    $rows[$node->id]
                );
            }
        }
        return $result;
    }

    /**
     * Format a progress row.
     *
     * @param int $nodeid
     * @param string $type
     * @param string $code
     * @param string $name
     * @param int $hours
     * @param \stdClass $row
     * @return array
     */
    private static function format(int $nodeid, string $type, string $code, string $name, int $hours, \stdClass $row): array {
        $total = (int) $row->activitiestotal;
        return [
            'nodeid' => $nodeid, 'type' => $type, 'code' => $code, 'name' => $name, 'hours' => $hours,
            'total' => $total, 'done' => (int) $row->activitiesdone,
            'percent' => $total ? round(100 * $row->activitiesdone / $total, 1) : 0.0,
            'processavg' => $row->processavg === null ? null : (float) $row->processavg,
            'final1' => $row->final1 === null ? null : (float) $row->final1,
            'final2' => $row->final2 === null ? null : (float) $row->final2,
        ];
    }
}
