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
 * Report builder entity: usage per learner, area and day.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Usage entity.
 */
class usage extends base {
    /**
     * Database tables used by the entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['local_customreport_usage'];
    }

    /**
     * Entity title.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_usage', 'local_customreport');
    }

    /**
     * Columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $u = $this->get_table_alias('local_customreport_usage');
        $name = $this->get_entity_name();
        $columns = [];
        $columns[] = (new column('day', new lang_string('day', 'local_customreport'), $name))
            ->set_type(column::TYPE_TIMESTAMP)->add_field("{$u}.day")->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = (new column('component', new lang_string('area', 'local_customreport'), $name))
            ->add_field("{$u}.component")->set_is_sortable(true);
        $counters = ['events' => 'events', 'accesses' => 'col_accesses', 'participations' => 'col_participations'];
        foreach ($counters as $field => $string) {
            $columns[] = (new column($field, new lang_string($string, 'local_customreport'), $name))
                ->set_type(column::TYPE_INTEGER)->add_field("{$u}.{$field}")->set_is_sortable(true);
        }
        $columns[] = (new column('hours', new lang_string('col_timespenthours', 'local_customreport'), $name))
            ->set_type(column::TYPE_FLOAT)->add_field("{$u}.seconds / 3600.0", 'hours')->set_is_sortable(true)
            ->add_callback(static fn($v) => $v === null ? '' : format_float((float) $v, 2));
        $columns[] = (new column('firstaccess', new lang_string('col_firstaccess', 'local_customreport'), $name))
            ->set_type(column::TYPE_TIMESTAMP)->add_field("{$u}.firstaccess")->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        $columns[] = (new column('lastaccess', new lang_string('col_lastaccess', 'local_customreport'), $name))
            ->set_type(column::TYPE_TIMESTAMP)->add_field("{$u}.lastaccess")->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);
        return $columns;
    }

    /**
     * Filters.
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
        $u = $this->get_table_alias('local_customreport_usage');
        $name = $this->get_entity_name();
        return [
            new filter(date::class, 'day', new lang_string('day', 'local_customreport'), $name, "{$u}.day"),
            new filter(text::class, 'component', new lang_string('area', 'local_customreport'), $name, "{$u}.component"),
        ];
    }
}
