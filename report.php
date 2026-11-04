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
 * Follow-up report of a training action (statistics, charts, print, CSV and XML).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_customreport\export\csv;
use local_customreport\export\xml;
use local_customreport\local\access;
use local_customreport\local\action_manager;
use local_customreport\local\structure_manager;
use local_customreport\report\followup;
use local_customreport\report\presenter;

$courseid = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$nodeid = optional_param('nodeid', 0, PARAM_INT);
$fromstr = optional_param('from', '', PARAM_RAW_TRIMMED);
$tostr = optional_param('to', '', PARAM_RAW_TRIMMED);
$format = optional_param('format', 'html', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
$action = action_manager::get($courseid, $groupid);
if (!$action) {
    throw new moodle_exception('noaction', 'local_customreport');
}
if (!access::can_view_groups($context)) {
    // Learners only see their own report.
    redirect(new moodle_url('/local/customreport/student.php', ['id' => $courseid, 'groupid' => $groupid]));
}

$parse = function (string $value, bool $endofday): int {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return 0;
    }
    $date = new DateTimeImmutable($value, core_date::get_server_timezone_object());
    return ($endofday ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0))->getTimestamp();
};
$from = $parse($fromstr, false);
$to = $parse($tostr, true);

$data = followup::build($action, access::visible_learners($action), $from, $to, $nodeid);
$params = ['id' => $courseid, 'groupid' => $groupid, 'nodeid' => $nodeid, 'from' => $fromstr, 'to' => $tostr];

if (in_array($format, ['csv', 'xml'], true)) {
    if (!access::can_export($context)) {
        throw new required_capability_exception($context, 'local/customreport:exportreports', 'nopermissions', '');
    }
    $filename = clean_filename('followup-' . ($action->actioncode ?: $courseid) . '-' . date('Ymd')) . '.' . $format;
    $content = $format === 'csv' ? csv::render($data) : xml::render($data);
    send_file($content, $filename, 0, 0, true, true, $format === 'csv' ? 'text/csv; charset=utf-8' : 'application/xml');
}

$print = $format === 'print';
$PAGE->set_url(new moodle_url('/local/customreport/report.php', $params + ($print ? ['format' => 'print'] : [])));
$PAGE->set_context($context);
$PAGE->set_pagelayout($print ? 'print' : 'report');
$PAGE->set_title(get_string('followupreport', 'local_customreport'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
if (!$print) {
    echo $OUTPUT->heading(get_string('followupreport', 'local_customreport'));
    $nodemenu = [0 => get_string('wholeaction', 'local_customreport')];
    foreach (structure_manager::get_nodes($action->id) as $node) {
        $nodemenu[$node->id] = $node->code . ' ' . $node->name;
    }
    $formurl = new moodle_url('/local/customreport/report.php', ['id' => $courseid, 'groupid' => $groupid]);
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $formurl->out(false), 'class' => 'form-inline mb-3']);
    echo html_writer::input_hidden_params($formurl);
    echo html_writer::tag('label', get_string('modulefilter', 'local_customreport'), ['for' => 'cr-node', 'class' => 'mr-2']);
    echo html_writer::select($nodemenu, 'nodeid', $nodeid, false, ['id' => 'cr-node', 'class' => 'mr-3']);
    echo html_writer::tag('label', get_string('datefrom', 'local_customreport'), ['for' => 'cr-from', 'class' => 'mr-2']);
    echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'from', 'id' => 'cr-from', 'value' => $fromstr,
        'class' => 'mr-3']);
    echo html_writer::tag('label', get_string('dateto', 'local_customreport'), ['for' => 'cr-to', 'class' => 'mr-2']);
    echo html_writer::empty_tag('input', ['type' => 'date', 'name' => 'to', 'id' => 'cr-to', 'value' => $tostr, 'class' => 'mr-3']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
        'value' => get_string('apply', 'local_customreport')]);
    echo html_writer::end_tag('form');

    $links = [html_writer::link(
        new moodle_url('/local/customreport/report.php', $params + ['format' => 'print']),
        get_string('printview', 'local_customreport'),
        ['target' => '_blank', 'rel' => 'noopener']
    )];
    if (access::can_export($context)) {
        $links[] = html_writer::link(new moodle_url('/local/customreport/report.php', $params + ['format' => 'csv']), 'CSV');
        $links[] = html_writer::link(new moodle_url('/local/customreport/report.php', $params + ['format' => 'xml']), 'XML');
    }
    $links[] = html_writer::link(
        new moodle_url('/local/customreport/tutor.php', ['id' => $courseid, 'groupid' => $groupid]),
        get_string('tutorpanel', 'local_customreport')
    );
    echo html_writer::alist($links, ['class' => 'list-inline'], 'ul');
}
echo $OUTPUT->render_from_template('local_customreport/report', presenter::context($data, $OUTPUT, !$print || true));
echo $OUTPUT->footer();
