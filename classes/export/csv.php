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
 * CSV export of the follow-up report.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\export;

/**
 * Renders the follow-up report as CSV (one row per learner, columns defined by the regulatory profile).
 */
class csv {
    /**
     * Column definitions: profile column name => [language string, value callback].
     *
     * @return array
     */
    public static function columns(): array {
        $hours = fn($seconds) => number_format($seconds / HOURSECS, 2, '.', '');
        $date = fn($time) => $time ? userdate($time, '%Y-%m-%d %H:%M', 99, false, false) : '';
        return [
            'fullname' => ['fullname', fn($l) => $l['fullname']],
            'firstaccess' => ['firstaccess', fn($l) => $date($l['firstaccess'])],
            'lastaccess' => ['lastaccess', fn($l) => $date($l['lastaccess'])],
            'accesses' => ['accesses', fn($l) => $l['accesses']],
            'participations' => ['participations', fn($l) => $l['participations']],
            'timespent' => ['timespenthours', fn($l) => $hours($l['seconds'])],
            'activitiesdone' => ['activitiesdone', fn($l) => $l['done'] . '/' . $l['total']],
            'processaverage' => ['processaverage', fn($l) => $l['processavg'] === null ? '' : round($l['processavg'], 2)],
            'finalgrade' => ['finalgrade', fn($l) => $l['final1'] === null ? '' : round($l['final1'], 2)],
            'finalscore' => ['finalscore', fn($l) => $l['score'] === null ? '' : $l['score']],
            'call' => ['callnumber', fn($l) => $l['call'] ?? ''],
            'status' => ['status', fn($l) => get_string('status_' . $l['status'], 'local_customreport')],
        ];
    }

    /**
     * Render.
     *
     * @param array $data Output of \local_customreport\report\followup::build().
     * @return string CSV content with UTF-8 BOM.
     */
    public static function render(array $data): string {
        $definitions = self::columns();
        $names = array_values(array_filter($data['profile']->columns, fn($c) => isset($definitions[$c])));
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_map(fn($c) => get_string('col_' . $definitions[$c][0], 'local_customreport'), $names), ';', '"', '\\');
        foreach ($data['learners'] as $learner) {
            fputcsv($out, array_map(fn($c) => $definitions[$c][1]($learner), $names), ';', '"', '\\');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return "\xEF\xBB\xBF" . $csv;
    }
}
