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
 * Tests for training actions.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Tests for action_manager.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\local\action_manager::class)]
final class action_manager_test extends \advanced_testcase {
    /**
     * Valid data passes validation; wrong values produce errors.
     */
    public function test_validate(): void {
        $data = (object) ['situation' => 20, 'modality' => 'TF', 'financing' => 'PR', 'centercode' => '8000000001'];
        $this->assertSame([], action_manager::validate($data));

        $data->situation = 25;
        $data->modality = 'XX';
        $data->financing = 'ZZ';
        $data->centercode = '12';
        $data->startplanned = 200;
        $data->endplanned = 100;
        $errors = action_manager::validate($data);
        $this->assertEqualsCanonicalizing(
            ['situation', 'modality', 'financing', 'centercode', 'endplanned'],
            array_keys($errors)
        );
    }

    /**
     * Dates older than seven days before the consultation are real, others planned.
     */
    public function test_is_real_date(): void {
        $now = 1700000000;
        $this->assertFalse(action_manager::is_real_date(0, $now));
        $this->assertTrue(action_manager::is_real_date($now - 8 * DAYSECS, $now));
        $this->assertFalse(action_manager::is_real_date($now - 7 * DAYSECS, $now));
        $this->assertFalse(action_manager::is_real_date($now - 3 * DAYSECS, $now));
        $this->assertFalse(action_manager::is_real_date($now + DAYSECS, $now));
    }

    /**
     * Save inserts then updates; one action per course and group.
     */
    public function test_save_and_get(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $data = (object) ['courseid' => $course->id, 'groupid' => 0, 'actioncode' => 'A1', 'name' => 'First'];
        $id = action_manager::save($data);
        $this->assertSame('First', action_manager::get($course->id)->name);

        $again = (object) ['courseid' => $course->id, 'groupid' => 0, 'actioncode' => 'A1', 'name' => 'Second'];
        $this->assertSame($id, action_manager::save($again));
        $this->assertSame('Second', action_manager::get($course->id)->name);
        $this->assertCount(1, action_manager::get_for_course($course->id));

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        action_manager::save((object) ['courseid' => $course->id, 'groupid' => $group->id, 'name' => 'Group action']);
        $this->assertCount(2, action_manager::get_for_course($course->id));
    }

    /**
     * Deleting an action deletes its structure.
     */
    public function test_delete_cascades(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0]);
        structure_manager::add($actionid, 'MF', 'MF0001', 'Module', 60);
        action_manager::delete($actionid);
        $this->assertSame(0, $DB->count_records('local_customreport_module'));
    }
}
