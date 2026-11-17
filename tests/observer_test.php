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
 * Tests for the event observers.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport;

use local_customreport\local\action_manager;
use local_customreport\local\cmrole_manager;
use local_customreport\local\structure_manager;
use local_customreport\privacy\provider;

/**
 * Tests for observer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class observer_test extends \advanced_testcase {
    /**
     * Course with an action ending at $end, a learner with usage and an activity with a role.
     *
     * @param int $end
     * @return array
     */
    private function setup_course(int $end): array {
        global $DB;
        $g = $this->getDataGenerator();
        $course = $g->create_course(['numsections' => 2]);
        $user = $g->create_and_enrol($course, 'student');
        $page = $g->create_module('page', ['course' => $course->id, 'section' => 1]);
        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0, 'endplanned' => $end]);
        cmrole_manager::set_role($course->id, $page->cmid, 'process');
        $DB->insert_record('local_customreport_usage', (object) ['userid' => $user->id, 'courseid' => $course->id,
            'cmid' => $page->cmid, 'component' => 'mod_page', 'day' => 1000, 'events' => 1, 'accesses' => 1,
            'participations' => 0, 'seconds' => 60, 'firstaccess' => 1000, 'lastaccess' => 1000]);
        return [$course, $user, $page, $actionid];
    }

    /**
     * Deleting an activity removes its evaluative role; deleting a section removes it from the structure.
     */
    public function test_activity_and_section_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, , $page, $actionid] = $this->setup_course(time() + DAYSECS);
        $sections = array_keys(structure_manager::course_section_menu($course->id));
        $node = structure_manager::add($actionid, 'MF', 'M', 'Module', 10, 0, [$sections[1], $sections[2]]);

        (new \core_courseformat\local\cmactions(get_course($course->id)))->delete($page->cmid);
        $this->assertSame([], cmrole_manager::get_roles($course->id));

        course_delete_section($course, 2, true);
        $nodes = structure_manager::get_nodes($actionid);
        $this->assertSame([(int) $sections[1]], structure_manager::node_sections($nodes[$node]));
        $this->assertSame(1, $DB->count_records('local_customreport_usage'));
    }

    /**
     * A deleted course whose control period has ended: data and configuration go at once.
     */
    public function test_course_deleted_after_retention(): void {
        global $DB;
        $this->resetAfterTest();
        [$course] = $this->setup_course(time() - 200 * DAYSECS);
        delete_course($course, false);
        $this->assertSame(0, $DB->count_records('local_customreport_usage'));
        $this->assertSame(0, $DB->count_records('local_customreport_action'));
        $this->assertSame(0, $DB->count_records('local_customreport_cmrole'));
        $this->assertSame(0, $DB->count_records('local_customreport_deferred'));
    }

    /**
     * A deleted course still under control keeps its follow-up data until the deferred deletion runs.
     */
    public function test_course_deleted_during_retention(): void {
        global $DB;
        $this->resetAfterTest();
        $end = time() + 10 * DAYSECS;
        [$course] = $this->setup_course($end);
        delete_course($course, false);
        $this->assertSame(1, $DB->count_records('local_customreport_usage'));
        $this->assertSame(1, $DB->count_records('local_customreport_action'));
        $this->assertSame(1, $DB->count_records('local_customreport_deferred'));
        $this->assertSame(0, $DB->count_records('local_customreport_cmrole'));

        $this->assertSame(1, provider::purge_deferred($end + 91 * DAYSECS));
        $this->assertSame(0, $DB->count_records('local_customreport_usage'));
        $this->assertSame(0, $DB->count_records('local_customreport_action'));
    }

    /**
     * A deleted user: data erased (or deferred under control) and the technical cursor removed.
     */
    public function test_user_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user] = $this->setup_course(time() - 200 * DAYSECS);
        [, $other] = $this->setup_course(time() + DAYSECS);
        $DB->insert_record('local_customreport_cursor', (object) ['userid' => $user->id, 'lasttime' => 1,
            'lastcourseid' => $course->id, 'lastcmid' => 0, 'lastcomponent' => 'x', 'isopen' => 1]);

        delete_user($user);
        $this->assertSame(0, $DB->count_records('local_customreport_usage', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_customreport_cursor', ['userid' => $user->id]));

        delete_user($other);
        $this->assertSame(1, $DB->count_records('local_customreport_usage', ['userid' => $other->id]));
        $this->assertSame(1, $DB->count_records('local_customreport_deferred', ['userid' => $other->id]));
    }
}
