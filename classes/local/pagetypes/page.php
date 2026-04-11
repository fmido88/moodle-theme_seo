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

use local_pg\serve;
use theme_seo\seo;
use theme_seo\utils;

/**
 * Class page
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page extends base {
    /**
     * Get server class for page.
     * @return serve
     */
    protected function get_page(): serve {
        global $DB;
        $params = $this->seo->get_url_params();
        $id = $params['id'] ?? $params['page'] ?? null;
        $shortname = $params['shortname'] ?? null;

        if (!$id && $shortname) {
            $id = $DB->get_field('local_pg_pages', 'id', ['shortname' => $shortname]);
        }

        return serve::make($id, true, $shortname);
    }
    #[\Override()]
    protected function description(): string {
        $serve = $this->get_page();
        if (!$serve->page_exists() || !$serve->is_visible()) {
            return '';
        }
        $content = $serve->get_formatted_content();
        // Todo add part of the page content as description.
        return utils::format_text_for_meta($content);
    }
    #[\Override()]
    public static function is_this_type(seo $seo): bool {
        return (class_exists('\local_pg\context\page')
                        && $seo->get_context()->contextlevel == \local_pg\context\page::LEVEL);
    }
}
