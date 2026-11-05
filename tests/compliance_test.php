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
 * Tests for the compliance checks.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport;

use local_customreport\local\action_manager;
use local_customreport\local\compliance;
use local_customreport\local\inspector_role;
use local_customreport\local\profile_field_wizard;
use local_customreport\local\profile_manager;
use local_customreport\local\structure_manager;
use local_customreport\sepe\security;

/**
 * Tests for compliance.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(compliance::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\check\session_timeout::class)]
final class compliance_test extends \advanced_testcase {
    /**
     * Session timeout levels.
     */
    public function test_sessiontimeout(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->sessiontimeout = 0;
        $this->assertSame(compliance::ERROR, compliance::sessiontimeout()['status']);
        $CFG->sessiontimeout = 8 * HOURSECS;
        $this->assertSame(compliance::WARNING, compliance::sessiontimeout()['status']);
        $CFG->sessiontimeout = 2 * HOURSECS;
        $this->assertSame(compliance::OK, compliance::sessiontimeout()['status']);
        $check = new check\session_timeout();
        $this->assertSame(\core\check\result::OK, $check->get_result()->get_status());
    }

    /**
     * Inspector role, profile fields, branding and actions.
     */
    public function test_checks_follow_configuration(): void {
        $this->resetAfterTest();
        $this->assertSame(compliance::WARNING, compliance::profile_fields()['status']);
        profile_field_wizard::create_fields('learner');
        profile_field_wizard::create_fields('tutor');
        $this->assertSame(compliance::OK, compliance::profile_fields()['status']);

        $roleid = inspector_role::ensure();
        $this->assertSame(compliance::OK, compliance::inspector()['status']);
        assign_capability('moodle/site:config', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        $this->assertSame(compliance::ERROR, compliance::inspector()['status']);

        $this->assertSame(compliance::WARNING, compliance::branding()['status']);
        get_file_storage()->create_file_from_string(['contextid' => \context_system::instance()->id,
            'component' => 'local_customreport', 'filearea' => 'logo1', 'itemid' => 0, 'filepath' => '/',
            'filename' => 'a.png'], 'x');
        $this->assertSame(compliance::OK, compliance::branding()['status']);

        profile_manager::install_builtin();
        $this->assertSame(compliance::WARNING, compliance::actions()['status']);
        $course = $this->getDataGenerator()->create_course();
        $id = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0, 'actioncode' => 'A1',
            'profile' => 'sepe_sce_tms369']);
        $this->assertStringContainsString('planned dates', compliance::actions()['message']);
        action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0, 'startplanned' => 1000, 'endplanned' => 2000]);
        structure_manager::add($id, 'MF', 'M', 'Module', 10);
        $this->assertSame(compliance::OK, compliance::actions()['status']);
        action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0, 'profile' => 'nope']);
        $this->assertStringContainsString('unknown regulatory profile', compliance::actions()['message']);
    }

    /**
     * The endpoint check needs HTTPS (or the testing override), credentials and the centre.
     */
    public function test_endpoint(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->wwwroot = 'https://example.com';
        $this->assertSame(compliance::ERROR, compliance::endpoint()['status']);
        security::save_credential('SEPE', 'sepe', 'a-long-password-1');
        $this->assertSame(compliance::WARNING, compliance::endpoint()['status']);
        security::save_credential('SEPE', 'sepe', 's3cret-pass');
        \local_customreport\sepe\service::handle(file_get_contents(__DIR__ . '/fixtures/ws/crearCentro.xml'), '');
        $this->assertSame(compliance::OK, compliance::endpoint()['status']);
        set_config('ws_enabled', 0, 'local_customreport');
        $this->assertSame(compliance::ERROR, compliance::endpoint()['status']);
        $CFG->wwwroot = 'http://example.com';
        set_config('ws_enabled', 1, 'local_customreport');
        $this->assertSame(compliance::ERROR, compliance::endpoint()['status']);
    }

    /**
     * Cron check and overall status.
     */
    public function test_cron_and_overall(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->set_field('task_scheduled', 'lastruntime', 0, ['classname' => '\local_customreport\task\aggregate_usage']);
        $this->assertSame(compliance::WARNING, compliance::cron()['status']);
        $DB->set_field('task_scheduled', 'lastruntime', 1000, ['classname' => '\local_customreport\task\aggregate_usage']);
        $this->assertSame(compliance::ERROR, compliance::cron(1000 + 2 * DAYSECS)['status']);
        $this->assertSame(compliance::OK, compliance::cron(1000 + HOURSECS)['status']);
        $mixed = [['status' => 'ok'], ['status' => 'error'], ['status' => 'warning']];
        $this->assertSame(compliance::ERROR, compliance::overall($mixed));
        $this->assertSame(compliance::WARNING, compliance::overall([['status' => 'ok'], ['status' => 'warning']]));
        $this->assertSame(compliance::OK, compliance::overall([['status' => 'ok']]));
        $all = compliance::run();
        $this->assertCount(8, $all);
        foreach ($all as $check) {
            $this->assertMatchesRegularExpression('/^R-/', $check['ref']);
        }
    }
}
