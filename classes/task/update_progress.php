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
 * Scheduled task: progress update.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\task;

use local_customreport\local\progress_calculator;

/**
 * Recalculates progress for every course with a training action.
 */
class update_progress extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_update_progress', 'local_customreport');
    }

    /**
     * Execute.
     */
    public function execute(): void {
        global $DB;
        foreach ($DB->get_fieldset_select('local_customreport_action', 'DISTINCT courseid', '1=1') as $courseid) {
            if ($DB->record_exists('course', ['id' => $courseid])) {
                $rows = progress_calculator::update_course((int) $courseid);
                mtrace("local_customreport: course $courseid, $rows progress rows.");
            }
        }
    }
}
