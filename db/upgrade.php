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
 * Upgrade steps.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute local_customreport upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_customreport_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026110401) {
        foreach (
            ['local_customreport_profile', 'local_customreport_action',
                'local_customreport_module', 'local_customreport_cmrole'] as $table
        ) {
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file($CFG->dirroot . '/local/customreport/db/install.xml', $table);
            }
        }
        upgrade_plugin_savepoint(true, 2026110401, 'local', 'customreport');
    }

    if ($oldversion < 2026110402) {
        \local_customreport\local\profile_manager::install_builtin();
        upgrade_plugin_savepoint(true, 2026110402, 'local', 'customreport');
    }

    if ($oldversion < 2026110403) {
        foreach (
            ['local_customreport_usage', 'local_customreport_cursor', 'local_customreport_progress',
                'local_customreport_enrol', 'local_customreport_cause', 'local_customreport_deferred'] as $table
        ) {
            if (!$dbman->table_exists($table)) {
                $dbman->install_one_table_from_xmldb_file($CFG->dirroot . '/local/customreport/db/install.xml', $table);
            }
        }
        \local_customreport\local\cause_manager::install_defaults();
        upgrade_plugin_savepoint(true, 2026110403, 'local', 'customreport');
    }

    if ($oldversion < 2026110404) {
        \local_customreport\local\inspector_role::ensure();
        upgrade_plugin_savepoint(true, 2026110404, 'local', 'customreport');
    }

    return true;
}
