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
 * Automatic compliance checks.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Verifiable requirements, each linked to its row in docs/REQUISITOS.md.
 */
class compliance {
    /** @var string OK status. */
    const OK = 'ok';

    /** @var string Warning status. */
    const WARNING = 'warning';

    /** @var string Error status. */
    const ERROR = 'error';

    /** @var int Maximum idle time (seconds) accepted for the session timeout. */
    const MAX_SESSION_TIMEOUT = 4 * HOURSECS;

    /**
     * Run all checks.
     *
     * @return array[] Each ['id', 'ref', 'status', 'message', 'url' (moodle_url|null)].
     */
    public static function run(): array {
        $checks = [
            self::sessiontimeout(),
            self::inspector(),
            self::endpoint(),
            self::profile_fields(),
            self::consent(),
            self::branding(),
            self::actions(),
            self::cron(),
        ];
        return $checks;
    }

    /**
     * Worst status of a list of checks.
     *
     * @param array[] $checks
     * @return string
     */
    public static function overall(array $checks): string {
        $statuses = array_column($checks, 'status');
        if (in_array(self::ERROR, $statuses, true)) {
            return self::ERROR;
        }
        return in_array(self::WARNING, $statuses, true) ? self::WARNING : self::OK;
    }

    /**
     * Build a result.
     *
     * @param string $id
     * @param string $ref
     * @param string $status
     * @param string $message
     * @param \moodle_url|null $url
     * @return array
     */
    private static function result(string $id, string $ref, string $status, string $message, ?\moodle_url $url = null): array {
        return ['id' => $id, 'ref' => $ref, 'status' => $status, 'message' => $message, 'url' => $url];
    }

    /**
     * Session timeout for inactivity is configured (Annex II).
     *
     * @return array
     */
    public static function sessiontimeout(): array {
        global $CFG;
        $timeout = (int) $CFG->sessiontimeout;
        $url = new \moodle_url('/admin/settings.php', ['section' => 'sessionhandling']);
        if ($timeout <= 0) {
            $message = get_string('cc_sessiontimeout_none', 'local_customreport');
            return self::result('sessiontimeout', 'R-2.6.4', self::ERROR, $message, $url);
        }
        if ($timeout > self::MAX_SESSION_TIMEOUT) {
            return self::result(
                'sessiontimeout',
                'R-2.6.4',
                self::WARNING,
                get_string('cc_sessiontimeout_long', 'local_customreport', format_time($timeout)),
                $url
            );
        }
        return self::result(
            'sessiontimeout',
            'R-2.6.4',
            self::OK,
            get_string('cc_sessiontimeout_ok', 'local_customreport', format_time($timeout)),
            $url
        );
    }

    /**
     * The inspector role exists and is read only.
     *
     * @return array
     */
    public static function inspector(): array {
        global $DB;
        $url = new \moodle_url('/admin/roles/manage.php');
        if (!$DB->record_exists('role', ['shortname' => inspector_role::SHORTNAME])) {
            $message = get_string('check_inspector_missing', 'local_customreport');
            return self::result('inspector', 'R-2.3.2', self::ERROR, $message, $url);
        }
        $violations = inspector_role::find_violations();
        if ($violations) {
            return self::result(
                'inspector',
                'R-2.3.2',
                self::ERROR,
                get_string('check_inspector_bad', 'local_customreport', implode(', ', array_keys($violations))),
                $url
            );
        }
        return self::result('inspector', 'R-2.3.2', self::OK, get_string('check_inspector_ok', 'local_customreport'), $url);
    }

    /**
     * The SOAP endpoint can answer: enabled, HTTPS, credentials, WSDL and centre received.
     *
     * @return array
     */
    public static function endpoint(): array {
        global $DB, $CFG;
        $url = new \moodle_url('/local/customreport/wsadmin.php');
        $enabled = get_config('local_customreport', 'ws_enabled');
        $problems = [];
        if ($enabled !== false && !$enabled) {
            $problems[] = get_string('cc_ws_disabled', 'local_customreport');
        }
        if (strpos($CFG->wwwroot, 'https://') !== 0 && !get_config('local_customreport', 'ws_allowhttp')) {
            $problems[] = get_string('cc_ws_nohttps', 'local_customreport');
        }
        if (!is_readable(\local_customreport\sepe\wsdl::dir() . '/ProveedorCentroTFWS.wsdl')) {
            $problems[] = get_string('cc_ws_nowsdl', 'local_customreport');
        }
        if (!$DB->record_exists('local_customreport_wscred', ['enabled' => 1])) {
            $problems[] = get_string('cc_ws_nocred', 'local_customreport');
        }
        if ($problems) {
            return self::result('endpoint', 'R-2.4.1', self::ERROR, implode(' ', $problems), $url);
        }
        if (!\local_customreport\sepe\store::get_center()) {
            return self::result('endpoint', 'R-2.4.1', self::WARNING, get_string('ws_nocenter', 'local_customreport'), $url);
        }
        return self::result('endpoint', 'R-2.4.1', self::OK, get_string('cc_ws_ok', 'local_customreport'), $url);
    }

