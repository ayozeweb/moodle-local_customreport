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
 * Status check: session timeout for inactivity.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\check;

use core\check\check;
use core\check\result;
use local_customreport\local\compliance;

/**
 * Uses the same rule as the compliance panel.
 */
class session_timeout extends check {
    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('cc_sessiontimeout', 'local_customreport');
    }

    /**
     * Result.
     *
     * @return result
     */
    public function get_result(): result {
        $check = compliance::sessiontimeout();
        $status = [compliance::OK => result::OK, compliance::WARNING => result::WARNING, compliance::ERROR => result::ERROR];
        return new result($status[$check['status']], $check['message']);
    }
}
