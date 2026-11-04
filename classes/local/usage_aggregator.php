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
 * Incremental aggregation of logstore_standard_log into usage tables.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Reads new log rows since the last run, estimates time and counts accesses/participation.
 * It never scans the whole log: a watermark (log id) and a cursor per user are stored.
 */
class usage_aggregator {
    /** @var int Log rows read per chunk. */
    const CHUNK = 20000;

    /** @var string[] Events that count as a platform access. */
    const LOGIN_EVENTS = ['\core\event\user_loggedin'];

    /** @var string[] Events that count as participation (posts, chat, messages). */
    const PARTICIPATION_EVENTS = [
        '\mod_forum\event\post_created',
        '\mod_forum\event\discussion_created',
        '\mod_chat\event\message_sent',
        '\core\event\message_sent',
    ];

    /** @var string[] Enrolment events mapped to our event type. */
    const ENROL_EVENTS = [
        '\core\event\user_enrolment_created' => 'create',
        '\core\event\user_enrolment_updated' => 'update',
        '\core\event\user_enrolment_deleted' => 'delete',
    ];

    /** @var \DateTimeZone */
    private $tz;

    /** @var int */
    private $gap;

    /** @var int */
    private $tail;

    /** @var array|null Courses whose usage is stored (null = all). */
    private $courses;

    /**
     * Constructor.
     *
     * @param int|null $gap Session gap (defaults to the setting).
     * @param int|null $tail Session tail (defaults to the setting).
     * @param \DateTimeZone|null $tz Timezone for days (defaults to the server timezone).
     */
    public function __construct(?int $gap = null, ?int $tail = null, ?\DateTimeZone $tz = null) {
        $this->gap = $gap ?? (int) (get_config('local_customreport', 'sessiongap') ?: 30 * MINSECS);
        $this->tail = $tail ?? (int) (get_config('local_customreport', 'sessiontail') ?: 5 * MINSECS);
        $this->tz = $tz ?? \core_date::get_server_timezone_object();
    }

    /**
     * Process new log rows.
     *
     * @param int|null $now Current time (for tests).
     * @return int Number of log rows read.
     */
    public function run(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $horizon = $now - $this->gap;
        $watermark = (int) get_config('local_customreport', 'usagewatermark');
        $this->courses = $DB->get_fieldset_select('local_customreport_action', 'DISTINCT courseid', '1=1');
        $total = 0;

        do {
            $rows = $DB->get_records_select(
                'logstore_standard_log',
                'id > :wm AND timecreated <= :horizon AND userid > 0',
                ['wm' => $watermark, 'horizon' => $horizon],
                'id ASC',
                'id, userid, relateduserid, courseid, contextlevel, contextinstanceid, component, eventname, '
                . 'action, crud, edulevel, timecreated',
                0,
                self::CHUNK
            );
            if (!$rows) {
                break;
            }
            $transaction = $DB->start_delegated_transaction();
            $this->process_rows($rows);
            $watermark = (int) max(array_keys($rows));
            set_config('usagewatermark', $watermark, 'local_customreport');
            $transaction->allow_commit();
            $total += count($rows);
        } while (count($rows) === self::CHUNK);

        $this->close_idle_sessions($horizon);
        return $total;
    }

    /**
     * Process a chunk of log rows.
     *
     * @param \stdClass[] $rows
     */
    private function process_rows(array $rows): void {
        global $DB;
        $byuser = [];
        $counts = [];
        foreach ($rows as $row) {
            if (isset(self::ENROL_EVENTS[$row->eventname])) {
                $this->record_enrol($row);
                continue;
            }
            $islogin = in_array($row->eventname, self::LOGIN_EVENTS, true);
            if ((int) $row->edulevel === 0 && !$islogin) {
                continue;
            }
            $area = $this->area($row);
            $byuser[$row->userid][] = ['time' => (int) $row->timecreated] + $area;
            if (!$this->is_tracked((int) $area['courseid'])) {
                continue;
            }
            $day = session_calculator::day_start((int) $row->timecreated, $this->tz);
            $key = $row->userid . '|' . $area['courseid'] . '|' . $area['cmid'] . '|' . $area['component'] . '|' . $day;
            $counts[$key] = $counts[$key] ?? ['userid' => (int) $row->userid, 'courseid' => $area['courseid'],
                'cmid' => $area['cmid'], 'component' => $area['component'], 'day' => $day, 'events' => 0,
                'accesses' => 0, 'participations' => 0, 'seconds' => 0, 'first' => PHP_INT_MAX, 'last' => 0];
            $counts[$key]['events']++;
            if ($islogin || ($row->crud === 'r' && $row->action === 'viewed')) {
                $counts[$key]['accesses']++;
            }
            if (in_array($row->eventname, self::PARTICIPATION_EVENTS, true)) {
                $counts[$key]['participations']++;
            }
            $counts[$key]['first'] = min($counts[$key]['first'], (int) $row->timecreated);
            $counts[$key]['last'] = max($counts[$key]['last'], (int) $row->timecreated);
        }

        foreach ($byuser as $userid => $events) {
            usort($events, fn($a, $b) => $a['time'] <=> $b['time']);
            $cursor = $DB->get_record('local_customreport_cursor', ['userid' => $userid]);
            $open = $cursor && $cursor->isopen ? ['time' => (int) $cursor->lasttime, 'courseid' => (int) $cursor->lastcourseid,
                'cmid' => (int) $cursor->lastcmid, 'component' => $cursor->lastcomponent] : null;
            $result = session_calculator::process($events, $this->gap, $this->tail, $open, $this->tz);
            foreach ($result['segments'] as $segment) {
                $this->add_seconds($counts, $userid, $segment);
            }
            $this->save_cursor($cursor, $userid, $result['open']);
        }

        foreach ($counts as $count) {
            $this->flush($count);
        }
    }

