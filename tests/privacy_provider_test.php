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
 * Privacy provider tests.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use local_customreport\local\action_manager;
use local_customreport\privacy\provider;

/**
 * Tests for the privacy provider.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\local\retention::class)]
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create a course with usage of a user.
     *
     * @param int $end End date of the action (0 = unknown).
     * @return array
     */
    private function setup_data(int $end): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0, 'endplanned' => $end]);
        $DB->insert_record('local_customreport_usage', (object) ['userid' => $user->id, 'courseid' => $course->id,
            'cmid' => 0, 'component' => 'mod_page', 'day' => 1000, 'events' => 2, 'accesses' => 1, 'participations' => 0,
            'seconds' => 120, 'firstaccess' => 1000, 'lastaccess' => 1100]);
        $DB->insert_record('local_customreport_enrol', (object) ['courseid' => $course->id, 'userid' => $user->id,
            'eventtype' => 'create', 'eventtime' => 1000, 'logid' => 5, 'causeid' => 0]);
        return [$course, $user, \context_course::instance($course->id)];
    }

    /**
     * Contexts, users and metadata are exposed.
     */
    public function test_contexts_and_metadata(): void {
        $this->resetAfterTest();
        [, $user, $context] = $this->setup_data(time() + DAYSECS);
        $this->assertEquals([$context->id], provider::get_contexts_for_userid($user->id)->get_contextids());
        $userlist = new \core_privacy\local\request\userlist($context, 'local_customreport');
        provider::get_users_in_context($userlist);
        $this->assertSame([(int) $user->id], array_map('intval', $userlist->get_userids()));
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('local_customreport'));
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * Export contains usage and enrolments.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        [, $user, $context] = $this->setup_data(time() + DAYSECS);
        $contextlist = new approved_contextlist($user, 'local_customreport', [$context->id]);
        provider::export_user_data($contextlist);
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_customreport')]);
        $this->assertCount(1, $data->usage);
        $this->assertSame(120, (int) $data->usage[0]->seconds);
        $this->assertCount(1, $data->enrolments);
    }

    /**
     * Deletion during the retention period is deferred and carried out afterwards.
     */
    public function test_deferred_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        $end = time() + 10 * DAYSECS;
        [$course, $user, $context] = $this->setup_data($end);
        provider::delete_data_for_user(new approved_contextlist($user, 'local_customreport', [$context->id]));
        $this->assertSame(1, $DB->count_records('local_customreport_usage'));
        $this->assertSame(1, $DB->count_records('local_customreport_deferred'));
        // Still within 90 days after the end: nothing is purged.
        $this->assertSame(0, provider::purge_deferred($end + 89 * DAYSECS));
        $this->assertSame(1, $DB->count_records('local_customreport_usage'));
        // After the control period the deferred deletion is carried out.
        $this->assertSame(1, provider::purge_deferred($end + 91 * DAYSECS));
        $this->assertSame(0, $DB->count_records('local_customreport_usage'));
        $this->assertSame(0, $DB->count_records('local_customreport_enrol'));
        $this->assertSame(0, $DB->count_records('local_customreport_deferred'));
    }

    /**
     * Deletion is immediate when the retention period has ended.
     */
    public function test_immediate_deletion_after_retention(): void {
        global $DB;
        $this->resetAfterTest();
        [, $user, $context] = $this->setup_data(time() - 200 * DAYSECS);
        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('local_customreport_usage'));
        $this->assertSame(0, $DB->count_records('local_customreport_deferred'));
    }
}
