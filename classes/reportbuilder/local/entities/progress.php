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
 * Report builder entity: progress per learner and module/unit.
 *
 * @package    local_customreport
 * @copyright  2026 Ayoze
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customreport\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;

/**
 * Progress entity.
 */
class progress extends base {
    /**
     * Database tables used by the entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['local_customreport_progress', 'local_customreport_module'];
    }

    /**
     * Entity title.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entity_progress', 'local_customreport');
    }

    /**
     * Module/unit join.
     *
     * @return string
     */
    public function get_module_join(): string {
        $p = $this->get_table_alias('local_customreport_progress');
        $m = $this->get_table_alias('local_customreport_module');
        return "LEFT JOIN {local_customreport_module} {$m} ON {$m}.id = {$p}.nodeid AND {$p}.nodeid > 0";
    }

    /**
     * Columns.
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $p = $this->get_table_alias('local_customreport_progress');
        $m = $this->get_table_alias('local_customreport_module');
        $name = $this->get_entity_name();
        $columns = [];

        $columns[] = (new column('modulecode', new lang_string('code', 'local_customreport'), $name))
            ->add_join($this->get_module_join())->add_field("{$m}.code")->set_is_sortable(true);
        $columns[] = (new column('modulename', new lang_string('name'), $name))
            ->add_join($this->get_module_join())->add_field("{$m}.name")->set_is_sortable(true);
        $columns[] = (new column('activitiesdone', new lang_string('col_activitiesdone', 'local_customreport'), $name))
            ->set_type(column::TYPE_INTEGER)->add_field("{$p}.activitiesdone")->set_is_sortable(true);
        $columns[] = (new column('activitiestotal', new lang_string('activitiestotal', 'local_customreport'), $name))
            ->set_type(column::TYPE_INTEGER)->add_field("{$p}.activitiestotal")->set_is_sortable(true);
        $columns[] = (new column('percent', new lang_string('percentdone', 'local_customreport'), $name))
            ->set_type(column::TYPE_FLOAT)
            ->add_field(
                "CASE WHEN {$p}.activitiestotal > 0 THEN 100.0 * {$p}.activitiesdone / {$p}.activitiestotal ELSE 0 END",
                'percent'
            )
            ->set_is_sortable(true)
            ->add_callback(static fn($v) => $v === null ? '' : format_float((float) $v, 1));
        foreach (['processavg' => 'col_processaverage', 'final1' => 'final1', 'final2' => 'final2'] as $field => $string) {
            $columns[] = (new column($field, new lang_string($string, 'local_customreport'), $name))
                ->set_type(column::TYPE_FLOAT)->add_field("{$p}.{$field}")->set_is_sortable(true)
                ->add_callback(static fn($v) => $v === null ? '' : format_float((float) $v, 2));
        }
        return $columns;
    }

    /**
     * Filters.
     *
     * @return filter[]
     */
    protected function get_available_filters(): array {
        $p = $this->get_table_alias('local_customreport_progress');
        $m = $this->get_table_alias('local_customreport_module');
        $name = $this->get_entity_name();
        return [
            (new filter(text::class, 'modulecode', new lang_string('code', 'local_customreport'), $name, "{$m}.code"))
                ->add_joins([$this->get_module_join()]),
            new filter(
                number::class,
                'processavg',
                new lang_string('col_processaverage', 'local_customreport'),
                $name,
                "{$p}.processavg"
            ),
            new filter(
                number::class,
                'activitiesdone',
                new lang_string('col_activitiesdone', 'local_customreport'),
                $name,
                "{$p}.activitiesdone"
            ),
        ];
    }
}
