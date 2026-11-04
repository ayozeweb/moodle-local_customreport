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
 * Wizard that creates the standard learner and tutor profile fields (admin).
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_customreport\local\profile_field_wizard;

admin_externalpage_setup('local_customreport_profilefields');

$url = new moodle_url('/local/customreport/profilefields.php');
if (($audience = optional_param('create', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (in_array($audience, ['learner', 'tutor'], true)) {
        $count = profile_field_wizard::create_fields($audience);
        redirect(
            $url,
            get_string('wizard_created', 'local_customreport', $count),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('profilefieldswizard', 'local_customreport'));
echo html_writer::tag('p', get_string('wizard_intro', 'local_customreport'));
foreach (['learner', 'tutor'] as $audience) {
    $missing = profile_field_wizard::missing_fields($audience);
    echo $OUTPUT->heading(get_string('wizard_category_' . $audience, 'local_customreport'), 3);
    if ($missing) {
        echo html_writer::tag('p', get_string('wizard_missing', 'local_customreport', implode(', ', $missing)));
        echo $OUTPUT->single_button(
            new moodle_url($url, ['create' => $audience]),
            get_string('wizard_create', 'local_customreport'),
            'post'
        );
    } else {
        echo $OUTPUT->notification(
            get_string('wizard_complete', 'local_customreport'),
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}
echo $OUTPUT->footer();
