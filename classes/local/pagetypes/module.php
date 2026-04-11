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

namespace theme_seo\local\pagetypes;

use stdClass;
use theme_seo\seo;
use theme_seo\utils;

/**
 * Class module
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module extends base {
    /**
     * get the course module instance with intro and introformat.
     * @return ?stdClass
     */
    protected function get_module_instance(): ?stdClass {
        global $DB;
        $moduleid = $this->seo->get_context()->instanceid;
        $sql      = 'SELECT cm.id, cm.instance, m.name as modname
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id
                WHERE cm.id = :moduleid';
        $params = ['moduleid' => $moduleid];

        $module = $DB->get_record_sql($sql, $params);
        if (!$DB->get_manager()->table_exists($module->modname)) {
            return null;
        }

        $columns = $DB->get_columns($module->modname);
        if (\count(array_intersect(array_keys($columns), ['intro', 'introformat'])) !== 2) {
            return null;
        }

        // Todo: In case of forum discussion page, get the post content as description.
        // Actually we should add a specific page type for each module.
        return $DB->get_record($module->modname, ['id' => $module->instance], 'id, intro, introformat') ?: null;
    }

    #[\Override()]
    protected function description(): string {
        $instance = $this->get_module_instance();

        $this->description = $instance ? utils::format_text_for_meta($instance->intro, 'text', $instance->introformat) : '';

        return $this->description;
    }
    #[\Override()]
    public static function is_this_type(seo $seo): bool {
        return $seo->get_context()->contextlevel == CONTEXT_MODULE;
    }
}
