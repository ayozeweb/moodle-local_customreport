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
 * Tests for the follow-up report, exports, roles, indicators and report builder sources.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport;

use local_customreport\export\csv;
use local_customreport\export\xml;
use local_customreport\local\access;
use local_customreport\local\action_manager;
use local_customreport\local\cmrole_manager;
use local_customreport\local\indicators;
use local_customreport\local\inspector_role;
use local_customreport\local\profile_manager;
use local_customreport\local\progress_calculator;
use local_customreport\local\structure_manager;
use local_customreport\report\followup;
use local_customreport\report\presenter;

/**
 * Tests for the report layer.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(followup::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(csv::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(xml::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(inspector_role::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(indicators::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(presenter::class)]
final class report_test extends \advanced_testcase {
    /**
     * Build a course with an action, two modules, two learners, usage and grades.
     *
     * @return array
     */
    private function build(): array {
        global $CFG, $DB;
        $CFG->enablecompletion = 1;
        profile_manager::install_builtin();
        $g = $this->getDataGenerator();
        $course = $g->create_course(['numsections' => 2, 'enablecompletion' => 1]);
        $ana = $g->create_and_enrol($course, 'student', ['firstname' => 'Ana', 'lastname' => 'A']);
        $bea = $g->create_and_enrol($course, 'student', ['firstname' => 'Bea', 'lastname' => 'B']);
        $tutor = $g->create_and_enrol($course, 'editingteacher');
        $proc = $g->create_module('assign', ['course' => $course->id, 'section' => 1, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $final = $g->create_module('assign', ['course' => $course->id, 'section' => 2, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $start = time() - 10 * DAYSECS;
        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0, 'name' => 'Action',
            'actioncode' => 'A1',
            'profile' => 'sepe_sce_tms369', 'startplanned' => $start, 'endplanned' => $start + 20 * DAYSECS, 'hours' => 100,
            'centercode' => '8000000001']);
        $sections = array_keys(structure_manager::course_section_menu($course->id));
        $mf1 = structure_manager::add($actionid, 'MF', 'MF1', 'Module 1', 60, 0, [$sections[1]]);
        $mf2 = structure_manager::add($actionid, 'MF', 'MF2', 'Module 2', 40, 0, [$sections[2]]);
        cmrole_manager::set_role($course->id, $proc->cmid, 'process');
        cmrole_manager::set_role($course->id, $final->cmid, 'final1');
        $completion = new \completion_info(get_course($course->id));
        $completion->update_state(get_fast_modinfo($course)->get_cm($proc->cmid), COMPLETION_COMPLETE, $ana->id);
        foreach ([[$proc, 80], [$final, 60]] as [$mod, $value]) {
            \grade_item::fetch(['courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'assign',
                'iteminstance' => $mod->id])->update_final_grade($ana->id, $value);
        }
        // Module 1 has only process (no final): not passed. Module 2 has only a final: no score either.
        $day = $start + DAYSECS;
        $usage = [[$ana, $proc->cmid, 3600, 4, 1], [$ana, $final->cmid, 1800, 2, 0]];
        foreach ($usage as [$user, $cmid, $seconds, $accesses, $part]) {
            $DB->insert_record('local_customreport_usage', (object) ['userid' => $user->id, 'courseid' => $course->id,
                'cmid' => $cmid, 'component' => 'mod_assign', 'day' => $day, 'events' => $accesses, 'accesses' => $accesses,
                'participations' => $part, 'seconds' => $seconds, 'firstaccess' => $day + 10, 'lastaccess' => $day + 500]);
        }
        $DB->insert_record('local_customreport_enrol', (object) ['courseid' => $course->id, 'userid' => $bea->id,
            'eventtype' => 'create', 'eventtime' => $start, 'logid' => 1, 'causeid' => 0]);
        $DB->insert_record('local_customreport_enrol', (object) ['courseid' => $course->id, 'userid' => $bea->id,
            'eventtype' => 'delete', 'eventtime' => $start + 100, 'logid' => 2, 'causeid' => 0]);
        progress_calculator::update_course($course->id);
        $action = action_manager::get($course->id);
        return compact('course', 'ana', 'bea', 'tutor', 'action', 'mf1', 'mf2', 'day');
    }

    /**
     * Totals, per learner values, status and node statistics.
     */
    public function test_build(): void {
        $this->resetAfterTest();
        $d = $this->build();
        $data = followup::build($d['action'], [$d['ana']->id, $d['bea']->id]);
        $this->assertSame(2, $data['summary']['learners']);
        $this->assertSame(1, $data['summary']['started']);
        $this->assertEqualsWithDelta(50.0, $data['summary']['startedpercent'], 0.01);
        $this->assertSame(1, $data['summary']['dropped']);
        $this->assertSame(5400, $data['summary']['totalseconds']);
        $this->assertSame(2700, $data['summary']['avgseconds']);
        $this->assertSame(6, $data['summary']['accesses']);
        $this->assertSame(1, $data['summary']['participations']);

        $byname = array_column($data['learners'], null, 'fullname');
        $ana = $byname[fullname($d['ana'])];
        $this->assertSame('started', $ana['status']);
        $this->assertSame(5400, $ana['seconds']);
        $this->assertSame('dropped', $byname[fullname($d['bea'])]['status']);
        $this->assertEqualsWithDelta(8.0, $ana['nodes'][$d['mf1']]['processavg'], 0.001);
        $this->assertEqualsWithDelta(6.0, $ana['nodes'][$d['mf2']]['final1'], 0.001);
        $this->assertCount(2, $data['nodes']);
        $this->assertSame(0, $data['summary']['passall']);
        $this->assertSame('mod_assign', $data['areas'][0]['component']);
        $this->assertEqualsWithDelta(100.0, $data['areas'][0]['accessespercent'], 0.01);
    }

    /**
     * Date and module filters limit the usage.
     */
    public function test_filters(): void {
        $this->resetAfterTest();
        $d = $this->build();
        $users = [$d['ana']->id];
        $out = followup::build($d['action'], $users, $d['day'] + 5 * DAYSECS, 0);
        $this->assertSame(0, $out['summary']['totalseconds']);
        $in = followup::build($d['action'], $users, $d['day'] - DAYSECS, $d['day'] + DAYSECS);
        $this->assertSame(5400, $in['summary']['totalseconds']);
        $mf1 = followup::build($d['action'], $users, 0, 0, $d['mf1']);
        $this->assertSame(3600, $mf1['summary']['totalseconds']);
        $this->assertSame(3600, $mf1['nodes'][0]['seconds']);
    }

    /**
     * CSV follows the profile columns and XML validates against the schema.
     */
    public function test_exports(): void {
        $this->resetAfterTest();
        $d = $this->build();
        $data = followup::build($d['action'], [$d['ana']->id, $d['bea']->id]);
        $csvdata = csv::render($data);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvdata);
        $lines = array_filter(explode("\n", trim(substr($csvdata, 3))));
        $this->assertCount(3, $lines);
        $this->assertSame(count($data['profile']->columns), count(str_getcsv($lines[0], ';', '"', '\\')));
        $durations = array_map(fn($l) => presenter::duration($l['seconds']), $data['learners']);
        $this->assertStringContainsString('1:30', implode(';', $durations));

        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML(xml::render($data, 1780000000)));
        $this->assertTrue($doc->schemaValidate(__DIR__ . '/../docs/followup.xsd'));
        $this->assertSame('sepe_sce_tms369', $doc->documentElement->getAttribute('profile'));
        $this->assertSame(2, $doc->getElementsByTagNameNS(xml::NS, 'learner')->length);
        $this->assertSame('planned', $doc->getElementsByTagNameNS(xml::NS, 'startdate')->item(0)->getAttribute('type'));
    }

    /**
     * Templates render with the generated context (including charts).
     */
    public function test_templates_render(): void {
        global $PAGE;
        $this->resetAfterTest();
        $d = $this->build();
        $data = followup::build($d['action'], [$d['ana']->id]);
        $output = $PAGE->get_renderer('core');
        $html = $output->render_from_template('local_customreport/report', presenter::context($data, $output));
        $this->assertStringContainsString('Module 1', $html);
        $this->assertStringContainsString('<table', $html);
        $html = $output->render_from_template('local_customreport/student', ['logos' => [], 'fullname' => 'Ana',
            'actionname' => 'Action', 'generated' => 'today', 'rows' => [['code' => 'MF1', 'name' => 'M', 'hours' => 1,
            'done' => '1/2', 'percent' => 50, 'process' => '7', 'final' => '-', 'score' => '-', 'call' => '-', 'time' => '1:00',
            'result' => 'x']]]);
        $this->assertStringContainsString('MF1', $html);
    }

    /**
     * Tutors see only their groups; learners only themselves; the administration everything.
     */
    public function test_access(): void {
        $this->resetAfterTest();
        $d = $this->build();
        $g = $this->getDataGenerator();
        $group = $g->create_group(['courseid' => $d['course']->id]);
        $g->create_group_member(['groupid' => $group->id, 'userid' => $d['ana']->id]);
        $g->create_group_member(['groupid' => $group->id, 'userid' => $d['tutor']->id]);

        $this->setUser($d['tutor']);
        $this->assertTrue(access::can_view_groups(\context_course::instance($d['course']->id)));
        $all = access::visible_learners($d['action']);
        $this->assertContains((int) $d['ana']->id, $all);
        $this->assertContains((int) $d['bea']->id, $all);

        $this->setUser($d['ana']);
        $this->assertFalse(access::can_view_groups(\context_course::instance($d['course']->id)));
        $this->assertTrue(access::can_view_learner($d['action'], $d['ana']->id));
        $this->assertFalse(access::can_view_learner($d['action'], $d['bea']->id));

        $teacher2 = $g->create_and_enrol($d['course'], 'teacher');
        $this->setUser($teacher2);
        $this->assertTrue(access::can_view_groups(\context_course::instance($d['course']->id)));

        $this->setAdminUser();
        $this->assertTrue(access::is_admin_reader());
        $this->assertCount(2, access::visible_learners($d['action']));
    }

    /**
     * The inspector role exists, is read only and the check detects writes.
     */
    public function test_inspector_role(): void {
        global $DB;
        $this->resetAfterTest();
        $roleid = inspector_role::ensure();
        $this->assertSame([], inspector_role::find_violations());
        $check = new check\inspector_readonly();
        $this->assertSame(\core\check\result::OK, $check->get_result()->get_status());

        assign_capability('moodle/course:update', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        assign_capability('moodle/site:config', CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        $violations = inspector_role::find_violations();
        $this->assertArrayHasKey('moodle/course:update', $violations);
        $this->assertArrayHasKey('moodle/site:config', $violations);
        $this->assertSame(\core\check\result::ERROR, $check->get_result()->get_status());

        $DB->delete_records('role', ['id' => $roleid]);
        $this->assertSame(\core\check\result::WARNING, $check->get_result()->get_status());
    }

    /**
     * Tutor indicators: no access, pace and overdue activities.
     */
    public function test_indicators(): void {
        $this->resetAfterTest();
        $d = $this->build();
        $now = time();
        $ind = indicators::for_learners($d['action'], [$d['ana']->id, $d['bea']->id], $now, 7);
        $this->assertTrue($ind[$d['ana']->id]['noaccess']);
        $this->assertTrue($ind[$d['bea']->id]['noaccess']);
        // Ten of twenty days elapsed (50 %), Ana completed 1 of 2 activities (50 %): on pace; Bea at 0 %: behind.
        $this->assertFalse($ind[$d['ana']->id]['behind']);
        $this->assertTrue($ind[$d['bea']->id]['behind']);
        $this->assertFalse($ind[$d['ana']->id]['noaccess'] && false);
        $recent = indicators::for_learners($d['action'], [$d['ana']->id], $d['day'] + 3600, 7);
        $this->assertFalse($recent[$d['ana']->id]['noaccess']);
        $this->assertNull(indicators::expected_percent((object) ['startplanned' => 0, 'endplanned' => 0, 'startreal' => 0,
            'endreal' => 0], $now));
        $this->assertSame([], indicators::for_learners($d['action'], []));
    }

    /**
     * Duration and grade formatting.
     */
    public function test_presenter_formats(): void {
        $this->assertSame('1:30', presenter::duration(5400));
        $this->assertSame('0:00', presenter::duration(0));
        $this->assertSame('-', presenter::grade(null));
    }
}
