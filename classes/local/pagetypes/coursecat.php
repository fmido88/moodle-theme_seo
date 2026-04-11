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

use core_course_category;
use theme_seo\seo;
use theme_seo\utils;

/**
 * Class coursecat
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursecat extends base {
    /**
     * The context category.
     * @var core_course_category
     */
    protected core_course_category $category;
    /**
     * Get the course category for the current page context.
     * @return core_course_category|null
     */
    protected function get_category(): ?core_course_category {
        if (!isset($this->category)) {
            $categoryid = $this->seo->get_context()->instanceid;
            $category = core_course_category::get($categoryid, IGNORE_MISSING);
            if (!$category) {
                return null;
            }
            $this->category = $category;
        }
        return $this->category;
    }

    #[\Override()]
    protected function description(): string {
        $category = $this->get_category();
        $cathelper  = new \coursecat_helper();

        if (!$category) {
            return '';
        }
        $this->description = utils::format_text_for_meta($cathelper->get_category_formatted_description($category));
        return $this->description;
    }
    #[\Override()]
    public static function is_this_type(seo $seo): bool {
        return $seo->get_context()->contextlevel == CONTEXT_COURSECAT;
    }
}
