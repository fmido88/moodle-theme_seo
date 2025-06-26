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
 * Handler for robots.txt serving
 *
 * @package    theme_seo
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_seo;

/**
 * Handler for robots.txt serving
 *
 * @author     Brendan Heywood <brendan@catalyst-au.net>
 * @copyright  2023 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class robots {

    /**
     * This serves requests for robots.txt file.
     */
    public static function serve() {
        global $ME;

        if ($ME !== '/robots.txt' ) {
            return;
        }

        $config = get_config('theme_seo');
        if (empty($config->robotstxt)) {
            return;
        }

        header("Content-Type: text/plain");
        print $config->robotstxt;

        die;
    }
}
