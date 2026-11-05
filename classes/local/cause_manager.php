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
 * Catalogue of withdrawal causes.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Configurable catalogue of withdrawal causes and their assignment to withdrawals.
 */
class cause_manager {
    /** @var string[] Default cause codes. */
    const DEFAULTS = ['placement', 'work', 'personal', 'health', 'content', 'technical', 'other'];

    /**
     * Insert the default causes that do not exist.
     */
    public static function install_defaults(): void {
        global $DB;
        foreach (self::DEFAULTS as $i => $code) {
            if (!$DB->record_exists('local_customreport_cause', ['code' => $code])) {
                $DB->insert_record('local_customreport_cause', (object) [
                    'code' => $code,
                    'name' => get_string('cause_' . $code, 'local_customreport'),
                    'sortorder' => $i + 1,
                    'enabled' => 1,
                ]);
            }
        }
    }

    /**
     * Enabled causes indexed by id.
     *
     * @return string[]
     */
    public static function get_menu(): array {
        global $DB;
        return $DB->get_records_menu('local_customreport_cause', ['enabled' => 1], 'sortorder', 'id, name');
    }

    /**
     * Add a custom cause.
     *
     * @param string $code
     * @param string $name
     * @return int
     */
    public static function add(string $code, string $name): int {
        global $DB;
        $order = (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {local_customreport_cause}') + 1;
        return (int) $DB->insert_record(
            'local_customreport_cause',
            (object) ['code' => $code, 'name' => $name, 'sortorder' => $order, 'enabled' => 1]
        );
    }

    /**
     * Assign a cause to the latest withdrawal of a user in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $causeid
     * @return bool False when the user has no withdrawal event.
     */
    public static function assign(int $courseid, int $userid, int $causeid): bool {
        global $DB;
        $rows = $DB->get_records('local_customreport_enrol', ['courseid' => $courseid, 'userid' => $userid,
            'eventtype' => 'delete'], 'eventtime DESC, id DESC', 'id', 0, 1);
        if (!$rows) {
            return false;
        }
        $DB->set_field('local_customreport_enrol', 'causeid', $causeid, ['id' => key($rows)]);
        return true;
    }
}
