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
 * Evaluative role of activities.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Stores and reads the evaluative role of each activity.
 */
class cmrole_manager {
    /** @var string[] Roles: process evaluation, final test 1st/2nd call, work-centre practice, not evaluable. */
    const ROLES = ['none', 'process', 'final1', 'final2', 'fct'];

    /**
     * Role of every activity of a course.
     *
     * @param int $courseid
     * @return string[] cmid => role
     */
    public static function get_roles(int $courseid): array {
        global $DB;
        return $DB->get_records_menu('local_customreport_cmrole', ['courseid' => $courseid], '', 'cmid, evalrole');
    }

    /**
     * Set the role of an activity.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $role
     * @throws \coding_exception
     */
    public static function set_role(int $courseid, int $cmid, string $role): void {
        global $DB;
        if (!in_array($role, self::ROLES, true)) {
            throw new \coding_exception('Invalid evaluative role: ' . $role);
        }
        $existing = $DB->get_record('local_customreport_cmrole', ['cmid' => $cmid]);
        $record = (object) ['courseid' => $courseid, 'cmid' => $cmid, 'evalrole' => $role, 'timemodified' => time()];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_customreport_cmrole', $record);
        } else {
            $DB->insert_record('local_customreport_cmrole', $record);
        }
    }

    /**
     * Suggest roles from format_customtiles categories when that plugin is installed.
     * Mapping: AF/AE are process, PP is process, PO is a final test (1st call). Never required.
     *
     * @param string $category Category code (AF, AE, PP, PO).
     * @return string|null Suggested role or null when unknown.
     */
    public static function suggest_from_category(string $category): ?string {
        $map = ['AF' => 'process', 'AE' => 'process', 'PP' => 'process', 'PO' => 'final1'];
        return $map[strtoupper($category)] ?? null;
    }

    /**
     * Whether the optional format_customtiles plugin is installed.
     *
     * @return bool
     */
    public static function customtiles_available(): bool {
        return \core_component::get_component_directory('format_customtiles') !== null;
    }

    /**
     * Remove roles of deleted activities.
     *
     * @param int $cmid
     */
    public static function delete_for_cm(int $cmid): void {
        global $DB;
        $DB->delete_records('local_customreport_cmrole', ['cmid' => $cmid]);
    }
}
