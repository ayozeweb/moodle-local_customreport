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
 * Compliance panel (admin).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_customreport\local\compliance;

admin_externalpage_setup('local_customreport_compliance');

$checks = compliance::run();
$base = get_string('requisitosurl', 'local_customreport');
$icons = [compliance::OK => 'text-success', compliance::WARNING => 'text-warning', compliance::ERROR => 'text-danger'];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('compliancepanel', 'local_customreport'));
$overall = compliance::overall($checks);
$types = [compliance::OK => \core\output\notification::NOTIFY_SUCCESS,
    compliance::WARNING => \core\output\notification::NOTIFY_WARNING,
    compliance::ERROR => \core\output\notification::NOTIFY_ERROR];
$type = $types[$overall];
echo $OUTPUT->notification(get_string('cc_overall_' . $overall, 'local_customreport'), $type);

$table = new html_table();
$table->head = [get_string('cc_requirement', 'local_customreport'), get_string('cc_status', 'local_customreport'),
    get_string('cc_detail', 'local_customreport'), get_string('cc_reference', 'local_customreport')];
foreach ($checks as $check) {
    $label = get_string('cc_status_' . $check['status'], 'local_customreport');
    $status = html_writer::span($label, $icons[$check['status']] . ' font-weight-bold');
    $detail = s($check['message']);
    if ($check['url']) {
        $detail .= ' ' . html_writer::link($check['url'], get_string('cc_fix', 'local_customreport'));
    }
    $table->data[] = [get_string('cc_' . $check['id'], 'local_customreport'), $status, $detail,
        html_writer::link($base, $check['ref'], ['target' => '_blank', 'rel' => 'noopener'])];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
