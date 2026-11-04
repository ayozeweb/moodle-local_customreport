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
 * Follow-up report of a learner per module/unit (print and download).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_customreport\local\access;
use local_customreport\local\action_manager;
use local_customreport\local\branding;
use local_customreport\local\structure_manager;
use local_customreport\report\followup;
use local_customreport\report\presenter;

$courseid = required_param('id', PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);
$format = optional_param('format', 'html', PARAM_ALPHA);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
$action = action_manager::get($courseid, $groupid);
if (!$action) {
    throw new moodle_exception('noaction', 'local_customreport');
}
if ($userid === (int) $USER->id) {
    require_capability('local/customreport:viewownreport', $context);
} else if (!access::can_view_learner($action, $userid)) {
    throw new required_capability_exception($context, 'local/customreport:viewgroupreports', 'nopermissions', '');
}

$data = followup::build($action, [$userid]);
$learner = $data['learners'][0] ?? null;
$rows = [];
$times = [];
foreach (
    $DB->get_records_sql("SELECT cmid, SUM(seconds) AS seconds FROM {local_customreport_usage}
        WHERE courseid = ? AND userid = ? GROUP BY cmid", [$courseid, $userid]) as $r
) {
    $times[(int) $r->cmid] = (int) $r->seconds;
}
$nodes = structure_manager::get_nodes($action->id);
$nodecms = followup::node_cmids($action, $nodes);
foreach ($nodes as $node) {
    $d = $learner['nodes'][$node->id] ?? null;
    $seconds = 0;
    foreach ($nodecms[$node->id] ?? [] as $cmid) {
        $seconds += $times[$cmid] ?? 0;
    }
    $passed = $d && ($node->type === 'MP' ? ($d['total'] > 0 && $d['done'] >= $d['total']) : !empty($d['passed']));
    $rows[] = [
        'code' => $node->code, 'name' => format_string($node->name), 'hours' => (int) $node->hours,
        'done' => $d ? $d['done'] . '/' . $d['total'] : '-', 'percent' => $d ? $d['percent'] : '-',
        'process' => presenter::grade($d['processavg'] ?? null), 'final' => presenter::grade($d['final1'] ?? null),
        'score' => presenter::grade($d['score'] ?? null), 'call' => $d['call'] ?? '-',
        'time' => presenter::duration($seconds),
        'result' => get_string($passed ? 'passed' : 'notpassed', 'local_customreport'),
    ];
}

$user = core_user::get_user($userid, '*', MUST_EXIST);
$contextdata = ['logos' => branding::logos(), 'fullname' => fullname($user),
    'actionname' => format_string($action->name ?: $action->actioncode),
    'generated' => userdate(time(), get_string('strftimedatetimeshort', 'langconfig')), 'rows' => $rows];

if ($format === 'csv') {
    $out = fopen('php://temp', 'r+');
    $header = ['code', 'name', 'hours', 'done', 'percent', 'processavg', 'final', 'score', 'call', 'time', 'result'];
    fputcsv($out, $header, ';', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, array_values($row), ';', '"', '\\');
    }
    rewind($out);
    send_file(
        "\xEF\xBB\xBF" . stream_get_contents($out),
        clean_filename('student-' . $userid . '.csv'),
        0,
        0,
        true,
        true,
        'text/csv; charset=utf-8'
    );
}

$print = $format === 'print';
$params = ['id' => $courseid, 'groupid' => $groupid, 'userid' => $userid];
$PAGE->set_url(new moodle_url('/local/customreport/student.php', $params + ($print ? ['format' => 'print'] : [])));
$PAGE->set_context($context);
$PAGE->set_pagelayout($print ? 'print' : 'report');
$PAGE->set_title(get_string('studentreport', 'local_customreport'));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
if (!$print) {
    echo $OUTPUT->heading(get_string('studentreport', 'local_customreport'));
    echo html_writer::alist([
        html_writer::link(
            new moodle_url('/local/customreport/student.php', $params + ['format' => 'print']),
            get_string('printview', 'local_customreport'),
            ['target' => '_blank', 'rel' => 'noopener']
        ),
        html_writer::link(
            new moodle_url('/local/customreport/student.php', $params + ['format' => 'csv']),
            get_string('download', 'local_customreport')
        ),
    ], ['class' => 'list-inline'], 'ul');
}
echo $OUTPUT->render_from_template('local_customreport/student', $contextdata);
echo $OUTPUT->footer();
