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
 * Event observers: keep the plugin tables consistent when Moodle deletes data.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport;

use local_customreport\local\action_manager;
use local_customreport\local\structure_manager;
use local_customreport\privacy\provider;

/**
 * Configuration is deleted at once. Follow-up data goes through the privacy provider, which defers the
 * deletion while the legal control period lasts, exactly as for a data subject request.
 */
class observer {
    /**
     * A course was deleted.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $courseid = (int) $event->objectid;
        $DB->delete_records('local_customreport_cmrole', ['courseid' => $courseid]);
        provider::erase($courseid, self::users_with_data($courseid));
        self::delete_actions_if_unused($courseid);
    }

    /**
     * An activity was deleted.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        $DB->delete_records('local_customreport_cmrole', ['cmid' => $event->objectid]);
    }

    /**
     * A section was deleted: remove it from the modules/units it was assigned to.
     *
     * @param \core\event\course_section_deleted $event
     */
    public static function course_section_deleted(\core\event\course_section_deleted $event): void {
        $sectionid = (int) $event->objectid;
        foreach (action_manager::get_for_course((int) $event->courseid) as $action) {
            foreach (structure_manager::get_nodes((int) $action->id) as $node) {
                $sections = structure_manager::node_sections($node);
                if (in_array($sectionid, $sections, true)) {
                    structure_manager::set_sections((int) $node->id, array_diff($sections, [$sectionid]));
                }
            }
        }
    }

    /**
     * A user was deleted.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $userid = (int) $event->objectid;
        $courses = [];
        foreach (provider::TABLES as $table) {
            $courses += $DB->get_records_menu($table, ['userid' => $userid], '', 'DISTINCT courseid, courseid AS c');
        }
        foreach (array_keys($courses) as $courseid) {
            provider::erase((int) $courseid, [$userid]);
        }
        $DB->delete_records('local_customreport_cursor', ['userid' => $userid]);
    }

    /**
     * Users with follow-up data in a course.
     *
     * @param int $courseid
     * @return int[]
     */
    private static function users_with_data(int $courseid): array {
        global $DB;
        $users = [];
        foreach (provider::TABLES as $table) {
            $users += $DB->get_records_menu($table, ['courseid' => $courseid], '', 'DISTINCT userid, userid AS u');
        }
        return array_map('intval', array_keys($users));
    }

    /**
     * Delete the actions of a deleted course once no deferred deletion depends on them (the retention rule reads the
     * action dates).
     *
     * @param int $courseid
     */
    public static function delete_actions_if_unused(int $courseid): void {
        global $DB;
        if (
            $DB->record_exists('course', ['id' => $courseid])
                || $DB->record_exists('local_customreport_deferred', ['courseid' => $courseid])
        ) {
            return;
        }
        foreach (action_manager::get_for_course($courseid) as $action) {
            $DB->set_field('local_customreport_wsaction', 'actionid', 0, ['actionid' => $action->id]);
            action_manager::delete((int) $action->id);
        }
    }
}
