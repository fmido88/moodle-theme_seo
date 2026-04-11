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

use theme_seo\seo;

/**
 * Class blog
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blog extends base {
    #[\Override()]
    protected function description(): string {
        // Todo: Get part of the blog post as description.
        return '';
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
