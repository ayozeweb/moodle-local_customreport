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
 * Identity documents (data model B11.1.8.1.1 and B12.1.1).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\sepe;

/**
 * Parsing and control letter calculation for DNI/NIE documents.
 */
class identity {
    /** @var string Control letters by remainder of the number modulo 23 (data model table). */
    const LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    /**
     * Control letter of a document.
     *
     * @param string $type D (DNI), E (NIE) or G.
     * @param string $number Digits of a DNI/G, or X/Y/Z followed by 7 digits for an NIE.
     * @return string|null Null when the number is not valid for the type.
     */
    public static function letter(string $type, string $number): ?string {
        $number = trim($number);
        if ($type === 'E') {
            if (!preg_match('/^([XYZ])(\d{1,7})$/i', $number, $m)) {
                return null;
            }
            $number = strpos('XYZ', strtoupper($m[1])) . str_pad($m[2], 7, '0', STR_PAD_LEFT);
        } else if (!in_array($type, ['D', 'G'], true) || !ctype_digit($number)) {
            return null;
        }
        return self::LETTERS[(int) $number % 23];
    }

    /**
     * Parse a document written as users usually do (12345678Z, X1234567L).
     *
     * @param string $text
     * @return array|null ['type', 'number' (10 characters), 'letter'] or null when it is not a valid DNI/NIE.
     */
    public static function parse(string $text): ?array {
        $text = strtoupper(preg_replace('/[\s.\-]/', '', $text));
        if (preg_match('/^(\d{1,8})([A-Z])$/', $text, $m)) {
            $type = 'D';
            $digits = str_pad($m[1], 8, '0', STR_PAD_LEFT);
            $given = $m[2];
        } else if (preg_match('/^([XYZ])(\d{1,7})([A-Z])$/', $text, $m)) {
            $type = 'E';
            $digits = $m[1] . str_pad($m[2], 7, '0', STR_PAD_LEFT);
            $given = $m[3];
        } else {
            return null;
        }
        if (self::letter($type, $digits) !== $given) {
            return null;
        }
        // The data model writes the 10 character number as "bb" + digits, with b standing for a blank.
        return ['type' => $type, 'number' => '  ' . $digits, 'letter' => $given];
    }
}
