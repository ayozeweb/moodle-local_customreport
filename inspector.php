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
 * Start page for the competent administration: list of training actions with direct links to the reports.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/customreport:viewallreports', $context);

$PAGE->set_url(new moodle_url('/local/customreport/inspector.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('inspectorhome', 'local_customreport'));
$PAGE->set_heading(get_string('inspectorhome', 'local_customreport'));

$actions = $DB->get_records_sql("SELECT a.*, c.fullname AS coursename
                                   FROM {local_customreport_action} a
                                   JOIN {course} c ON c.id = a.courseid
                               ORDER BY a.actioncode, c.fullname");
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('inspectorhome', 'local_customreport'));
$table = new html_table();
$table->head = [get_string('actioncode', 'local_customreport'), get_string('actionname', 'local_customreport'),
    get_string('course'), get_string('situation', 'local_customreport'), get_string('startplanned', 'local_customreport'),
    get_string('endplanned', 'local_customreport'), ''];
foreach ($actions as $a) {
    $link = html_writer::link(
        new moodle_url('/local/customreport/report.php', ['id' => $a->courseid, 'groupid' => $a->groupid]),
        get_string('followupreport', 'local_customreport')
    );
    $table->data[] = [s($a->actioncode), format_string($a->name), format_string($a->coursename),
        get_string('situation_' . $a->situation, 'local_customreport'),
        $a->startplanned ? userdate($a->startplanned, get_string('strftimedate', 'langconfig')) : '',
        $a->endplanned ? userdate($a->endplanned, get_string('strftimedate', 'langconfig')) : '', $link];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
