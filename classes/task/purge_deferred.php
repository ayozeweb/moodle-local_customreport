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
 * Scheduled task: carry out deferred privacy deletions.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\task;

use local_customreport\privacy\provider;

/**
 * Deletes the data whose erasure was deferred once the legal retention period has ended.
 */
class purge_deferred extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purge_deferred', 'local_customreport');
    }

    /**
     * Execute.
     */
    public function execute(): void {
        provider::purge_deferred();
        $deleted = \local_customreport\sepe\store::purge_log();
        mtrace("local_customreport: $deleted web service log rows deleted.");
    }
}
