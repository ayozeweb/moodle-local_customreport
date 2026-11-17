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
 * Tests for progress calculation and the public API.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

use local_customreport\api\progress;

/**
 * Tests for progress_calculator and the progress API.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(progress_calculator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(progress::class)]
final class progress_test extends \advanced_testcase {
    /**
     * Build a course with two modules, activities, completion and grades.
     *
     * @return array
     */
    private function build(): array {
        global $DB, $CFG;
        $CFG->enablecompletion = 1;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2, 'enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $other = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $assign1 = $generator->create_module('assign', ['course' => $course->id, 'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $assign2 = $generator->create_module('assign', ['course' => $course->id, 'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $final = $generator->create_module('assign', ['course' => $course->id, 'section' => 2,
            'completion' => COMPLETION_TRACKING_MANUAL]);

        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0, 'name' => 'Action',
            'actioncode' => 'A1', 'hours' => 100]);
        $sections = array_keys(structure_manager::course_section_menu($course->id));
        $mf1 = structure_manager::add($actionid, 'MF', 'MF1', 'Module 1', 60, 0, [$sections[1]]);
        $mf2 = structure_manager::add($actionid, 'MF', 'MF2', 'Module 2', 40, 0, [$sections[2]]);
        cmrole_manager::set_role($course->id, $assign1->cmid, 'process');
        cmrole_manager::set_role($course->id, $assign2->cmid, 'process');
        cmrole_manager::set_role($course->id, $final->cmid, 'final1');

        $completion = new \completion_info(get_course($course->id));
        $completion->update_state(get_fast_modinfo($course)->get_cm($assign1->cmid), COMPLETION_COMPLETE, $student->id);
        $this->set_grade($course, $assign1, $student, 80);
        $this->set_grade($course, $assign2, $student, 60);
        $this->set_grade($course, $final, $student, 50);
        return compact('course', 'student', 'other', 'teacher', 'mf1', 'mf2', 'actionid');
    }

    /**
     * Set a grade (assign max grade is 100 by default).
     *
     * @param \stdClass $course
     * @param \stdClass $module
     * @param \stdClass $user
     * @param float $value Percentage 0-100.
     */
    private function set_grade($course, $module, $user, float $value): void {
        $item = \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $module->id]);
        $item->update_final_grade($user->id, $value);
    }

    /**
     * Progress per module and total.
     */
    public function test_progress_values(): void {
        global $DB;
        $this->resetAfterTest();
        $data = $this->build();
        $rows = progress_calculator::update_course($data['course']->id);
        $this->assertSame(6, $rows);

        $get = fn($nodeid, $user) => $DB->get_record(
            'local_customreport_progress',
            ['courseid' => $data['course']->id, 'userid' => $user->id, 'nodeid' => $nodeid]
        );
        $m1 = $get($data['mf1'], $data['student']);
        $this->assertSame(2, (int) $m1->activitiestotal);
        $this->assertSame(1, (int) $m1->activitiesdone);
        $this->assertEqualsWithDelta(7.0, (float) $m1->processavg, 0.001);
        $this->assertNull($m1->final1);
        $m2 = $get($data['mf2'], $data['student']);
        $this->assertEqualsWithDelta(5.0, (float) $m2->final1, 0.001);
        $this->assertNull($m2->processavg);
        $total = $get(0, $data['student']);
        $this->assertSame(3, (int) $total->activitiestotal);
        $this->assertSame(1, (int) $total->activitiesdone);
        $this->assertSame(0, (int) $get(0, $data['other'])->activitiesdone);

        // Running again updates in place.
        progress_calculator::update_course($data['course']->id);
        $this->assertSame(6, $DB->count_records('local_customreport_progress'));
    }

    /**
     * The API returns data to the user, a teacher and refuses another student.
     */
    public function test_api_permissions(): void {
        $this->resetAfterTest();
        $data = $this->build();
        progress_calculator::update_course($data['course']->id);

        $this->setUser($data['student']);
        $result = progress::get_user_progress($data['course']->id, $data['student']->id);
        $this->assertSame([0, $data['mf1'], $data['mf2']], array_keys($result));
        $this->assertSame('MF1', $result[$data['mf1']]['code']);
        $this->assertEqualsWithDelta(50.0, $result[$data['mf1']]['percent'], 0.01);
        $this->assertEqualsWithDelta(7.0, $result[$data['mf1']]['processavg'], 0.001);

        $this->setUser($data['teacher']);
        $this->assertNotEmpty(progress::get_user_progress($data['course']->id, $data['student']->id));

        $this->setUser($data['other']);
        $this->expectException(\required_capability_exception::class);
        progress::get_user_progress($data['course']->id, $data['student']->id);
    }

    /**
     * Without an action the API returns nothing.
     */
    public function test_api_without_action(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->assertSame([], progress::get_user_progress($course->id, $student->id));
    }
}
