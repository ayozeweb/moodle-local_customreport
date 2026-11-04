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
 * Assign course sections to formative modules (MF) and units (UF).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_customreport\local\action_manager;
use local_customreport\local\structure_manager;

$courseid = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);
require_capability('local/customreport:manage', context_system::instance());

$action = action_manager::get($courseid, $groupid);
if (!$action) {
    redirect(new moodle_url('/local/customreport/manage.php', ['id' => $courseid, 'groupid' => $groupid]));
}

$url = new moodle_url('/local/customreport/structure.php', ['id' => $courseid, 'groupid' => $groupid]);
$PAGE->set_url($url);
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('structure', 'local_customreport'));
$PAGE->set_heading($course->fullname);

$sections = structure_manager::course_section_menu($courseid);

if (optional_param('add', 0, PARAM_BOOL) && confirm_sesskey()) {
    structure_manager::add(
        $action->id,
        required_param('type', PARAM_ALPHA),
        required_param('code', PARAM_ALPHANUMEXT),
        required_param('name', PARAM_TEXT),
        required_param('hours', PARAM_INT),
        optional_param('parentid', 0, PARAM_INT),
        optional_param_array('sectionids', [], PARAM_INT)
    );
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($delete = optional_param('delete', 0, PARAM_INT)) {
    require_sesskey();
    structure_manager::delete($delete);
    redirect($url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('structure', 'local_customreport'));

$nodes = structure_manager::get_nodes($action->id);
$table = new html_table();
$table->head = [get_string('type', 'local_customreport'), get_string('code', 'local_customreport'),
    get_string('name'), get_string('hours', 'local_customreport'), get_string('sections', 'local_customreport'), ''];
$modules = [];
foreach ($nodes as $node) {
    if ($node->type === 'MF') {
        $modules[$node->id] = $node->code . ' ' . $node->name;
    }
    $names = array_map(fn($sid) => $sections[$sid] ?? $sid, structure_manager::node_sections($node));
    $deleteurl = new moodle_url($url, ['delete' => $node->id, 'sesskey' => sesskey()]);
    $table->data[] = [s($node->type), s($node->code), format_string($node->name), (int) $node->hours,
        s(implode(', ', $names)), html_writer::link($deleteurl, get_string('delete'))];
}
echo html_writer::table($table);
foreach (structure_manager::find_duplicate_sections($action->id) as $sid) {
    echo $OUTPUT->notification(
        get_string('err_duplicatesection', 'local_customreport', $sections[$sid] ?? $sid),
        \core\output\notification::NOTIFY_WARNING
    );
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::input_hidden_params($url);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'add', 'value' => 1]);
echo html_writer::tag('label', get_string('type', 'local_customreport'), ['for' => 'cr-type']);
echo html_writer::select(['MF' => 'MF', 'UF' => 'UF', 'MP' => 'MP'], 'type', 'MF', false, ['id' => 'cr-type']);
echo html_writer::tag('label', get_string('parentmodule', 'local_customreport'), ['for' => 'cr-parent']);
echo html_writer::select($modules, 'parentid', 0, ['0' => '-'], ['id' => 'cr-parent']);
echo html_writer::tag('label', get_string('code', 'local_customreport'), ['for' => 'cr-code']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'code', 'id' => 'cr-code', 'required' => 'required']);
echo html_writer::tag('label', get_string('name'), ['for' => 'cr-name']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'id' => 'cr-name', 'required' => 'required']);
echo html_writer::tag('label', get_string('hours', 'local_customreport'), ['for' => 'cr-hours']);
echo html_writer::empty_tag('input', ['type' => 'number', 'name' => 'hours', 'id' => 'cr-hours', 'min' => 0, 'value' => 0]);
echo html_writer::tag('label', get_string('sections', 'local_customreport'), ['for' => 'cr-sections']);
echo html_writer::select($sections, 'sectionids[]', [], false, ['id' => 'cr-sections', 'multiple' => 'multiple']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
    'value' => get_string('add')]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
