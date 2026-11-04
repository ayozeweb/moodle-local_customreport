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
 * Read-only role for the competent administration (inspector).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Creates the "Competent administration follow-up" role and checks that it can only read.
 */
class inspector_role {
    /** @var string Role shortname. */
    const SHORTNAME = 'customreportinspector';

    /** @var string[] Capabilities granted to the role (all read-only). */
    const CAPABILITIES = [
        'local/customreport:viewallreports',
        'local/customreport:exportreports',
        'local/customreport:viewidentitydocuments',
        'moodle/course:view',
        'moodle/course:viewparticipants',
        'moodle/grade:viewall',
        'moodle/user:viewdetails',
        'report/log:view',
    ];

    /** @var int Risks that a read-only role must never have. */
    const FORBIDDEN_RISKS = RISK_DATALOSS | RISK_CONFIG | RISK_XSS | RISK_MANAGETRUST | RISK_SPAM;

    /**
     * Create the role if missing and grant the read-only capabilities.
     *
     * @return int Role id.
     */
    public static function ensure(): int {
        global $DB;
        $role = $DB->get_record('role', ['shortname' => self::SHORTNAME]);
        if ($role) {
            $roleid = (int) $role->id;
        } else {
            $roleid = create_role(
                get_string('inspectorrole', 'local_customreport'),
                self::SHORTNAME,
                get_string('inspectorrole_desc', 'local_customreport')
            );
            set_role_contextlevels($roleid, [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE]);
        }
        $system = \context_system::instance();
        foreach (self::CAPABILITIES as $capability) {
            if ($DB->record_exists('capabilities', ['name' => $capability])) {
                assign_capability($capability, CAP_ALLOW, $roleid, $system->id, true);
            }
        }
        return $roleid;
    }

    /**
     * Capabilities of the role that can write or carry a dangerous risk.
     *
     * @return string[] capability => reason (empty when the role is safe)
     */
    public static function find_violations(): array {
        global $DB;
        $role = $DB->get_record('role', ['shortname' => self::SHORTNAME]);
        if (!$role) {
            return [];
        }
        $sql = "SELECT c.name, c.captype, c.riskbitmask
                  FROM {role_capabilities} rc
                  JOIN {capabilities} c ON c.name = rc.capability
                 WHERE rc.roleid = :roleid AND rc.permission = :allow";
        $violations = [];
        foreach ($DB->get_records_sql($sql, ['roleid' => $role->id, 'allow' => CAP_ALLOW]) as $cap) {
            if ($cap->captype === 'write') {
                $violations[$cap->name] = 'write';
            } else if ((int) $cap->riskbitmask & self::FORBIDDEN_RISKS) {
                $violations[$cap->name] = 'risk';
            }
        }
        ksort($violations);
        return $violations;
    }
}