    /**
     * The learner and tutor profile fields exist.
     *
     * @return array
     */
    public static function profile_fields(): array {
        $missing = array_merge(profile_field_wizard::missing_fields('learner'), profile_field_wizard::missing_fields('tutor'));
        $url = new \moodle_url('/local/customreport/profilefields.php');
        if ($missing) {
            return self::result(
                'profilefields',
                'R-2.1.10',
                self::WARNING,
                get_string('wizard_missing', 'local_customreport', implode(', ', $missing)),
                $url
            );
        }
        return self::result('profilefields', 'R-2.1.10', self::OK, get_string('wizard_complete', 'local_customreport'), $url);
    }

    /**
     * Informed consent: a current policy exists in tool_policy.
     *
     * @return array
     */
    public static function consent(): array {
        $url = new \moodle_url('/admin/tool/policy/managedocs.php');
        if (!class_exists('\tool_policy\api') || !\core_component::get_component_directory('tool_policy')) {
            return self::result('consent', 'R-2.7.2', self::WARNING, get_string('cc_consent_notool', 'local_customreport'), $url);
        }
        $versions = \tool_policy\api::list_current_versions(\tool_policy\policy_version::AUDIENCE_LOGGEDIN);
        $versions += \tool_policy\api::list_current_versions(\tool_policy\policy_version::AUDIENCE_ALL);
        if (!$versions) {
            return self::result('consent', 'R-2.7.2', self::WARNING, get_string('cc_consent_none', 'local_customreport'), $url);
        }
        $message = get_string('cc_consent_ok', 'local_customreport', count($versions));
        return self::result('consent', 'R-2.7.2', self::OK, $message, $url);
    }

    /**
     * At least one institutional logo is uploaded.
     *
     * @return array
     */
    public static function branding(): array {
        $url = new \moodle_url('/admin/settings.php', ['section' => 'local_customreport']);
        $count = branding::count();
        if (!$count) {
            return self::result('branding', 'R-2.5.5', self::WARNING, get_string('cc_branding_none', 'local_customreport'), $url);
        }
        return self::result('branding', 'R-2.5.5', self::OK, get_string('cc_branding_ok', 'local_customreport', $count), $url);
    }

    /**
     * There is a training action and each one has dates, a module structure and a regulatory profile.
     *
     * @return array
     */
    public static function actions(): array {
        global $DB;
        $actions = $DB->get_records('local_customreport_action');
        if (!$actions) {
            return self::result('actions', 'R-2.5.1', self::WARNING, get_string('cc_actions_none', 'local_customreport'));
        }
        $problems = [];
        foreach ($actions as $action) {
            $name = $action->actioncode ?: ('#' . $action->id);
            if (!$action->startplanned || !$action->endplanned) {
                $problems[] = get_string('cc_actions_nodates', 'local_customreport', $name);
            }
            if (!$DB->record_exists('local_customreport_module', ['actionid' => $action->id])) {
                $problems[] = get_string('cc_actions_nostructure', 'local_customreport', $name);
            }
            if (!profile_manager::get($action->profile)) {
                $problems[] = get_string('cc_actions_noprofile', 'local_customreport', $name);
            }
        }
        if ($problems) {
            return self::result('actions', 'R-2.5.1', self::WARNING, implode(' ', $problems));
        }
        return self::result('actions', 'R-2.5.1', self::OK, get_string('cc_actions_ok', 'local_customreport', count($actions)));
    }

    /**
     * The usage aggregation task has run recently.
     *
     * @param int|null $now
     * @return array
     */
    public static function cron(?int $now = null): array {
        global $DB;
        $url = new \moodle_url('/admin/tool/task/scheduledtasks.php');
        $last = (int) $DB->get_field(
            'task_scheduled',
            'lastruntime',
            ['classname' => '\local_customreport\task\aggregate_usage']
        );
        if (!$last) {
            return self::result('cron', 'R-2.6.2', self::WARNING, get_string('cc_cron_never', 'local_customreport'), $url);
        }
        if (($now ?? time()) - $last > DAYSECS) {
            return self::result(
                'cron',
                'R-2.6.2',
                self::ERROR,
                get_string('cc_cron_old', 'local_customreport', userdate($last)),
                $url
            );
        }
        return self::result('cron', 'R-2.6.2', self::OK, get_string('cc_cron_ok', 'local_customreport', userdate($last)), $url);
    }
}
