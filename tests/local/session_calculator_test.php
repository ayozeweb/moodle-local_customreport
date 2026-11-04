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
 * Tests for the session algorithm.
 *
 * @package    local_customreport
 * @category   test
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Edge cases of the session and time estimation.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(session_calculator::class)]
final class session_calculator_test extends \basic_testcase {
    /** @var int Gap threshold used in the tests. */
    const GAP = 1800;
    /** @var int Tail used in the tests. */
    const TAIL = 300;

    /**
     * Build an event.
     *
     * @param int $time
     * @param int $cmid
     * @return array
     */
    private function ev(int $time, int $cmid = 1): array {
        return ['time' => $time, 'courseid' => 2, 'cmid' => $cmid, 'component' => 'mod_page'];
    }

    /**
     * Total seconds of the segments.
     *
     * @param array $result
     * @return int
     */
    private function total(array $result): int {
        return array_sum(array_column($result['segments'], 'seconds'));
    }

    /**
     * A user without accesses produces nothing.
     */
    public function test_no_events(): void {
        $result = session_calculator::process([], self::GAP, self::TAIL);
        $this->assertSame([], $result['segments']);
        $this->assertNull($result['open']);
        $this->assertNull(session_calculator::close(null, 100000, self::GAP, self::TAIL));
    }

    /**
     * A single event stays open and gets the tail when the session closes.
     */
    public function test_single_event(): void {
        $result = session_calculator::process([$this->ev(1000)], self::GAP, self::TAIL);
        $this->assertSame([], $result['segments']);
        $this->assertSame(1000, $result['open']['time']);
        $this->assertNull(session_calculator::close($result['open'], 1000 + self::GAP, self::GAP, self::TAIL));
        $segment = session_calculator::close($result['open'], 1000 + self::GAP + 1, self::GAP, self::TAIL);
        $this->assertSame(self::TAIL, $segment['seconds']);
    }

    /**
     * A gap exactly equal to the threshold belongs to the same session.
     */
    public function test_gap_exactly_at_threshold(): void {
        $result = session_calculator::process([$this->ev(0), $this->ev(self::GAP)], self::GAP, self::TAIL);
        $this->assertSame(self::GAP, $this->total($result));
    }

    /**
     * A gap one second above the threshold ends the session and adds the tail.
     */
    public function test_gap_above_threshold(): void {
        $result = session_calculator::process([$this->ev(0), $this->ev(self::GAP + 1)], self::GAP, self::TAIL);
        $this->assertSame(self::TAIL, $this->total($result));
        $this->assertSame(self::GAP + 1, $result['open']['time']);
    }

    /**
     * Duplicated events (same time) do not add extra time.
     */
    public function test_duplicate_events(): void {
        $result = session_calculator::process(
            [$this->ev(0), $this->ev(0), $this->ev(0), $this->ev(60)],
            self::GAP,
            self::TAIL
        );
        $this->assertSame(60, $this->total($result));
    }

    /**
     * Time between two events is attributed to the area of the earlier event.
     */
    public function test_area_attribution(): void {
        $result = session_calculator::process(
            [$this->ev(0, 10), $this->ev(100, 20), $this->ev(250, 30)],
            self::GAP,
            self::TAIL
        );
        $byarea = array_column($result['segments'], 'seconds', 'cmid');
        $this->assertSame([10 => 100, 20 => 150], $byarea);
    }

    /**
     * An open session continues across runs (incremental processing).
     */
    public function test_incremental_equals_batch(): void {
        $events = [$this->ev(0), $this->ev(600), $this->ev(1200), $this->ev(10000), $this->ev(10100)];
        $batch = session_calculator::process($events, self::GAP, self::TAIL);
        $first = session_calculator::process(array_slice($events, 0, 3), self::GAP, self::TAIL);
        $second = session_calculator::process(array_slice($events, 3), self::GAP, self::TAIL, $first['open']);
        $this->assertSame($this->total($batch), $this->total($first) + $this->total($second));
        $this->assertSame($batch['open'], $second['open']);
    }

    /**
     * An out-of-order event does not produce negative time.
     */
    public function test_out_of_order_event(): void {
        $result = session_calculator::process([$this->ev(500), $this->ev(400), $this->ev(600)], self::GAP, self::TAIL);
        $this->assertSame(100, $this->total($result));
    }

    /**
     * Days follow the local timezone, also on a daylight saving change day (23 hours).
     */
    public function test_day_start_dst(): void {
        $tz = new \DateTimeZone('Europe/Madrid');
        // Sunday 2026-03-29 is the spring forward day in Madrid: midnight is 2026-03-28 23:00 UTC.
        $midnight = gmmktime(23, 0, 0, 3, 28, 2026);
        $this->assertSame($midnight, session_calculator::day_start(gmmktime(12, 0, 0, 3, 29, 2026), $tz));
        $next = session_calculator::day_start(gmmktime(12, 0, 0, 3, 30, 2026), $tz);
        $this->assertSame(23 * HOURSECS, $next - $midnight);
        // An event just after local midnight belongs to the new day.
        $this->assertSame($next, session_calculator::day_start($next + 1, $tz));
        $this->assertSame($midnight, session_calculator::day_start($next - 1, $tz));
    }

    /**
     * A session crossing local midnight attributes the time to the day of the earlier event.
     */
    public function test_session_across_midnight(): void {
        $tz = new \DateTimeZone('UTC');
        $day = gmmktime(0, 0, 0, 6, 1, 2026);
        $result = session_calculator::process([$this->ev($day - 60), $this->ev($day + 60)], self::GAP, self::TAIL, null, $tz);
        $this->assertCount(1, $result['segments']);
        $this->assertSame($day - DAYSECS, $result['segments'][0]['day']);
        $this->assertSame(120, $result['segments'][0]['seconds']);
    }
}
