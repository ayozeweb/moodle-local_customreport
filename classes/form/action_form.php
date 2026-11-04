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
 * Training action metadata form.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\form;

use local_customreport\local\action_manager;
use local_customreport\local\profile_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to edit the metadata of a training action.
 */
class action_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $groups = $this->_customdata['groups'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'groupid', get_string('scope', 'local_customreport'), $groups);
        $mform->addHelpButton('groupid', 'scope', 'local_customreport');
        $mform->addElement('select', 'profile', get_string('profile', 'local_customreport'), profile_manager::get_menu());
        $mform->addElement('text', 'authority', get_string('authority', 'local_customreport'), ['size' => 40]);
        $mform->setType('authority', PARAM_TEXT);
        $mform->addElement('text', 'name', get_string('actionname', 'local_customreport'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('header', 'identhdr', get_string('identification', 'local_customreport'));
        $mform->addElement('text', 'actionorigin', get_string('actionorigin', 'local_customreport'), ['size' => 3]);
        $mform->setType('actionorigin', PARAM_ALPHANUM);
        $mform->setDefault('actionorigin', '20');
        $mform->addElement('text', 'actioncode', get_string('actioncode', 'local_customreport'), ['size' => 30]);
        $mform->setType('actioncode', PARAM_ALPHANUMEXT);
        $mform->addElement('text', 'centercode', get_string('centercode', 'local_customreport'), ['size' => 12]);
        $mform->setType('centercode', PARAM_ALPHANUM);
        $mform->addHelpButton('centercode', 'centercode', 'local_customreport');
        $mform->addElement('text', 'areacode', get_string('areacode', 'local_customreport'), ['size' => 10]);
        $mform->setType('areacode', PARAM_ALPHANUM);
        $mform->addElement('text', 'specialtycode', get_string('specialtycode', 'local_customreport'), ['size' => 30]);
        $mform->setType('specialtycode', PARAM_ALPHANUMEXT);
        $situations = [];
        foreach (action_manager::SITUATIONS as $code) {
            $situations[$code] = get_string('situation_' . $code, 'local_customreport');
        }
        $mform->addElement('select', 'situation', get_string('situation', 'local_customreport'), $situations);
        $mform->setDefault('situation', 20);
        $mform->addElement('select', 'modality', get_string('modality', 'local_customreport'), [
            'TF' => get_string('modality_TF', 'local_customreport'),
            'PR' => get_string('modality_PR', 'local_customreport'),
            'PE' => get_string('modality_PE', 'local_customreport'),
        ]);
        $mform->addElement('select', 'financing', get_string('financing', 'local_customreport'), [
            'PR' => get_string('financing_PR', 'local_customreport'),
            'PU' => get_string('financing_PU', 'local_customreport'),
        ]);
        $mform->addElement('advcheckbox', 'fullitinerary', get_string('fullitinerary', 'local_customreport'));
        $mform->setDefault('fullitinerary', 1);
        $mform->addElement('text', 'hours', get_string('hours', 'local_customreport'), ['size' => 6]);
        $mform->setType('hours', PARAM_INT);
        $mform->addElement('text', 'places', get_string('places', 'local_customreport'), ['size' => 6]);
        $mform->setType('places', PARAM_INT);

        $mform->addElement('header', 'dateshdr', get_string('dates', 'local_customreport'));
        $opt = ['optional' => true];
        $mform->addElement('date_selector', 'startplanned', get_string('startplanned', 'local_customreport'), $opt);
        $mform->addElement('date_selector', 'endplanned', get_string('endplanned', 'local_customreport'), $opt);
        $mform->addElement('date_selector', 'startreal', get_string('startreal', 'local_customreport'), $opt);
        $mform->addElement('date_selector', 'endreal', get_string('endreal', 'local_customreport'), $opt);

        $mform->addElement('header', 'deschdr', get_string('description', 'local_customreport'));
        foreach (['info', 'schedule', 'requirements'] as $field) {
            $mform->addElement('textarea', $field, get_string($field, 'local_customreport'), ['rows' => 3, 'cols' => 60]);
            $mform->setType($field, PARAM_TEXT);
        }
        $mform->addElement('text', 'contact', get_string('contact', 'local_customreport'), ['size' => 60]);
        $mform->setType('contact', PARAM_TEXT);
        $mform->addElement(
            'textarea',
            'presencialcenters',
            get_string('presencialcenters', 'local_customreport'),
            ['rows' => 3, 'cols' => 40]
        );
        $mform->addHelpButton('presencialcenters', 'presencialcenters', 'local_customreport');
        $mform->setType('presencialcenters', PARAM_TEXT);

        $this->add_action_buttons();
    }

    /**
     * Server side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (action_manager::validate((object) $data) as $field => $stringid) {
            $errors[$field] = get_string($stringid, 'local_customreport');
        }
        return $errors;
    }
}
