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
 * Tests for the incremental log aggregation.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Tests for usage_aggregator.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(usage_aggregator::class)]
final class usage_aggregator_test extends \advanced_testcase {
    /** @var int Base time for the fake log. */
    const T0 = 1780000000;

    /**
     * Insert a fake log row.
     *
     * @param array $data
     * @return int
     */
    private function log(array $data): int {
        global $DB;
        return $DB->insert_record('logstore_standard_log', (object) ($data + [
            'eventname' => '\mod_page\event\course_module_viewed', 'component' => 'mod_page', 'action' => 'viewed',
            'target' => 'course_module', 'objecttable' => 'page', 'objectid' => 1, 'crud' => 'r', 'edulevel' => 2,
            'contextid' => 1, 'contextlevel' => CONTEXT_MODULE, 'contextinstanceid' => 7, 'userid' => 0,
            'courseid' => 0, 'relateduserid' => null, 'anonymous' => 0, 'other' => 'N;', 'timecreated' => self::T0,
            'origin' => 'web', 'ip' => '127.0.0.1', 'realuserid' => null]));
    }

    /**
     * Create a course with an action and a learner.
     *
     * @return array [course, user]
     */
    private function setup_action(): array {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0]);
        return [$course, $user];
    }

    /**
     * Time, accesses and participation are aggregated and the run is idempotent.
     */
    public function test_run_and_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        [$course, $user] = $this->setup_action();
        $base = ['userid' => $user->id, 'courseid' => $course->id];
        $this->log($base + ['timecreated' => self::T0]);
        $this->log($base + ['timecreated' => self::T0 + 600, 'eventname' => '\mod_forum\event\post_created',
            'component' => 'mod_forum', 'action' => 'created', 'crud' => 'c', 'contextinstanceid' => 8]);
        $this->log($base + ['timecreated' => self::T0 + 700]);

        $now = self::T0 + 10 * HOURSECS;
        $aggregator = new usage_aggregator(1800, 300, new \DateTimeZone('UTC'));
        $this->assertSame(3, $aggregator->run($now));
        $this->assertSame(0, $aggregator->run($now));

        $usage = $DB->get_records('local_customreport_usage', ['userid' => $user->id], 'cmid');
        $this->assertSame(1000, array_sum(array_column($usage, 'seconds')));
        $this->assertSame(2, array_sum(array_column($usage, 'accesses')));
        $this->assertSame(1, array_sum(array_column($usage, 'participations')));
        $this->assertSame(3, array_sum(array_column($usage, 'events')));
    }

    /**
     * Rows after the horizon wait; an open session is closed with the tail later.
     */
    public function test_horizon_and_incremental_close(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user] = $this->setup_action();
        $base = ['userid' => $user->id, 'courseid' => $course->id];
        $this->log($base + ['timecreated' => self::T0]);
        $aggregator = new usage_aggregator(1800, 300, new \DateTimeZone('UTC'));

        // Too early: the event is within the gap of "now", it must not be read yet.
        $this->assertSame(0, $aggregator->run(self::T0 + 100));
        // Later: read it; the session is not yet closed.
        $this->assertSame(1, $aggregator->run(self::T0 + 1800 + 10));
        $this->assertSame(0, (int) $DB->get_field_sql('SELECT SUM(seconds) FROM {local_customreport_usage}'));
        // Much later: the idle session is closed and the tail is added.
        $aggregator->run(self::T0 + 4000);
        $this->assertSame(300, (int) $DB->get_field_sql('SELECT SUM(seconds) FROM {local_customreport_usage}'));
        // Closing again does not add the tail twice.
        $aggregator->run(self::T0 + 9000);
        $this->assertSame(300, (int) $DB->get_field_sql('SELECT SUM(seconds) FROM {local_customreport_usage}'));
    }

    /**
     * A session continues between runs.
     */
    public function test_session_across_runs(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user] = $this->setup_action();
        $base = ['userid' => $user->id, 'courseid' => $course->id];
        $aggregator = new usage_aggregator(1800, 300, new \DateTimeZone('UTC'));
        $this->log($base + ['timecreated' => self::T0]);
        $aggregator->run(self::T0 + 2000);
        $this->log($base + ['timecreated' => self::T0 + 1500]);
        $aggregator->run(self::T0 + 5000);
        $aggregator->run(self::T0 + 9000);
        // 1500 between events + 300 tail.
        $this->assertSame(1800, (int) $DB->get_field_sql('SELECT SUM(seconds) FROM {local_customreport_usage}'));
    }

    /**
     * Courses without an action are not stored, but the platform login is.
     */
    public function test_untracked_course_and_login(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_course();
        $this->log(['userid' => $user->id, 'courseid' => $other->id, 'timecreated' => self::T0]);
        $this->log(['userid' => $user->id, 'courseid' => 0, 'timecreated' => self::T0 + 10, 'edulevel' => 0,
            'eventname' => '\core\event\user_loggedin', 'component' => 'core', 'action' => 'loggedin',
            'target' => 'user', 'crud' => 'r', 'contextlevel' => CONTEXT_SYSTEM, 'contextinstanceid' => 0]);
        (new usage_aggregator(1800, 300, new \DateTimeZone('UTC')))->run(self::T0 + 10000);
        $this->assertSame(0, $DB->count_records('local_customreport_usage', ['courseid' => $other->id]));
        $login = $DB->get_record('local_customreport_usage', ['userid' => $user->id, 'courseid' => 0]);
        $this->assertSame(1, (int) $login->accesses);
    }

    /**
     * Enrolment events produce registrations and withdrawals once, and a cause can be assigned.
     */
    public function test_enrolment_events_and_causes(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user] = $this->setup_action();
        $admin = get_admin();
        $this->log(['userid' => $admin->id, 'courseid' => $course->id, 'relateduserid' => $user->id,
            'eventname' => '\core\event\user_enrolment_created', 'timecreated' => self::T0, 'crud' => 'c']);
        $this->log(['userid' => $admin->id, 'courseid' => $course->id, 'relateduserid' => $user->id,
            'eventname' => '\core\event\user_enrolment_deleted', 'timecreated' => self::T0 + 100, 'crud' => 'd']);
        $aggregator = new usage_aggregator(1800, 300, new \DateTimeZone('UTC'));
        $aggregator->run(self::T0 + 10000);
        $this->assertSame(2, $DB->count_records('local_customreport_enrol'));
        $this->assertSame(0, $DB->count_records('local_customreport_usage'));

        $causes = cause_manager::get_menu();
        $this->assertCount(count(cause_manager::DEFAULTS), $causes);
        $this->assertTrue(cause_manager::assign($course->id, $user->id, (int) array_key_first($causes)));
        $this->assertSame(1, $DB->count_records_select('local_customreport_enrol', 'causeid > 0'));
        $this->assertFalse(cause_manager::assign($course->id, $user->id + 99, 1));
    }

    /**
     * Accesses and seconds are also stored per time-of-day slot (used by the web service).
     */
    public function test_slots_are_aggregated(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user] = $this->setup_action();
        $base = ['userid' => $user->id, 'courseid' => $course->id];
        $morning = gmmktime(9, 0, 0, 6, 1, 2026);
        $evening = gmmktime(20, 0, 0, 6, 1, 2026);
        $this->log($base + ['timecreated' => $morning]);
        $this->log($base + ['timecreated' => $morning + 600]);
        $this->log($base + ['timecreated' => $evening]);
        (new usage_aggregator(1800, 300, new \DateTimeZone('UTC')))->run($evening + 5 * HOURSECS);
        $slots = $DB->get_records('local_customreport_slot', ['userid' => $user->id], 'slot', 'slot, accesses, seconds');
        $this->assertSame(2, (int) $slots[1]->accesses);
        $this->assertSame(900, (int) $slots[1]->seconds);
        $this->assertSame(1, (int) $slots[2]->accesses);
        $this->assertSame(300, (int) $slots[2]->seconds);
    }
}
