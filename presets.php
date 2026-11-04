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
 * Import and export regulatory profiles (admin).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_customreport\local\profile_manager;

admin_externalpage_setup('local_customreport_presets');

if ($export = optional_param('export', '', PARAM_ALPHANUMEXT)) {
    require_sesskey();
    $json = profile_manager::export_json($export);
    send_file($json, $export . '.json', 0, 0, true, true, 'application/json');
}

$url = new moodle_url('/local/customreport/presets.php');
$message = null;
if (optional_param('import', 0, PARAM_BOOL) && confirm_sesskey()) {
    $json = required_param('json', PARAM_RAW);
    try {
        $record = profile_manager::import_json($json);
        redirect(
            $url,
            get_string('profile_imported', 'local_customreport', s($record->shortname)),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        $message = $OUTPUT->notification($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('presets', 'local_customreport'));
echo $message;
$table = new html_table();
$table->head = [get_string('name'), get_string('shortname'), get_string('version', 'local_customreport'), ''];
foreach ($DB->get_records('local_customreport_profile', null, 'name') as $profile) {
    $link = html_writer::link(
        new moodle_url($url, ['export' => $profile->shortname, 'sesskey' => sesskey()]),
        get_string('export', 'local_customreport')
    );
    $table->data[] = [s($profile->name), s($profile->shortname), (int) $profile->version, $link];
}
echo html_writer::table($table);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'import', 'value' => 1]);
echo html_writer::tag('label', get_string('importjson', 'local_customreport'), ['for' => 'cr-json']);
echo html_writer::tag('textarea', '', ['name' => 'json', 'id' => 'cr-json', 'rows' => 12, 'class' => 'form-control']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary mt-2',
    'value' => get_string('import', 'local_customreport')]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
