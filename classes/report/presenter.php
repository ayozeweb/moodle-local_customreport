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
 * Prepares report data for templates and charts.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\report;

use local_customreport\local\branding;

/**
 * Converts the output of followup::build() into Mustache context and Chart API charts.
 */
class presenter {
    /**
     * Format seconds as H:MM.
     *
     * @param int $seconds
     * @return string
     */
    public static function duration(int $seconds): string {
        return sprintf('%d:%02d', intdiv($seconds, HOURSECS), intdiv($seconds % HOURSECS, MINSECS));
    }

    /**
     * Format a nullable grade.
     *
     * @param float|null $value
     * @return string
     */
    public static function grade(?float $value): string {
        return $value === null ? '-' : format_float($value, 2);
    }

    /**
     * Template context of the group report.
     *
     * @param array $data
     * @param \renderer_base $output
     * @param bool $withcharts
     * @return array
     */
    public static function context(array $data, \renderer_base $output, bool $withcharts = true): array {
        $s = $data['summary'];
        $str = fn($id) => get_string($id, 'local_customreport');
        $summary = [
            [$str('learners'), $s['learners']],
            [$str('started'), $s['started'] . ' (' . $s['startedpercent'] . ' %)'],
            [$str('completed'), $s['completed'] . ' (' . $s['completedpercent'] . ' %)'],
            [$str('dropped'), $s['dropped'] . ' (' . $s['droppedpercent'] . ' %)'],
            [$str('totaltime'), self::duration($s['totalseconds'])],
            [$str('avgtime'), self::duration($s['avgseconds'])],
            [$str('col_accesses'), $s['accesses']],
            [$str('col_participations'), $s['participations']],
            [$str('avgscore'), self::grade($s['avgscore'])],
            [$str('passall'), $s['passall'] . ' (' . $s['passallpercent'] . ' %)'],
        ];
        if ($s['passfct'] !== null) {
            $summary[] = [$str('passfct'), $s['passfct'] . ' (' . $s['passfctpercent'] . ' %)'];
        }
        return [
            'actionname' => format_string($data['action']->name ?: $data['action']->actioncode),
            'generated' => userdate(time(), get_string('strftimedatetimeshort', 'langconfig')),
            'logos' => branding::logos(),
            'summary' => array_map(fn($r) => ['label' => $r[0], 'value' => $r[1]], $summary),
            'nodes' => array_map(fn($n) => ['code' => $n['code'], 'name' => format_string($n['name']), 'hours' => $n['hours'],
                'passed' => $n['passed'], 'passedpercent' => $n['passedpercent'], 'time' => self::duration($n['seconds']),
                'avgtime' => self::duration($n['avgseconds'])], $data['nodes']),
            'areas' => array_map(fn($a) => ['name' => $a['component'], 'accesses' => $a['accesses'],
                'accessespercent' => $a['accessespercent'], 'time' => self::duration($a['seconds']),
                'participations' => $a['participations']], $data['areas']),
            'learners' => array_map(fn($l) => ['fullname' => $l['fullname'], 'status' => get_string(
                'status_' . $l['status'],
                'local_customreport'
            ), 'accesses' => $l['accesses'], 'participations' => $l['participations'],
                'time' => self::duration($l['seconds']), 'done' => $l['done'] . '/' . $l['total'], 'percent' => $l['percent'],
                'process' => self::grade($l['processavg']), 'final' => self::grade($l['final1']),
                'score' => self::grade($l['score']), 'call' => $l['call'] ?? '-'], $data['learners']),
            'charts' => $withcharts ? self::charts($data, $output) : [],
        ];
    }

    /**
     * Charts of the group report.
     *
     * @param array $data
     * @param \renderer_base $output
     * @return array
     */
    public static function charts(array $data, \renderer_base $output): array {
        $charts = [];
        if ($data['nodes']) {
            $labels = array_map(fn($n) => $n['code'] ?: $n['name'], $data['nodes']);
            $bar = new \core\chart_bar();
            $bar->set_labels($labels);
            $bar->add_series(new \core\chart_series(
                get_string('avgtimehours', 'local_customreport'),
                array_map(fn($n) => round($n['avgseconds'] / HOURSECS, 2), $data['nodes'])
            ));
            $charts[] = ['title' => get_string('chart_time', 'local_customreport'), 'html' => $output->render($bar)];

            $pass = new \core\chart_bar();
            $pass->set_labels($labels);
            $pass->add_series(new \core\chart_series(
                get_string('passedpercent', 'local_customreport'),
                array_map(fn($n) => $n['passedpercent'], $data['nodes'])
            ));
            $charts[] = ['title' => get_string('chart_pass', 'local_customreport'), 'html' => $output->render($pass)];
        }
        if ($data['areas']) {
            $pie = new \core\chart_pie();
            $pie->set_labels(array_column($data['areas'], 'component'));
            $pie->add_series(new \core\chart_series(
                get_string('col_accesses', 'local_customreport'),
                array_column($data['areas'], 'accesses')
            ));
            $charts[] = ['title' => get_string('chart_areas', 'local_customreport'), 'html' => $output->render($pie)];
        }
        return $charts;
    }
}
