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
 * Tests for the institutional images.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport;

use local_customreport\local\branding;

/**
 * Tests for branding.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(branding::class)]
final class branding_test extends \advanced_testcase {
    /**
     * Uploaded logos are returned with their URL and alternative text.
     */
    public function test_logos(): void {
        $this->resetAfterTest();
        $this->assertSame([], branding::logos());
        $this->assertSame(0, branding::count());
        $context = \context_system::instance();
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id, 'component' => 'local_customreport', 'filearea' => 'logo2', 'itemid' => 0,
            'filepath' => '/', 'filename' => 'logo.png'], 'png-bytes');
        set_config('logoalt2', 'Employment service', 'local_customreport');
        $logos = branding::logos();
        $this->assertCount(1, $logos);
        $expected = '/pluginfile.php/' . $context->id . '/local_customreport/logo2/0/logo.png';
        $this->assertStringContainsString($expected, $logos[0]['url']);
        $this->assertSame('Employment service', $logos[0]['alt']);
    }

    /**
     * Unknown file areas are not served.
     */
    public function test_pluginfile_rejects_other_areas(): void {
        $this->resetAfterTest();
        $this->assertFalse(local_customreport_pluginfile(null, null, \context_system::instance(), 'secret', ['x.png'], false));
        $this->assertFalse(local_customreport_pluginfile(null, null, \context_system::instance(), 'logo9', ['x.png'], false));
    }
}
