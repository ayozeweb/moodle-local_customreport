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
 * Legal retention of follow-up data.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Follow-up data must be kept during the control period: from the planned start until 90 natural days
 * after the end of the action (Order TMS/369/2019, Annex V; data model point 13.c).
 */
class retention {
    /** @var int Days after the end of the action during which follow-up data is retained. */
    const DAYS_AFTER_END = 90;

    /**
     * Whether the follow-up data of a course must still be retained.
     *
     * @param int $courseid
     * @param int|null $now
     * @return bool
     */
    public static function must_retain(int $courseid, ?int $now = null): bool {
        $now = $now ?? time();
        $actions = action_manager::get_for_course($courseid);
        foreach ($actions as $action) {
            $end = max((int) $action->endreal, (int) $action->endplanned);
            if ($end === 0 || $now <= $end + self::DAYS_AFTER_END * DAYSECS) {
                return true;
            }
        }
        return false;
    }
}
