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
 * Tutor panel: access, pace and overdue indicators.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_customreport\local\access;
use local_customreport\local\action_manager;
use local_customreport\local\indicators;

$courseid = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
if (!access::can_view_groups($context)) {
    require_capability('local/customreport:viewgroupreports', $context);
}
$action = action_manager::get($courseid, $groupid);
if (!$action) {
    throw new moodle_exception('noaction', 'local_customreport');
}

$PAGE->set_url(new moodle_url('/local/customreport/tutor.php', ['id' => $courseid, 'groupid' => $groupid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('tutorpanel', 'local_customreport'));
$PAGE->set_heading($course->fullname);

$userids = access::visible_learners($action);
$ind = indicators::for_learners($action, $userids);
$users = $userids ? $DB->get_records_list('user', 'id', $userids, 'lastname, firstname') : [];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tutorpanel', 'local_customreport'));
$noaccess = count(array_filter($ind, fn($i) => $i['noaccess']));
$behind = count(array_filter($ind, fn($i) => $i['behind']));
$overdue = count(array_filter($ind, fn($i) => $i['overdue'] > 0));
echo html_writer::tag('p', get_string(
    'tutorsummary',
    'local_customreport',
    (object) ['noaccess' => $noaccess, 'behind' => $behind, 'overdue' => $overdue,
    'days' => (int) (get_config(
        'local_customreport',
        'inactivitydays'
    ) ?: 7)]
));
$table = new html_table();
$table->head = [get_string('col_fullname', 'local_customreport'), get_string('col_lastaccess', 'local_customreport'),
    get_string('noaccess', 'local_customreport'), get_string('behindpace', 'local_customreport'),
    get_string('overdueactivities', 'local_customreport'), ''];
$yes = get_string('yes');
foreach ($users as $user) {
    $i = $ind[$user->id];
    $link = html_writer::link(new moodle_url(
        '/local/customreport/student.php',
        ['id' => $courseid, 'groupid' => $groupid, 'userid' => $user->id]
    ), get_string('studentreport', 'local_customreport'));
    $table->data[] = [fullname($user), $i['lastaccess'] ? userdate($i['lastaccess']) : get_string('never'),
        $i['noaccess'] ? $yes : '', $i['behind'] ? $yes : '', $i['overdue'] ?: '', $link];
}
echo html_writer::table($table);
echo html_writer::link(
    new moodle_url('/local/customreport/report.php', ['id' => $courseid, 'groupid' => $groupid]),
    get_string('followupreport', 'local_customreport')
);
echo $OUTPUT->footer();
