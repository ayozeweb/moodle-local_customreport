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
 * Final result code of a participant (data model B12.1.4.6.1).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\sepe;

/**
 * Computes RESULTADO_FINAL, CALIFICACION_FINAL and PUNTUACION_FINAL.
 */
class result_calculator {
    /** @var int Started. */
    const STARTED = 0;
    /** @var int Dropped out for a job placement. */
    const DROP_PLACEMENT = 1;
    /** @var int Dropped out for other reasons. */
    const DROP_OTHER = 2;
    /** @var int Finished with a positive evaluation. */
    const PASSED = 3;
    /** @var int Finished with a negative evaluation. */
    const FAILED = 4;
    /** @var int Finished without evaluation. */
    const NOT_EVALUATED = 5;

    /**
     * Result code.
     *
     * @param bool $dropped
     * @param string $causecode Cause code of the withdrawal ('placement' counts as a job placement).
     * @param bool $allpassed Every module passed.
     * @param bool $failed Some module failed in every available call.
     * @param bool $ended The action has ended.
     * @return int
     */
    public static function code(bool $dropped, string $causecode, bool $allpassed, bool $failed, bool $ended): int {
        if ($dropped) {
            return $causecode === 'placement' ? self::DROP_PLACEMENT : self::DROP_OTHER;
        }
        if ($allpassed) {
            return self::PASSED;
        }
        if ($failed) {
            return self::FAILED;
        }
        return $ended ? self::NOT_EVALUATED : self::STARTED;
    }

    /**
     * Value x100 as an integer (7.6 becomes 760), as the data model requires.
     *
     * @param float $value
     * @return int
     */
    public static function times100(float $value): int {
        return (int) round($value * 100);
    }

    /**
     * Final score: never below 5 nor below the final test (data model B12.1.4.6.3).
     *
     * @param float $score Weighted score.
     * @param float $final Final test.
     * @return float
     */
    public static function floor_score(float $score, float $final): float {
        return max($score, $final, 5.0);
    }

    /**
     * Hours as the data model rounds them: a fraction up to 0.5 rounds down, above 0.5 rounds up.
     *
     * @param int $seconds
     * @return int
     */
    public static function hours(int $seconds): int {
        return (int) ceil($seconds / HOURSECS - 0.5);
    }
}
