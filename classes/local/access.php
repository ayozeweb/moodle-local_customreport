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
 * Which learners the current user may see.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Access rules: learners see their own data, tutors see their groups, the administration sees everything (read only).
 */
class access {
    /**
     * Whether the user has the competent-administration style access to everything.
     *
     * @return bool
     */
    public static function is_admin_reader(): bool {
        return has_capability('local/customreport:viewallreports', \context_system::instance());
    }

    /**
     * Whether the user may see group reports of a course (tutor or administration).
     *
     * @param \context_course $context
     * @return bool
     */
    public static function can_view_groups(\context_course $context): bool {
        return self::is_admin_reader() || has_capability('local/customreport:viewgroupreports', $context);
    }

    /**
     * Whether the user may export reports of a course.
     *
     * @param \context_course $context
     * @return bool
     */
    public static function can_export(\context_course $context): bool {
        return self::is_admin_reader() || has_capability('local/customreport:exportreports', $context);
    }

    /**
     * Learner ids of an action visible to the current user for a group report.
     *
     * @param \stdClass $action
     * @param int|null $viewerid
     * @return int[]
     */
    public static function visible_learners(\stdClass $action, ?int $viewerid = null): array {
        global $DB, $USER;
        $viewerid = $viewerid ?? (int) $USER->id;
        $context = \context_course::instance($action->courseid);
        $ids = array_map('intval', $DB->get_fieldset_select(
            'local_customreport_progress',
            'DISTINCT userid',
            'courseid = ?',
            [$action->courseid]
        ));
        if ($action->groupid) {
            $members = array_map('intval', array_keys(groups_get_members($action->groupid, 'u.id')));
            $ids = array_values(array_intersect($ids, $members));
        }
        if (self::is_admin_reader() || has_capability('moodle/site:accessallgroups', $context)) {
            return $ids;
        }
        $mine = [];
        foreach (groups_get_all_groups($action->courseid, $viewerid) as $group) {
            $mine = array_merge($mine, array_map('intval', array_keys(groups_get_members($group->id, 'u.id'))));
        }
        return array_values(array_intersect($ids, array_unique($mine)));
    }

    /**
     * Whether the current user may view the report of a learner.
     *
     * @param \stdClass $action
     * @param int $userid
     * @return bool
     */
    public static function can_view_learner(\stdClass $action, int $userid): bool {
        global $USER;
        $context = \context_course::instance($action->courseid);
        if ((int) $USER->id === $userid) {
            return has_capability('local/customreport:viewownreport', $context);
        }
        return self::can_view_groups($context) && in_array($userid, self::visible_learners($action), true);
    }
}
