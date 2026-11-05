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
 * Privacy provider.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_customreport\local\retention;

/**
 * Privacy provider for local_customreport.
 *
 * Follow-up data must be retained during the legal control period (until 90 days after the end of the
 * action). Erasure requests received during that period are recorded in local_customreport_deferred and
 * carried out by the purge_deferred task when the period ends.
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider {
    /** @var string[] Tables with a userid and a courseid holding follow-up data. */
    const TABLES = ['local_customreport_usage', 'local_customreport_progress', 'local_customreport_enrol',
        'local_customreport_slot'];

    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_customreport_usage', [
            'userid' => 'privacy:metadata:userid', 'courseid' => 'privacy:metadata:courseid',
            'cmid' => 'privacy:metadata:usage:cmid', 'day' => 'privacy:metadata:usage:day',
            'events' => 'privacy:metadata:usage:events', 'accesses' => 'privacy:metadata:usage:accesses',
            'participations' => 'privacy:metadata:usage:participations', 'seconds' => 'privacy:metadata:usage:seconds',
            'firstaccess' => 'privacy:metadata:usage:firstaccess', 'lastaccess' => 'privacy:metadata:usage:lastaccess',
        ], 'privacy:metadata:usage');
        $collection->add_database_table('local_customreport_progress', [
            'userid' => 'privacy:metadata:userid', 'courseid' => 'privacy:metadata:courseid',
            'activitiesdone' => 'privacy:metadata:progress:activitiesdone',
            'processavg' => 'privacy:metadata:progress:grades', 'final1' => 'privacy:metadata:progress:grades',
            'final2' => 'privacy:metadata:progress:grades',
        ], 'privacy:metadata:progress');
        $collection->add_database_table('local_customreport_enrol', [
            'userid' => 'privacy:metadata:userid', 'courseid' => 'privacy:metadata:courseid',
            'eventtype' => 'privacy:metadata:enrol:eventtype', 'eventtime' => 'privacy:metadata:enrol:eventtime',
            'causeid' => 'privacy:metadata:enrol:causeid',
        ], 'privacy:metadata:enrol');
        $collection->add_database_table('local_customreport_cursor', [
            'userid' => 'privacy:metadata:userid', 'lasttime' => 'privacy:metadata:cursor:lasttime',
        ], 'privacy:metadata:cursor');
        $collection->add_database_table('local_customreport_deferred', [
            'userid' => 'privacy:metadata:userid', 'courseid' => 'privacy:metadata:courseid',
            'timerequested' => 'privacy:metadata:deferred:timerequested',
        ], 'privacy:metadata:deferred');
        $collection->add_database_table('local_customreport_slot', [
            'userid' => 'privacy:metadata:userid', 'courseid' => 'privacy:metadata:courseid',
            'accesses' => 'privacy:metadata:usage:accesses', 'seconds' => 'privacy:metadata:usage:seconds',
        ], 'privacy:metadata:slot');
        $collection->add_database_table('local_customreport_idoc', [
            'userid' => 'privacy:metadata:userid', 'doctype' => 'privacy:metadata:idoc:doctype',
            'docnumber' => 'privacy:metadata:idoc:docnumber', 'docletter' => 'privacy:metadata:idoc:docletter',
        ], 'privacy:metadata:idoc');
        $collection->add_database_table('local_customreport_wslog', [
            'request' => 'privacy:metadata:wslog:request', 'response' => 'privacy:metadata:wslog:response',
        ], 'privacy:metadata:wslog');
        $collection->add_external_location_link('competent_administration', [
            'idoc' => 'privacy:metadata:idoc', 'progress' => 'privacy:metadata:progress',
        ], 'privacy:metadata:administration');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:messages');
        return $collection;
    }

    /**
     * Course contexts holding data of a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        foreach (self::TABLES as $table) {
            $contextlist->add_from_sql("SELECT ctx.id
                  FROM {{$table}} t
                  JOIN {context} ctx ON ctx.instanceid = t.courseid AND ctx.contextlevel = :level
                 WHERE t.userid = :userid", ['level' => CONTEXT_COURSE, 'userid' => $userid]);
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        foreach (self::TABLES as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {{$table}} WHERE courseid = :courseid",
                ['courseid' => $context->instanceid]
            );
        }
    }

    /**
     * Export the data of a user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $conditions = ['userid' => $userid, 'courseid' => $context->instanceid];
            $data = [
                'usage' => array_values(array_map(fn($r) => (object) [
                    'day' => \core_privacy\local\request\transform::date($r->day), 'cmid' => $r->cmid,
                    'component' => $r->component, 'events' => $r->events, 'accesses' => $r->accesses,
                    'participations' => $r->participations, 'seconds' => $r->seconds,
                ], $DB->get_records('local_customreport_usage', $conditions, 'day, id'))),
                'progress' => array_values(array_map(fn($r) => (object) [
                    'nodeid' => $r->nodeid, 'activitiestotal' => $r->activitiestotal, 'activitiesdone' => $r->activitiesdone,
                    'processavg' => $r->processavg, 'final1' => $r->final1, 'final2' => $r->final2,
                ], $DB->get_records('local_customreport_progress', $conditions, 'nodeid'))),
                'enrolments' => array_values(array_map(fn($r) => (object) [
                    'type' => $r->eventtype, 'time' => \core_privacy\local\request\transform::datetime($r->eventtime),
                    'causeid' => $r->causeid,
                ], $DB->get_records('local_customreport_enrol', $conditions, 'eventtime, id'))),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'local_customreport')], (object) $data);
        }
    }

    /**
     * Delete all the data of a context (deferred for courses still under follow-up).
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $userids = [];
        foreach (self::TABLES as $table) {
            $userids += $DB->get_records_menu($table, ['courseid' => $context->instanceid], '', 'userid, userid');
        }
        self::erase((int) $context->instanceid, array_keys($userids));
    }

    /**
     * Delete the data of a user (deferred for courses still under follow-up).
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                self::erase((int) $context->instanceid, [$userid]);
            }
        }
        $DB->delete_records('local_customreport_cursor', ['userid' => $userid]);
    }

    /**
     * Delete the data of several users in a context (deferred for courses still under follow-up).
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        self::erase((int) $context->instanceid, $userlist->get_userids());
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_customreport_cursor', "userid $insql", $params);
    }

    /**
     * Erase the data of users in a course, or record a deferred request while the retention period lasts.
     *
     * @param int $courseid
     * @param int[] $userids
     * @param int|null $now
     */
    public static function erase(int $courseid, array $userids, ?int $now = null): void {
        global $DB;
        $retain = retention::must_retain($courseid, $now);
        foreach ($userids as $userid) {
            if ($retain) {
                if (!$DB->record_exists('local_customreport_deferred', ['userid' => $userid, 'courseid' => $courseid])) {
                    $DB->insert_record('local_customreport_deferred', (object) ['userid' => $userid,
                        'courseid' => $courseid, 'timerequested' => $now ?? time()]);
                }
                continue;
            }
            foreach (self::TABLES as $table) {
                $DB->delete_records($table, ['userid' => $userid, 'courseid' => $courseid]);
            }
            $DB->delete_records('local_customreport_deferred', ['userid' => $userid, 'courseid' => $courseid]);
            self::erase_identity((int) $userid);
        }
    }

    /**
     * Delete the identity document of a user once no course under follow-up keeps data about them.
     *
     * @param int $userid
     */
    private static function erase_identity(int $userid): void {
        global $DB;
        foreach (self::TABLES as $table) {
            if ($DB->record_exists($table, ['userid' => $userid])) {
                return;
            }
        }
        $DB->delete_records('local_customreport_idoc', ['userid' => $userid]);
    }

    /**
     * Carry out the deferred erasures whose retention period has ended.
     *
     * @param int|null $now
     * @return int Number of requests carried out.
     */
    public static function purge_deferred(?int $now = null): int {
        global $DB;
        $done = 0;
        foreach ($DB->get_records('local_customreport_deferred') as $request) {
            if (!retention::must_retain((int) $request->courseid, $now)) {
                self::erase((int) $request->courseid, [(int) $request->userid], $now);
                $done++;
            }
        }
        return $done;
    }
}
