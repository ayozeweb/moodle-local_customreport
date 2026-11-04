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

use local_customreport\privacy\provider;

/**
 * Tests for the privacy provider.
 *
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_customreport\privacy\provider::class)]
final class privacy_provider_test extends \advanced_testcase {
    /**
     * The reason string exists in both languages.
     */
    public function test_reason(): void {
        $this->assertTrue(get_string_manager()->string_exists(provider::get_reason(), 'local_customreport'));
    }
}
