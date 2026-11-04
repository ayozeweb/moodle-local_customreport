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
 * Training action metadata manager.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * CRUD and validation for training actions (a course or a group inside a course).
 */
class action_manager {
    /** @var int[] Allowed situations (MD B2): requested, authorised, started, finished, cancelled. */
    const SITUATIONS = [10, 20, 30, 40, 50];

    /** @var string[] Allowed modalities (MD B11.1.5). */
    const MODALITIES = ['TF', 'PR', 'PE'];

    /** @var string[] Allowed financing types (MD B8). */
    const FINANCING = ['PU', 'PR'];

    /**
     * Validate action data.
     *
     * @param \stdClass $data
     * @return string[] Map field => language string identifier of the error.
     */
    public static function validate(\stdClass $data): array {
        $errors = [];
        if (!in_array((int) ($data->situation ?? 0), self::SITUATIONS, true)) {
            $errors['situation'] = 'err_situation';
        }
        if (!in_array($data->modality ?? '', self::MODALITIES, true)) {
            $errors['modality'] = 'err_modality';
        }
        if (!in_array($data->financing ?? '', self::FINANCING, true)) {
            $errors['financing'] = 'err_financing';
        }
        // The centre code is province 80 (teleformation) plus an 8-digit sequence (MD A1.2).
        if (($data->centercode ?? '') !== '' && !preg_match('/^\d{10}$/', $data->centercode)) {
            $errors['centercode'] = 'err_centercode';
        }
        if (!empty($data->startplanned) && !empty($data->endplanned) && $data->endplanned < $data->startplanned) {
            $errors['endplanned'] = 'err_dates';
        }
        if (!empty($data->startreal) && !empty($data->endreal) && $data->endreal < $data->startreal) {
            $errors['endreal'] = 'err_dates';
        }
        return $errors;
    }

    /**
     * Whether a date should be treated as real (past) or planned (future).
     * A date older than the consultation date minus seven days is real (MD point 16).
     *
     * @param int $date Timestamp (0 = unknown).
     * @param int|null $now Consultation time.
     * @return bool True when real.
     */
    public static function is_real_date(int $date, ?int $now = null): bool {
        $now = $now ?? time();
        return $date > 0 && $date < $now - 7 * DAYSECS;
    }

    /**
     * Find the action for a course (and optionally a group).
     *
     * @param int $courseid
     * @param int $groupid
     * @return \stdClass|null
     */
    public static function get(int $courseid, int $groupid = 0): ?\stdClass {
        global $DB;
        return $DB->get_record('local_customreport_action', ['courseid' => $courseid, 'groupid' => $groupid]) ?: null;
    }

    /**
     * All actions of a course.
     *
     * @param int $courseid
     * @return \stdClass[]
     */
    public static function get_for_course(int $courseid): array {
        global $DB;
        return $DB->get_records('local_customreport_action', ['courseid' => $courseid], 'groupid');
    }

    /**
     * Insert or update an action.
     *
     * @param \stdClass $data Must contain courseid and groupid.
     * @return int Action id.
     */
    public static function save(\stdClass $data): int {
        global $DB;
        $now = time();
        $data->timemodified = $now;
        $existing = self::get((int) $data->courseid, (int) $data->groupid);
        if ($existing) {
            $data->id = $existing->id;
            $DB->update_record('local_customreport_action', $data);
            return (int) $existing->id;
        }
        unset($data->id);
        $data->timecreated = $now;
        return (int) $DB->insert_record('local_customreport_action', $data);
    }

    /**
     * Delete an action with its structure.
     *
     * @param int $actionid
     */
    public static function delete(int $actionid): void {
        global $DB;
        $DB->delete_records('local_customreport_module', ['actionid' => $actionid]);
        $DB->delete_records('local_customreport_action', ['id' => $actionid]);
    }
}
