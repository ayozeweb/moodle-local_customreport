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
 * Tests for activity roles and the profile fields wizard.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Tests for cmrole_manager and profile_field_wizard.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\local\cmrole_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\local\profile_field_wizard::class)]
final class cmrole_and_wizard_test extends \advanced_testcase {
    /**
     * Roles are stored, updated and validated.
     */
    public function test_roles(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        cmrole_manager::set_role($course->id, $page->cmid, 'process');
        $this->assertSame('process', cmrole_manager::get_roles($course->id)[$page->cmid]);
        cmrole_manager::set_role($course->id, $page->cmid, 'final1');
        $this->assertSame('final1', cmrole_manager::get_roles($course->id)[$page->cmid]);
        cmrole_manager::delete_for_cm($page->cmid);
        $this->assertSame([], cmrole_manager::get_roles($course->id));
        $this->expectException(\coding_exception::class);
        cmrole_manager::set_role($course->id, $page->cmid, 'bogus');
    }

    /**
     * Category suggestions never fail without format_customtiles.
     */
    public function test_suggestions(): void {
        $this->assertSame('final1', cmrole_manager::suggest_from_category('po'));
        $this->assertNull(cmrole_manager::suggest_from_category('ZZ'));
        $this->assertIsBool(cmrole_manager::customtiles_available());
    }

    /**
     * The wizard is idempotent and creates all fields.
     */
    public function test_wizard(): void {
        global $DB;
        $this->resetAfterTest();
        foreach (['learner', 'tutor'] as $audience) {
            $expected = count(profile_field_wizard::definitions($audience));
            $this->assertCount($expected, profile_field_wizard::missing_fields($audience));
            $this->assertSame($expected, profile_field_wizard::create_fields($audience));
            $this->assertSame(0, profile_field_wizard::create_fields($audience));
            $this->assertSame([], profile_field_wizard::missing_fields($audience));
        }
        $field = $DB->get_record('user_info_field', ['shortname' => 'cr_tutor_teachingcompetence']);
        $this->assertSame('menu', $field->datatype);
        $this->assertCount(6, explode("\n", $field->param1));
    }
}
