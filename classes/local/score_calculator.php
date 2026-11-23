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
 * Final module score calculation.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Final score of a module: when the final test reaches the minimum, weights * process average + weights * final test.
 * Weights, minimum and pass mark come from the regulatory profile (default 0.30 / 0.70, minimum 5, pass mark 5).
 */
class score_calculator {
    /**
     * Compute the final score.
     *
     * @param float|null $process Average of process evaluation (0-10) or null when there is none.
     * @param float|null $final1 Final test, 1st call (0-10).
     * @param float|null $final2 Final test, 2nd call (0-10).
     * @param \stdClass $profile Decoded regulatory profile.
     * @return array ['score' => float|null, 'call' => 1|2|null, 'passed' => bool]
     */
    public static function calculate(?float $process, ?float $final1, ?float $final2, \stdClass $profile): array {
        $min = (float) $profile->minfinal;
        $attempts = (int) $profile->attempts;
        $call = null;
        $final = null;
        if ($final1 !== null && $final1 >= $min) {
            $call = 1;
            $final = $final1;
        } else if ($attempts >= 2 && $final2 !== null && $final2 >= $min) {
            $call = 2;
            $final = $final2;
        }
        if ($final === null || $process === null) {
            return ['score' => null, 'call' => $call, 'passed' => false];
        }
        $score = round($profile->weights->process * $process + $profile->weights->final * $final, 2);
        return ['score' => $score, 'call' => $call, 'passed' => $score >= (float) $profile->passmark];
    }
}
