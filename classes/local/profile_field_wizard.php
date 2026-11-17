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
 * Wizard that creates the learner and tutor custom profile fields.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\local;

/**
 * Creates standard custom profile fields (idempotent) for follow-up reports.
 */
class profile_field_wizard {
    /** @var string Prefix of all shortnames created by this plugin. */
    const PREFIX = 'cr_';

    /**
     * Field definitions: shortname => [datatype, string key, options (menu) as list of option string keys or literals].
     * Options for which the official catalogue is not in docs/normativa are configurable
     * and listed as TODO-NORMATIVA in docs/REQUISITOS.md.
     *
     * @param string $audience 'learner' or 'tutor'.
     * @return array[]
     */
    public static function definitions(string $audience): array {
        $yesno = ['yes', 'no'];
        if ($audience === 'tutor') {
            return [
                'tutor_sex' => ['menu', ['male', 'female', 'other']],
                'tutor_birthdate' => ['datetime', null],
                'tutor_edulevel' => ['menu', ['edu_primary', 'edu_secondary', 'edu_vet', 'edu_degree', 'edu_master', 'edu_phd']],
                'tutor_teachingyears' => ['text', null],
                'tutor_onlinehours' => ['text', null],
                'tutor_competenceyears' => ['text', null],
                // Teaching competence codes 01-06 (MD B11.1.8.1.4).
                'tutor_teachingcompetence' => ['menu', ['tc01', 'tc02', 'tc03', 'tc04', 'tc05', 'tc06']],
                'tutor_contract' => ['menu', ['contract_employee', 'contract_self', 'contract_other']],
            ];
        }
        return [
            'sex' => ['menu', ['male', 'female', 'other']],
            'birthdate' => ['datetime', null],
            'edulevel' => ['menu', ['edu_primary', 'edu_secondary', 'edu_vet', 'edu_degree', 'edu_master', 'edu_phd']],
            'employment' => ['menu', ['emp_employed', 'emp_unemployed', 'emp_other']],
            'province' => ['text', null],
            'knownby' => ['text', null],
            'onlinebefore' => ['menu', $yesno],
            'reasons' => ['text', null],
            'rating' => ['menu', ['rate1', 'rate2', 'rate3', 'rate4', 'rate5']],
            'jobcategory' => ['text', null],
            'companysize' => ['menu', ['size_micro', 'size_small', 'size_medium', 'size_large']],
            'workschedule' => ['text', null],
        ];
    }

    /**
     * Create (or complete) the profile category and fields for an audience.
     *
     * @param string $audience 'learner' or 'tutor'.
     * @return int Number of fields created.
     */
    public static function create_fields(string $audience): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $categoryname = get_string('wizard_category_' . $audience, 'local_customreport');
        $category = $DB->get_record('user_info_category', ['name' => $categoryname]);
        if (!$category) {
            $sortorder = (int) $DB->get_field_sql('SELECT COALESCE(MAX(sortorder), 0) FROM {user_info_category}') + 1;
            $category = (object) ['name' => $categoryname, 'sortorder' => $sortorder];
            $category->id = $DB->insert_record('user_info_category', $category);
        }
        $created = 0;
        $order = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), 0) FROM {user_info_field} WHERE categoryid = ?',
            [$category->id]
        );
        foreach (self::definitions($audience) as $short => [$datatype, $options]) {
            $shortname = self::PREFIX . $short;
            if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
                continue;
            }
            $field = (object) [
                'shortname' => $shortname,
                'name' => get_string('field_' . $short, 'local_customreport'),
                'datatype' => $datatype,
                'description' => '',
                'descriptionformat' => FORMAT_HTML,
                'categoryid' => $category->id,
                'sortorder' => ++$order,
                'required' => 0,
                'locked' => 0,
                'visible' => PROFILE_VISIBLE_PRIVATE,
                'forceunique' => 0,
                'signup' => 0,
                'defaultdata' => '',
                'defaultdataformat' => 0,
                'param1' => '',
                'param2' => '',
                'param3' => '',
                'param4' => '',
                'param5' => '',
            ];
            if ($datatype === 'menu') {
                $labels = array_map(fn($key) => get_string('opt_' . $key, 'local_customreport'), $options);
                $field->param1 = implode("\n", $labels);
            } else if ($datatype === 'text') {
                $field->param1 = 30;
                $field->param2 = 2048;
                $field->param3 = 0;
            } else if ($datatype === 'datetime') {
                $field->param1 = 1920;
                $field->param2 = (int) date('Y');
                $field->param3 = 0;
            }
            $DB->insert_record('user_info_field', $field);
            $created++;
        }
        return $created;
    }

    /**
     * Shortnames of fields that are missing for an audience.
     *
     * @param string $audience
     * @return string[]
     */
    public static function missing_fields(string $audience): array {
        global $DB;
        $missing = [];
        foreach (array_keys(self::definitions($audience)) as $short) {
            if (!$DB->record_exists('user_info_field', ['shortname' => self::PREFIX . $short])) {
                $missing[] = self::PREFIX . $short;
            }
        }
        return $missing;
    }
}
