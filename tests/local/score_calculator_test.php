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
 * Tests for the 30/70 score calculation.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Tests for score_calculator.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(score_calculator::class)]
final class score_calculator_test extends \basic_testcase {
    /**
     * Default SEPE profile.
     *
     * @return \stdClass
     */
    private function profile(): \stdClass {
        return json_decode(file_get_contents(__DIR__ . '/../../presets/sepe_sce_tms369.json'));
    }

    /**
     * 0.30 * process + 0.70 * final when the first call reaches 5.
     */
    public function test_first_call(): void {
        $result = score_calculator::calculate(8.0, 6.0, null, $this->profile());
        $this->assertEqualsWithDelta(6.6, $result['score'], 0.001);
        $this->assertSame(1, $result['call']);
        $this->assertTrue($result['passed']);
    }

    /**
     * Exactly the minimum counts.
     */
    public function test_minimum_boundary(): void {
        $result = score_calculator::calculate(5.0, 5.0, null, $this->profile());
        $this->assertEqualsWithDelta(5.0, $result['score'], 0.001);
        $this->assertTrue($result['passed']);
        $result = score_calculator::calculate(10.0, 4.99, null, $this->profile());
        $this->assertNull($result['score']);
        $this->assertFalse($result['passed']);
    }

    /**
     * Second call is used when the first failed.
     */
    public function test_second_call(): void {
        $result = score_calculator::calculate(6.0, 3.0, 7.0, $this->profile());
        $this->assertSame(2, $result['call']);
        $this->assertEqualsWithDelta(6.7, $result['score'], 0.001);
    }

    /**
     * A profile with a single call ignores the second one; weights are configurable.
     */
    public function test_profile_configuration(): void {
        $profile = $this->profile();
        $profile->attempts = 1;
        $this->assertNull(score_calculator::calculate(6.0, 3.0, 7.0, $profile)['score']);
        $profile->attempts = 2;
        $profile->weights = (object) ['process' => 0.5, 'final' => 0.5];
        $this->assertEqualsWithDelta(6.5, score_calculator::calculate(6.0, 7.0, null, $profile)['score'], 0.001);
    }

    /**
     * Without data there is no score.
     */
    public function test_missing_data(): void {
        $this->assertNull(score_calculator::calculate(null, 8.0, null, $this->profile())['score']);
        $this->assertNull(score_calculator::calculate(8.0, null, null, $this->profile())['score']);
        $this->assertNull(score_calculator::calculate(null, null, null, $this->profile())['call']);
    }
}
