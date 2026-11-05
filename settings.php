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
 * Admin settings.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_customreport', get_string('pluginname', 'local_customreport'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configduration(
        'local_customreport/sessiongap',
        get_string('sessiongap', 'local_customreport'),
        get_string('sessiongap_desc', 'local_customreport'),
        30 * MINSECS,
        MINSECS
    ));
    $settings->add(new admin_setting_configduration(
        'local_customreport/sessiontail',
        get_string('sessiontail', 'local_customreport'),
        get_string('sessiontail_desc', 'local_customreport'),
        5 * MINSECS,
        MINSECS
    ));
    $settings->add(new admin_setting_configtext(
        'local_customreport/inactivitydays',
        get_string('inactivitydays', 'local_customreport'),
        get_string('inactivitydays_desc', 'local_customreport'),
        7,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'local_customreport/logretentiondays',
        get_string('logretentiondays', 'local_customreport'),
        get_string('logretentiondays_desc', 'local_customreport'),
        365,
        PARAM_INT
    ));

    // Follow-up web service.
    $settings->add(new admin_setting_configcheckbox(
        'local_customreport/ws_enabled',
        get_string('ws_enabled', 'local_customreport'),
        get_string('ws_enabled_desc', 'local_customreport'),
        1
    ));
    $settings->add(new admin_setting_configcheckbox(
        'local_customreport/ws_allowhttp',
        get_string('ws_allowhttp', 'local_customreport'),
        get_string('ws_allowhttp_desc', 'local_customreport'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'local_customreport/ws_keycompetence',
        get_string('ws_keycompetence', 'local_customreport'),
        get_string('ws_keycompetence_desc', 'local_customreport'),
        '01',
        '/^0[1-3]$/'
    ));

    // Institutional images for report headers and prints (the plugin ships no official logos).
    for ($i = 1; $i <= \local_customreport\local\branding::SLOTS; $i++) {
        $settings->add(new admin_setting_configstoredfile(
            'local_customreport/logo' . $i,
            get_string('logo', 'local_customreport', $i),
            get_string('logo_desc', 'local_customreport'),
            'logo' . $i,
            0,
            ['accepted_types' => ['.png', '.jpg', '.jpeg', '.svg', '.gif'], 'maxfiles' => 1]
        ));
        $settings->add(new admin_setting_configtext(
            'local_customreport/logoalt' . $i,
            get_string('logoalt', 'local_customreport', $i),
            '',
            '',
            PARAM_TEXT
        ));
    }

    $pages = [
        'local_customreport_presets' => ['presets', 'presets.php'],
        'local_customreport_profilefields' => ['profilefieldswizard', 'profilefields.php'],
        'local_customreport_wsadmin' => ['wsadmin', 'wsadmin.php'],
        'local_customreport_compliance' => ['compliancepanel', 'compliance.php'],
    ];
    foreach ($pages as $name => [$string, $file]) {
        $ADMIN->add('localplugins', new admin_externalpage(
            $name,
            get_string($string, 'local_customreport'),
            new moodle_url('/local/customreport/' . $file),
            'local/customreport:manage'
        ));
    }
}
