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
 * Training action configuration page for a course.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_customreport\form\action_form;
use local_customreport\local\action_manager;

$courseid = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('local/customreport:manage', context_system::instance());

$PAGE->set_url(new moodle_url('/local/customreport/manage.php', ['id' => $courseid, 'groupid' => $groupid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manageaction', 'local_customreport'));
$PAGE->set_heading($course->fullname);

$groups = [0 => get_string('wholecourse', 'local_customreport')];
foreach (groups_get_all_groups($courseid) as $group) {
    $groups[$group->id] = format_string($group->name, true, ['context' => $context]);
}

$form = new action_form(null, ['groups' => $groups]);
$existing = action_manager::get($courseid, $groupid);
if ($existing) {
    $form->set_data($existing);
} else {
    $form->set_data(['id' => $courseid, 'groupid' => $groupid]);
}

$structureurl = new moodle_url('/local/customreport/structure.php', ['id' => $courseid, 'groupid' => $groupid]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
} else if ($data = $form->get_data()) {
    $data->courseid = $courseid;
    foreach (['info', 'schedule', 'requirements', 'presencialcenters'] as $field) {
        $data->$field = $data->$field ?? '';
    }
    unset($data->id, $data->submitbutton);
    action_manager::save($data);
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageaction', 'local_customreport'));
if ($existing) {
    echo $OUTPUT->single_button($structureurl, get_string('structure', 'local_customreport'), 'get');
    echo $OUTPUT->single_button(
        new moodle_url('/local/customreport/roles.php', ['id' => $courseid]),
        get_string('activityroles', 'local_customreport'),
        'get'
    );
}
$form->display();
echo $OUTPUT->footer();
