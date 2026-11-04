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

    $settings->add(new admin_setting_configduration('local_customreport/sessiongap',
        get_string('sessiongap', 'local_customreport'),
        get_string('sessiongap_desc', 'local_customreport'), 30 * MINSECS, MINSECS));
    $settings->add(new admin_setting_configduration('local_customreport/sessiontail',
        get_string('sessiontail', 'local_customreport'),
        get_string('sessiontail_desc', 'local_customreport'), 5 * MINSECS, MINSECS));
    $settings->add(new admin_setting_configtext('local_customreport/inactivitydays',
        get_string('inactivitydays', 'local_customreport'),
        get_string('inactivitydays_desc', 'local_customreport'), 7, PARAM_INT));
    $settings->add(new admin_setting_configtext('local_customreport/logretentiondays',
        get_string('logretentiondays', 'local_customreport'),
        get_string('logretentiondays_desc', 'local_customreport'), 365, PARAM_INT));
}
