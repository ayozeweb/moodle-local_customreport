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
 * Tests for the formative structure.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Tests for structure_manager.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\local\structure_manager::class)]
final class structure_manager_test extends \advanced_testcase {
    /**
     * Modules, units and section assignment.
     */
    public function test_modules_units_sections(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);
        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0]);
        $sections = array_keys(structure_manager::course_section_menu($course->id));

        $mf = structure_manager::add($actionid, 'MF', 'MF0001_3', 'Module 1', 90, 0, [$sections[1]]);
        $uf = structure_manager::add($actionid, 'UF', 'UF0001', 'Unit 1', 30, $mf, [$sections[2], $sections[3]]);
        $nodes = structure_manager::get_nodes($actionid);
        $this->assertCount(2, $nodes);
        $this->assertSame([(int) $sections[2], (int) $sections[3]], structure_manager::node_sections($nodes[$uf]));
        $this->assertSame([], structure_manager::find_duplicate_sections($actionid));

        structure_manager::set_sections($mf, [$sections[1], $sections[2]]);
        $this->assertSame([(int) $sections[2]], structure_manager::find_duplicate_sections($actionid));
    }

    /**
     * A unit needs a module parent in the same action.
     */
    public function test_unit_requires_parent(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0]);
        $this->expectException(\moodle_exception::class);
        structure_manager::add($actionid, 'UF', 'UF0001', 'Orphan', 10);
    }

    /**
     * Invalid type is rejected and deleting a module deletes its units.
     */
    public function test_invalid_type_and_delete(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0]);
        $mf = structure_manager::add($actionid, 'MF', 'M', 'Module', 10);
        structure_manager::add($actionid, 'UF', 'U', 'Unit', 5, $mf);
        structure_manager::delete($mf);
        $this->assertSame(0, $DB->count_records('local_customreport_module'));
        $this->expectException(\moodle_exception::class);
        structure_manager::add($actionid, 'XX', 'X', 'Bad', 1);
    }
}
