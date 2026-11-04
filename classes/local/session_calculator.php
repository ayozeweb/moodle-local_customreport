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
 * Session and time calculation from a stream of events.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Pure (database free) estimation of time spent.
 *
 * Method: events of a user are ordered by time. If the gap between two consecutive events is less than or
 * equal to the threshold they belong to the same session and the gap is attributed to the area of the
 * earlier event. If the gap is greater than the threshold the session ends: the tail is attributed to the
 * area of the last event of the session and a new session starts. A lone event therefore counts the tail.
 * This is an estimate, not a measured presence.
 */
class session_calculator {
    /**
     * Compute time segments.
     *
     * @param array[] $events Each: ['time' => int, 'courseid' => int, 'cmid' => int, 'component' => string].
     *                       Must be sorted by time (equal times keep their order).
     * @param int $gap Session gap threshold in seconds.
     * @param int $tail Seconds added to the last event of each session.
     * @param array|null $open Last event of the previous run (still open) or null.
     * @param \DateTimeZone|null $tz Timezone used to assign seconds to local days.
     * @return array ['segments' => array[], 'open' => array|null]; segments aggregate seconds per area and day.
     */
    public static function process(array $events, int $gap, int $tail, ?array $open = null, ?\DateTimeZone $tz = null): array {
        $tz = $tz ?? new \DateTimeZone('UTC');
        $acc = [];
        $prev = $open;
        foreach ($events as $event) {
            if ($prev !== null) {
                $delta = max(0, $event['time'] - $prev['time']);
                self::add($acc, $prev, $delta <= $gap ? $delta : $tail, $tz);
                if ($event['time'] < $prev['time']) {
                    // Out-of-order event: do not move the clock back.
                    $event['time'] = $prev['time'];
                }
            }
            $prev = $event;
        }
        return ['segments' => array_values($acc), 'open' => $prev];
    }

    /**
     * Close an open session when no event can still extend it.
     *
     * @param array|null $open Last event of the open session.
     * @param int $horizon All events up to and including this time have been processed.
     * @param int $gap Session gap threshold.
     * @param int $tail Tail seconds.
     * @param \DateTimeZone|null $tz Timezone.
     * @return array|null Segment array [courseid, cmid, component, day, seconds] or null if still open.
     */
    public static function close(?array $open, int $horizon, int $gap, int $tail, ?\DateTimeZone $tz = null): ?array {
        if ($open === null || $open['time'] + $gap >= $horizon) {
            return null;
        }
        $acc = [];
        self::add($acc, $open, $tail, $tz ?? new \DateTimeZone('UTC'));
        return reset($acc) ?: null;
    }

    /**
     * Local midnight (start of the day) of a timestamp.
     *
     * @param int $time
     * @param \DateTimeZone $tz
     * @return int
     */
    public static function day_start(int $time, \DateTimeZone $tz): int {
        $date = (new \DateTimeImmutable('@' . $time))->setTimezone($tz);
        return $date->setTime(0, 0, 0)->getTimestamp();
    }

    /**
     * Add seconds to an accumulator keyed by area and day.
     *
     * @param array $acc
     * @param array $event
     * @param int $seconds
     * @param \DateTimeZone $tz
     */
    private static function add(array &$acc, array $event, int $seconds, \DateTimeZone $tz): void {
        $day = self::day_start($event['time'], $tz);
        $key = $event['courseid'] . '|' . $event['cmid'] . '|' . $event['component'] . '|' . $day;
        if (!isset($acc[$key])) {
            $acc[$key] = ['courseid' => $event['courseid'], 'cmid' => $event['cmid'],
                'component' => $event['component'], 'day' => $day, 'seconds' => 0];
        }
        $acc[$key]['seconds'] += $seconds;
    }
}
