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
 * English strings.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['customreport:exportreports'] = 'Export follow-up reports';
$string['customreport:manage'] = 'Manage follow-up configuration';
$string['customreport:viewallreports'] = 'View all follow-up reports (read only)';
$string['customreport:viewgroupreports'] = 'View follow-up reports of own groups';
$string['customreport:viewidentitydocuments'] = 'View identity documents received by the web service';
$string['customreport:viewownreport'] = 'View own follow-up report';
$string['inactivitydays'] = 'Days without access (alert)';
$string['inactivitydays_desc'] = 'Number of days without access after which a learner is flagged to the tutor.';
$string['logretentiondays'] = 'Web service log retention (days)';
$string['logretentiondays_desc'] = 'Days to keep SOAP request/response logs.';
$string['pluginname'] = 'Training follow-up reports';
$string['privacy:metadata'] = 'The plugin does not store personal data yet. Later versions will store aggregated follow-up data, which must be retained during the legal control period.';
$string['sessiongap'] = 'Session gap threshold';
$string['sessiongap_desc'] = 'A session ends when the gap between two log events exceeds this value.';
$string['sessiontail'] = 'Session tail time';
$string['sessiontail_desc'] = 'Time added to the last event of each session. Time spent is an estimate.';
