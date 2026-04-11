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

use core\context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use moodle_url;
use theme_seo\seo;

/**
 * Class manager-footer
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager_footer extends external_api {
    /**
     * Description for get_seo() parameters.
     * @return external_function_parameters
     */
    public static function get_seo_parameters() {
        return new external_function_parameters([
            'url'       => new external_value(PARAM_LOCALURL),
            'contextid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Exports values regarding page information to render manager footer.
     * @param string $url
     * @param int $contextid
     * @return array{
     * context: string,
     * contextname: string,
     * indexable: bool,
     * instanceid: int,
     * managable: bool,
     * managerurl: \core\url,
     * public: bool,
     * redirected: bool,
     * url: moodle_url}|null
     */
    public static function get_seo(string $url, int $contextid) {
        global $PAGE;
        [
            'url'       => $url,
            'contextid' => $contextid,
        ] = self::validate_parameters(self::get_seo_parameters(), compact('url', 'contextid'));

        $context = context::instance_by_id($contextid);
        self::validate_context($context);

        $PAGE->set_url($url);

        // Caches the seo data.
        $seo = new seo($PAGE, new \core\url($url));
        if (!is_siteadmin()) {
            return null;
        }

        $managerfooter = new \theme_seo\output\manager_footer($seo);
        return $managerfooter->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Returns description for get_seo()
     * @return \core_external\external_single_structure
     */
    public static function get_seo_returns() {
        return \theme_seo\output\manager_footer::export_external_parameters();
    }
}
