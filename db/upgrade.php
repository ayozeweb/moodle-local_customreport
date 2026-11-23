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
 * The pre-release development steps (2026110401-2026112200) were collapsed before the first public release:
 * db/install.xml and db/install.php describe the complete schema and data of version 2026112200. A site still
 * on an earlier pre-release version must uninstall and reinstall the plugin.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_customreport_upgrade($oldversion) {
    if ($oldversion < 2026112200) {
        throw new moodle_exception('upgradeprerelease', 'local_customreport', '', $oldversion);
    }

    // Steps for versions after the 2026112200 baseline go here.

    return true;
}
