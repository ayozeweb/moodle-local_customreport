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

    if ($oldversion < 2026110405) {
        $table = new xmldb_table('local_customreport_action');
        $field = new xmldb_field('centercode', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, '', 'actioncode');
        $dbman->change_field_precision($table, $field);
        foreach (
            ['local_customreport_wscenter', 'local_customreport_wsaction', 'local_customreport_wscred',
                'local_customreport_wslog', 'local_customreport_idoc'] as $name
        ) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file($CFG->dirroot . '/local/customreport/db/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026110405, 'local', 'customreport');
    }

    if ($oldversion < 2026110406) {
        if (!$dbman->table_exists('local_customreport_slot')) {
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/local/customreport/db/install.xml',
                'local_customreport_slot'
            );
        }
        \local_customreport\local\cause_manager::install_defaults();
        upgrade_plugin_savepoint(true, 2026110406, 'local', 'customreport');
    }

    if ($oldversion < 2026110407) {
        // CHAR NOT NULL columns must not declare an empty default (XMLDB rule); drop it.
        $fields = [
            'local_customreport_action' => ['authority', 'actioncode', 'centercode', 'areacode', 'specialtycode', 'name',
                'contact'],
            'local_customreport_module' => ['code', 'name'],
            'local_customreport_usage' => ['component'],
            'local_customreport_cursor' => ['lastcomponent'],
            'local_customreport_wslog' => ['operation', 'username', 'remoteaddr'],
        ];
        $xmldb = new xmldb_file($CFG->dirroot . '/local/customreport/db/install.xml');
        $xmldb->loadXMLStructure();
        $structure = $xmldb->getStructure();
        foreach ($fields as $tablename => $names) {
            $xmltable = $structure->getTable($tablename);
            $table = new xmldb_table($tablename);
            // A column inside a key cannot be altered: drop the keys that use it and add them back afterwards.
            $keys = array_filter($xmltable->getKeys(), fn($key) => $key->getType() !== XMLDB_KEY_PRIMARY
                && array_intersect($key->getFields(), $names));
            foreach ($keys as $key) {
                $dbman->drop_key($table, $key);
            }
            foreach ($names as $name) {
                $dbman->change_field_default($table, $xmltable->getField($name));
            }
            foreach ($keys as $key) {
                $dbman->add_key($table, $key);
            }
        }
        upgrade_plugin_savepoint(true, 2026110407, 'local', 'customreport');
    }

    if ($oldversion < 2026110409) {
        // The default regulatory profile is the neutral one; SEPE codes now live in the SEPE profile (catalogues).
        $table = new xmldb_table('local_customreport_action');
        $field = new xmldb_field('profile', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'generico', 'groupid');
        $dbman->change_field_default($table, $field);
        \local_customreport\local\profile_manager::install_builtin();
        upgrade_plugin_savepoint(true, 2026110409, 'local', 'customreport');
    }

    if ($oldversion < 2026112200) {
        // The SEPE origin "20" is now a default of the SEPE profile, not of the table.
        $table = new xmldb_table('local_customreport_action');
        $field = new xmldb_field('actionorigin', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, null, 'authority');
        $dbman->change_field_default($table, $field);
        \local_customreport\local\profile_manager::install_builtin();
        upgrade_plugin_savepoint(true, 2026112200, 'local', 'customreport');
    }

    return true;
}
