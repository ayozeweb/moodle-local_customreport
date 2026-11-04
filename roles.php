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
 * Assign an evaluative role to each activity of a course.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_customreport\local\cmrole_manager;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
require_capability('local/customreport:manage', context_system::instance());

$url = new moodle_url('/local/customreport/roles.php', ['id' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('activityroles', 'local_customreport'));
$PAGE->set_heading($course->fullname);

$roles = cmrole_manager::get_roles($courseid);
$options = [];
foreach (cmrole_manager::ROLES as $role) {
    $options[$role] = get_string('role_' . $role, 'local_customreport');
}

if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    foreach (optional_param_array('role', [], PARAM_ALPHANUM) as $cmid => $role) {
        if (in_array($role, cmrole_manager::ROLES, true)) {
            cmrole_manager::set_role($courseid, (int) $cmid, $role);
        }
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activityroles', 'local_customreport'));
if (cmrole_manager::customtiles_available()) {
    echo $OUTPUT->notification(
        get_string('customtilesavailable', 'local_customreport'),
        \core\output\notification::NOTIFY_INFO
    );
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);
$table = new html_table();
$table->head = [get_string('activity'), get_string('evaluativerole', 'local_customreport')];
foreach (get_fast_modinfo($course)->get_cms() as $cm) {
    $id = 'cr-role-' . $cm->id;
    $table->data[] = [
        html_writer::tag('label', format_string($cm->name), ['for' => $id]),
        html_writer::select($options, 'role[' . $cm->id . ']', $roles[$cm->id] ?? 'none', false, ['id' => $id]),
    ];
}
echo html_writer::table($table);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('savechanges')]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
