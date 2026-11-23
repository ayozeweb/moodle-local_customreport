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
 * Report builder datasource: learner progress per module/unit.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use local_customreport\reportbuilder\local\entities\progress as progress_entity;

/**
 * Progress datasource.
 */
class progress extends datasource {
    /**
     * Name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('datasource_progress', 'local_customreport');
    }

    /**
     * Initialise the report.
     */
    protected function initialise(): void {
        $entity = new progress_entity();
        $alias = $entity->get_table_alias('local_customreport_progress');
        $this->set_main_table('local_customreport_progress', $alias);
        $this->add_entity($entity);

        $course = new course();
        $coursealias = $course->get_table_alias('course');
        $this->add_entity($course->add_join("JOIN {course} {$coursealias} ON {$coursealias}.id = {$alias}.courseid"));

        $user = new user();
        $useralias = $user->get_table_alias('user');
        $this->add_entity($user->add_join("JOIN {user} {$useralias} ON {$useralias}.id = {$alias}.userid"));

        $this->add_all_from_entities();
    }

    /**
     * Default columns.
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return ['user:fullname', 'progress:modulecode', 'progress:modulename', 'progress:activitiesdone',
            'progress:activitiestotal', 'progress:percent', 'progress:processavg', 'progress:final1', 'progress:final2'];
    }

    /**
     * Default filters.
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return ['course:courseselector', 'progress:modulecode'];
    }

    /**
     * Default conditions.
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
