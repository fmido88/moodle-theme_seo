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

namespace theme_seo\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;
use theme_seo\utils;

/**
 * Class validate_links.
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validate_links extends external_api {
    /**
     * validated links list keyed by the url.
     * @var bool[]
     */
    protected static array $validated = [];

    /**
     * Description for validate_links() parameters.
     * @return external_function_parameters
     */
    public static function validate_links_parameters(): external_function_parameters {
        return new external_function_parameters([
            'links' => new external_multiple_structure(
                new external_single_structure([
                    'href' => new external_value(PARAM_URL),
                    'text' => new external_value(PARAM_TEXT),
                ])
            ),
        ]);
    }

    /**
     * Validate a list of links.
     * @param array $links
     */
    public static function validate_links(array $links) {
        $links = self::validate_parameters(self::validate_links_parameters(), compact('links'))['links'];
        self::validate_context(context_system::instance());
        require_admin();

        $cache = \core_cache\cache::make('theme_seo', 'validlinks');
        foreach ($links as $i => $link) {
            $url = $link['href'];

            if (empty($url)) {
                // Maybe not a valid href.
                $links[$i]['valid'] = false;
                continue;
            }

            $key = (new moodle_url($url));
            $key->set_anchor(null);
            $key = $key->out(false);

            if ($valid = $cache->get($key)) {
                self::$validated[$key] = $valid;
            } else if (!isset(self::$validated[$key])) {
                self::$validated[$key] = utils::validate_link($url);
                $cache->set($key, self::$validated[$key]);
            }

            $links[$i]['valid'] = self::$validated[$key];
        }

        return $links;
    }

    /**
     * Returns value description of validate_links().
     * @return external_multiple_structure
     */
    public static function validate_links_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'href'  => new external_value(PARAM_URL),
                'text'  => new external_value(PARAM_TEXT),
                'valid' => new external_value(PARAM_BOOL),
            ])
        );
    }
}
