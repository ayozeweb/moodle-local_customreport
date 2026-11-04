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
 * Status check: the inspector role is read-only.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\check;

use core\check\check;
use core\check\result;
use local_customreport\local\inspector_role;

/**
 * Fails when the competent administration role has write capabilities or dangerous risks.
 */
class inspector_readonly extends check {
    /**
     * Name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check_inspector', 'local_customreport');
    }

    /**
     * Reference to the requirement in docs/REQUISITOS.md.
     *
     * @return string
     */
    public function get_ref(): string {
        return 'R-2.3.2';
    }

    /**
     * Result.
     *
     * @return result
     */
    public function get_result(): result {
        global $DB;
        if (!$DB->record_exists('role', ['shortname' => inspector_role::SHORTNAME])) {
            return new result(result::WARNING, get_string('check_inspector_missing', 'local_customreport'));
        }
        $violations = inspector_role::find_violations();
        if ($violations) {
            return new result(result::ERROR, get_string(
                'check_inspector_bad',
                'local_customreport',
                implode(', ', array_keys($violations))
            ));
        }
        return new result(result::OK, get_string('check_inspector_ok', 'local_customreport'));
    }
}
