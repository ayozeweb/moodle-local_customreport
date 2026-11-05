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
 * Administration of the follow-up web service: credentials, centre, actions and log.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_customreport\sepe\identity;
use local_customreport\sepe\security;
use local_customreport\sepe\store;

admin_externalpage_setup('local_customreport_wsadmin');

$url = new moodle_url('/local/customreport/wsadmin.php');
$endpoint = new moodle_url('/local/customreport/ws/seguimiento.php');

if (optional_param('addcred', 0, PARAM_BOOL) && confirm_sesskey()) {
    $username = required_param('username', PARAM_ALPHANUMEXT);
    $password = required_param('password', PARAM_RAW);
    if (strlen($password) >= 12) {
        security::save_credential(required_param('authority', PARAM_TEXT), $username, $password);
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($url, get_string('ws_passwordshort', 'local_customreport'), null, \core\output\notification::NOTIFY_ERROR);
}
if (($id = optional_param('delcred', 0, PARAM_INT)) && confirm_sesskey()) {
    $DB->delete_records('local_customreport_wscred', ['id' => $id]);
    redirect($url);
}
if (($wsid = optional_param('link', 0, PARAM_INT)) && confirm_sesskey()) {
    $DB->set_field('local_customreport_wsaction', 'actionid', optional_param('actionid', 0, PARAM_INT), ['id' => $wsid]);
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}
if (optional_param('importdocs', 0, PARAM_BOOL) && confirm_sesskey()) {
    $count = 0;
    foreach ($DB->get_records_select('user', "idnumber <> '' AND deleted = 0", [], '', 'id, idnumber') as $user) {
        $parsed = identity::parse($user->idnumber);
        if ($parsed) {
            $record = (object) ['userid' => $user->id, 'doctype' => $parsed['type'], 'docnumber' => $parsed['number'],
                'docletter' => $parsed['letter'], 'timemodified' => time()];
            if ($existing = $DB->get_record('local_customreport_idoc', ['userid' => $user->id])) {
                $record->id = $existing->id;
                $DB->update_record('local_customreport_idoc', $record);
            } else {
                $DB->insert_record('local_customreport_idoc', $record);
            }
            $count++;
        }
    }
    redirect(
        $url,
        get_string('ws_docsimported', 'local_customreport', $count),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('wsadmin', 'local_customreport'));
echo html_writer::tag('p', get_string('ws_endpoint', 'local_customreport') . ': ' .
    html_writer::link($endpoint, s($endpoint->out(false))) . ' (WSDL: ' .
    html_writer::link(new moodle_url($endpoint, ['wsdl' => 1]), '?wsdl') . ')');

echo $OUTPUT->heading(get_string('ws_credentials', 'local_customreport'), 3);
$table = new html_table();
$table->head = [get_string('authority', 'local_customreport'), get_string('username'), get_string('lastaccess'), ''];
foreach ($DB->get_records('local_customreport_wscred', null, 'authority') as $cred) {
    $table->data[] = [s($cred->authority), s($cred->username), $cred->lastused ? userdate($cred->lastused) : get_string('never'),
        html_writer::link(new moodle_url($url, ['delcred' => $cred->id, 'sesskey' => sesskey()]), get_string('delete'))];
}
echo html_writer::table($table);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'mb-4']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'addcred', 'value' => 1]);
foreach (['authority' => 'text', 'username' => 'text', 'password' => 'password'] as $name => $type) {
    $label = $name === 'authority' ? get_string('authority', 'local_customreport') : get_string($name);
    echo html_writer::tag('label', $label, ['for' => 'cr-' . $name, 'class' => 'mr-2']);
    echo html_writer::empty_tag('input', ['type' => $type, 'name' => $name, 'id' => 'cr-' . $name, 'required' => 'required',
        'class' => 'mr-3', 'autocomplete' => 'off']);
}
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('add')]);
echo html_writer::end_tag('form');

echo $OUTPUT->heading(get_string('ws_center', 'local_customreport'), 3);
$center = store::get_center();
if ($center) {
    echo html_writer::tag('p', s("{$center->origin} / {$center->code} - {$center->name} ({$center->email})"));
} else {
    echo $OUTPUT->notification(get_string('ws_nocenter', 'local_customreport'), \core\output\notification::NOTIFY_WARNING);
}

echo $OUTPUT->heading(get_string('ws_actions', 'local_customreport'), 3);
$actions = $DB->get_records_menu('local_customreport_action', null, 'actioncode', 'id, actioncode');
$table = new html_table();
$table->head = [get_string('actioncode', 'local_customreport'), get_string('situation', 'local_customreport'),
    get_string('startplanned', 'local_customreport'), get_string('endplanned', 'local_customreport'),
    get_string('ws_linkedaction', 'local_customreport')];
foreach ($DB->get_records('local_customreport_wsaction', null, 'code') as $ws) {
    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'link', 'value' => $ws->id]) .
        html_writer::select(
            [0 => '-'] + $actions,
            'actionid',
            $ws->actionid,
            false,
            ['aria-label' => get_string('ws_linkedaction', 'local_customreport')]
        ) .
        html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary ml-2',
            'value' => get_string('savechanges')]) . html_writer::end_tag('form');
    $table->data[] = [s($ws->code), (int) $ws->situation, $ws->startdate ? userdate($ws->startdate) : '',
        $ws->enddate ? userdate($ws->enddate) : '', $form];
}
echo html_writer::table($table);

echo $OUTPUT->heading(get_string('ws_identitydocs', 'local_customreport'), 3);
echo html_writer::tag('p', get_string('ws_identitydocs_desc', 'local_customreport', $DB->count_records('local_customreport_idoc')));
echo $OUTPUT->single_button(new moodle_url($url, ['importdocs' => 1]), get_string('ws_importdocs', 'local_customreport'), 'post');

echo $OUTPUT->heading(get_string('ws_log', 'local_customreport'), 3);
$table = new html_table();
$table->head = [get_string('time'), get_string('ws_operation', 'local_customreport'), get_string('username'),
    get_string('ws_returncode', 'local_customreport'), get_string('ws_remoteaddr', 'local_customreport')];
foreach ($DB->get_records('local_customreport_wslog', null, 'id DESC', '*', 0, 50) as $log) {
    $table->data[] = [userdate($log->timecreated), s($log->operation), s($log->username), (int) $log->returncode,
        s($log->remoteaddr)];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