    /**
     * Finalise sessions that can no longer be extended.
     *
     * @param int $horizon
     */
    private function close_idle_sessions(int $horizon): void {
        global $DB;
        $cursors = $DB->get_records_select(
            'local_customreport_cursor',
            'isopen = 1 AND lasttime + :gap < :horizon',
            ['gap' => $this->gap, 'horizon' => $horizon]
        );
        foreach ($cursors as $cursor) {
            $open = ['time' => (int) $cursor->lasttime, 'courseid' => (int) $cursor->lastcourseid,
                'cmid' => (int) $cursor->lastcmid, 'component' => $cursor->lastcomponent];
            $segment = session_calculator::close($open, $horizon, $this->gap, $this->tail, $this->tz);
            if ($segment) {
                $counts = [];
                $this->add_seconds($counts, (int) $cursor->userid, $segment);
                foreach ($counts as $count) {
                    $this->flush($count);
                }
            }
            $DB->set_field('local_customreport_cursor', 'isopen', 0, ['id' => $cursor->id]);
        }
    }

    /**
     * Add the seconds of a segment to the in-memory counters.
     *
     * @param array $counts
     * @param int $userid
     * @param array $segment
     */
    private function add_seconds(array &$counts, int $userid, array $segment): void {
        if (!$this->is_tracked((int) $segment['courseid'])) {
            return;
        }
        $key = $userid . '|' . $segment['courseid'] . '|' . $segment['cmid'] . '|' . $segment['component'] . '|' . $segment['day'];
        $counts[$key] = $counts[$key] ?? ['userid' => $userid, 'courseid' => $segment['courseid'], 'cmid' => $segment['cmid'],
            'component' => $segment['component'], 'day' => $segment['day'], 'events' => 0, 'accesses' => 0,
            'participations' => 0, 'seconds' => 0, 'first' => PHP_INT_MAX, 'last' => 0];
        $counts[$key]['seconds'] += $segment['seconds'];
    }

    /**
     * Add the counters to the database row (insert or increment).
     *
     * @param array $count
     */
    private function flush(array $count): void {
        global $DB;
        $conditions = ['userid' => $count['userid'], 'courseid' => $count['courseid'], 'cmid' => $count['cmid'],
            'component' => $count['component'], 'day' => $count['day']];
        $record = $DB->get_record('local_customreport_usage', $conditions);
        $first = $count['first'] === PHP_INT_MAX ? 0 : $count['first'];
        if ($record) {
            $record->events += $count['events'];
            $record->accesses += $count['accesses'];
            $record->participations += $count['participations'];
            $record->seconds += $count['seconds'];
            if ($first && (!$record->firstaccess || $first < $record->firstaccess)) {
                $record->firstaccess = $first;
            }
            $record->lastaccess = max($record->lastaccess, $count['last']);
            $DB->update_record('local_customreport_usage', $record);
        } else {
            $DB->insert_record('local_customreport_usage', (object) ($conditions + [
                'events' => $count['events'], 'accesses' => $count['accesses'],
                'participations' => $count['participations'], 'seconds' => $count['seconds'],
                'firstaccess' => $first, 'lastaccess' => $count['last']]));
        }
    }

    /**
     * Store the open session state of a user.
     *
     * @param \stdClass|false $cursor
     * @param int $userid
     * @param array|null $open
     */
    private function save_cursor($cursor, int $userid, ?array $open): void {
        global $DB;
        if ($open === null) {
            return;
        }
        $record = (object) ['userid' => $userid, 'lasttime' => $open['time'], 'lastcourseid' => $open['courseid'],
            'lastcmid' => $open['cmid'], 'lastcomponent' => $open['component'], 'isopen' => 1];
        if ($cursor) {
            $record->id = $cursor->id;
            $DB->update_record('local_customreport_cursor', $record);
        } else {
            $DB->insert_record('local_customreport_cursor', $record);
        }
    }

    /**
     * Store an enrolment event once.
     *
     * @param \stdClass $row
     */
    private function record_enrol(\stdClass $row): void {
        global $DB;
        $userid = (int) $row->relateduserid;
        if (
            !$userid || !$this->is_tracked((int) $row->courseid) || $DB->record_exists(
                'local_customreport_enrol',
                ['logid' => $row->id]
            )
        ) {
            return;
        }
        $DB->insert_record('local_customreport_enrol', (object) ['courseid' => $row->courseid, 'userid' => $userid,
            'eventtype' => self::ENROL_EVENTS[$row->eventname], 'eventtime' => $row->timecreated, 'logid' => $row->id,
            'causeid' => 0]);
    }

    /**
     * Area (course, activity, component) of a log row.
     *
     * @param \stdClass $row
     * @return array
     */
    private function area(\stdClass $row): array {
        $courseid = (int) $row->courseid;
        if ($courseid === SITEID) {
            $courseid = 0;
        }
        $cmid = (int) $row->contextlevel === CONTEXT_MODULE ? (int) $row->contextinstanceid : 0;
        return ['courseid' => $courseid, 'cmid' => $cmid, 'component' => (string) $row->component];
    }

    /**
     * Whether the usage of a course is stored: the platform (0) and courses with a configured action.
     *
     * @param int $courseid
     * @return bool
     */
    private function is_tracked(int $courseid): bool {
        return $courseid === 0 || in_array($courseid, $this->courses, false);
    }
}
