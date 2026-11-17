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
 * Tests for the Report builder datasources.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport;

use local_customreport\local\action_manager;
use local_customreport\local\structure_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/reportbuilder/tests/helpers.php');

/**
 * Tests for the datasources.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reportbuilder\datasource\progress::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(reportbuilder\datasource\usage::class)]
final class reportbuilder_test extends \core_reportbuilder_testcase {
    /**
     * The progress datasource returns the stored rows with the calculated percentage.
     */
    public function test_progress_datasource(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Report']);
        $actionid = action_manager::save((object) ['courseid' => $course->id, 'groupid' => 0]);
        $mf = structure_manager::add($actionid, 'MF', 'MF9', 'Nine', 10);
        $DB->insert_record('local_customreport_progress', (object) ['courseid' => $course->id, 'userid' => $user->id,
            'nodeid' => $mf, 'activitiestotal' => 4, 'activitiesdone' => 1, 'processavg' => 7.5, 'final1' => null, 'final2' => null,
            'timemodified' => time()]);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Progress', 'source' => reportbuilder\datasource\progress::class,
            'default' => 1]);
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);
        $row = array_values($content[0]);
        $this->assertContains('MF9', $row);
        $this->assertContains('25.0', $row);
        $this->assertContains('7.50', $row);
    }

    /**
     * The usage datasource returns hours and counters.
     */
    public function test_usage_datasource(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('local_customreport_usage', (object) ['userid' => $user->id, 'courseid' => $course->id, 'cmid' => 0,
            'component' => 'mod_forum', 'day' => 86400, 'events' => 3, 'accesses' => 2, 'participations' => 1, 'seconds' => 5400,
            'firstaccess' => 86500, 'lastaccess' => 86900]);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_reportbuilder');
        $report = $generator->create_report(['name' => 'Usage', 'source' => reportbuilder\datasource\usage::class, 'default' => 1]);
        $content = $this->get_custom_report_content($report->get('id'));
        $this->assertCount(1, $content);
        $row = array_values($content[0]);
        $this->assertContains('mod_forum', $row);
        $this->assertContains('1.50', $row);
    }
}
