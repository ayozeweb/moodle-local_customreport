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
 * Library callbacks.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add the follow-up configuration link to the course navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_customreport_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if (has_capability('local/customreport:manage', context_system::instance())) {
        $navigation->add(
            get_string('manageaction', 'local_customreport'),
            new moodle_url('/local/customreport/manage.php', ['id' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'local_customreport_manage'
        );
    }
}
