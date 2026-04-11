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

use blog_entry;
use theme_seo\seo;
use theme_seo\utils;

/**
 * Class blog
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blog extends base {
    protected ?blog_entry $entry;
    /**
     * Get post entry.
     * @return blog_entry|null
     */
    protected function get_entry(): ?blog_entry {
        global $DB, $CFG;
        if (isset($this->entry)) {
            return $this->entry;
        }

        $id = $this->seo->get_url_params()['id'] ?? 0;
        if (!$id) {
            return null;
        }

        if (!$DB->record_exists('post', ['id' => $id])) {
            return null;
        }

        require_once("{$CFG->dirroot}/blog/locallib.php");
        $this->entry = new blog_entry($id);
        return $this->entry;
    }

    #[\Override()]
    protected function description(): string {
        if (!$entry = $this->get_entry()) {
            return '';
        }
        if ($entry->publishstate != 'public') {
            return '';
        }

        $description = $entry->summary ?: $entry->content;
        $format = !empty($entry->summary) ? $entry->summaryformat : $entry->format;

        return utils::format_text_for_meta($description, 'text', $format);
    }

    #[\Override()]
    protected function schema_markup(): ?array {
        // Todo: Add schema markup for blog post as article.
        return null;
    }
    #[\Override()]
    public static function is_this_type(seo $seo): bool {
        $pageurlpath = $seo->get_page_url_path();
        return strpos($pageurlpath, '/blog') === 0;
    }
}
