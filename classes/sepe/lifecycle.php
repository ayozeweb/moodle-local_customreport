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
 * Lifecycle rules of a training action (Annex V.A, data model points 11-16).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\sepe;

/**
 * Availability, tracking phases and deletion rules.
 */
class lifecycle {
    /** @var int Days after the end of the action during which it must stay available. */
    const AVAILABLE_AFTER_END = 30;

    /** @var int Days after the end during which tracking takes place. */
    const TRACKING_AFTER_END = 90;

    /** @var string Tracking before the start: authorisation data only. */
    const AUTHORISATION = 'authorisation';

    /** @var string From the start to the end: start and progress data. */
    const DEVELOPMENT = 'development';

    /** @var string Up to 90 days after the end: results. */
    const CLOSING = 'closing';

    /** @var string Beyond 90 days: no longer subject to tracking. */
    const CLOSED = 'closed';

    /**
     * Tracking phase.
     *
     * @param int $start Start date (timestamp).
     * @param int $end End date (timestamp).
     * @param int $now
     * @return string
     */
    public static function phase(int $start, int $end, int $now): string {
        if ($start > 0 && $now < $start) {
            return self::AUTHORISATION;
        }
        if ($end <= 0 || $now <= $end + DAYSECS) {
            return self::DEVELOPMENT;
        }
        return $now <= $end + self::TRACKING_AFTER_END * DAYSECS ? self::CLOSING : self::CLOSED;
    }

    /**
     * Whether personal tracking data (tutors, use, participants) must be reported.
     *
     * @param string $phase
     * @return bool
     */
    public static function reports_tracking_data(string $phase): bool {
        return $phase !== self::AUTHORISATION;
    }

    /**
     * Whether the action can be deleted: before it starts (e.g. test actions) or once the availability
     * period (30 natural days after the end) is over.
     *
     * @param int $situation Situation code (10-50).
     * @param int $start
     * @param int $end
     * @param int $now
     * @return bool
     */
    public static function may_delete(int $situation, int $start, int $end, int $now): bool {
        if ($situation < 30 && $start > $now) {
            return true;
        }
        return $end > 0 && $now > $end + self::AVAILABLE_AFTER_END * DAYSECS;
    }

    /**
     * Whether the action has started according to its situation or dates.
     *
     * @param int $situation
     * @param int $start
     * @param int $now
     * @return bool
     */
    public static function has_started(int $situation, int $start, int $now): bool {
        if ($situation === 50) {
            return false;
        }
        return $situation >= 30 || ($start > 0 && $start <= $now);
    }
}
