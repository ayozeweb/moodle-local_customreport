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
 * Formative structure (modules and units) manager.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

use moodle_exception;

/**
 * Assigns course sections (and subsections) to formative modules (MF) and units (UF).
 */
class structure_manager {
    /** @var string[] Allowed node types: module, unit, practice module in work centres. */
    const TYPES = ['MF', 'UF', 'MP'];

    /**
     * Add a node.
     *
     * @param int $actionid
     * @param string $type MF, UF or MP.
     * @param string $code
     * @param string $name
     * @param int $hours
     * @param int $parentid Module id when the node is a UF.
     * @param int[] $sectionids course_sections ids.
     * @return int New node id.
     * @throws moodle_exception
     */
    public static function add(
        int $actionid,
        string $type,
        string $code,
        string $name,
        int $hours,
        int $parentid = 0,
        array $sectionids = []
    ): int {
        global $DB;
        if (!in_array($type, self::TYPES, true) || $hours < 0) {
            throw new moodle_exception('err_structure', 'local_customreport');
        }
        if ($type === 'UF') {
            $parent = $DB->get_record('local_customreport_module', ['id' => $parentid, 'actionid' => $actionid]);
            if (!$parent || $parent->type !== 'MF') {
                throw new moodle_exception('err_structure_parent', 'local_customreport');
            }
        } else {
            $parentid = 0;
        }
        $sortorder = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {local_customreport_module} WHERE actionid = ?',
            [$actionid]
        ) + 1;
        return (int) $DB->insert_record('local_customreport_module', (object) [
            'actionid' => $actionid,
            'parentid' => $parentid,
            'type' => $type,
            'code' => $code,
            'name' => $name,
            'hours' => $hours,
            'sectionids' => implode(',', array_map('intval', $sectionids)),
            'sortorder' => $sortorder,
        ]);
    }

    /**
     * Replace the sections assigned to a node.
     *
     * @param int $nodeid
     * @param int[] $sectionids
     */
    public static function set_sections(int $nodeid, array $sectionids): void {
        global $DB;
        $DB->set_field(
            'local_customreport_module',
            'sectionids',
            implode(',', array_map('intval', $sectionids)),
            ['id' => $nodeid]
        );
    }

    /**
     * Delete a node and, if it is a module, its units.
     *
     * @param int $nodeid
     */
    public static function delete(int $nodeid): void {
        global $DB;
        $DB->delete_records('local_customreport_module', ['parentid' => $nodeid]);
        $DB->delete_records('local_customreport_module', ['id' => $nodeid]);
    }

    /**
     * Get the nodes of an action ordered for display.
     *
     * @param int $actionid
     * @return \stdClass[]
     */
    public static function get_nodes(int $actionid): array {
        global $DB;
        return $DB->get_records('local_customreport_module', ['actionid' => $actionid], 'sortorder');
    }

    /**
     * Sections assigned to a node as an integer list.
     *
     * @param \stdClass $node
     * @return int[]
     */
    public static function node_sections(\stdClass $node): array {
        return $node->sectionids === '' || $node->sectionids === null ? [] :
            array_map('intval', explode(',', $node->sectionids));
    }

    /**
     * Section ids assigned more than once in the same action (a section must map to one node).
     *
     * @param int $actionid
     * @return int[]
     */
    public static function find_duplicate_sections(int $actionid): array {
        $seen = [];
        $dups = [];
        foreach (self::get_nodes($actionid) as $node) {
            foreach (self::node_sections($node) as $sid) {
                if (isset($seen[$sid])) {
                    $dups[$sid] = $sid;
                }
                $seen[$sid] = true;
            }
        }
        return array_values($dups);
    }

    /**
     * Section ids of the course (sections and delegated subsections) with their names.
     *
     * @param int $courseid
     * @return string[] section id => name
     */
    public static function course_section_menu(int $courseid): array {
        $menu = [];
        foreach (get_fast_modinfo($courseid)->get_section_info_all() as $section) {
            $menu[$section->id] = get_section_name($courseid, $section);
        }
        return $menu;
    }
}
