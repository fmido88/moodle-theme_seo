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

namespace theme_seo;

use moodle_url;
use dml_exception;
/**
 * Class utils
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Strip links and escape html characters for a text.
     * @param string $text
     * @param string $type
     * @param int $textformat -1 means that the text already formatted.
     * @return string
     */
    public static function format_text_for_meta($text, $type = 'text', $textformat = -1) {
        if ($text === null) {
            return '';
        }
        if ($textformat >= 0 || !in_array($type, ['string', 'text'])) {
            $text = match($type) {
                'text' => format_text($text, $textformat),
                'html' => format_text($text, FORMAT_HTML),
                'string' => format_string($text, $textformat),
                default => $text,
            };
        }
        if ($text === null) {
            return '';
        }
        return s(strip_tags($text));
    }
    /**
     * Format strings to be suitable for being add inside meta tags.
     * @param mixed $text
     * @return string
     */
    public static function format_string($text) {
        return self::format_text_for_meta($text, 'string');
    }
    /**
     * Get the page url path.
     * @return string
     */
    public static function get_page_url_path() {
        global $PAGE, $FULLME;
        if ($PAGE->has_set_url()) {
            return $PAGE->url->get_path();
        }
        return (new moodle_url($FULLME))->get_path();
    }

    /**
     * Get redirection url from page path.
     * @param string $path
     * @param array|string $params
     * @return moodle_url
     */
    public static function get_url_from_path($path, $params = null) {
        $homeurlpath = (new moodle_url('/'))->get_path(false);

        if (!str_starts_with($path, '/')) {
            $path = "/{$path}";
        }

        if (!str_starts_with($homeurlpath, '/')) {
            $homeurlpath = "/{$homeurlpath}";
        }

        if (!str_ends_with($homeurlpath, '/')) {
            $homeurlpath = "{$homeurlpath}/";
        }

        if (strlen($homeurlpath) > 1 && strpos($path, $homeurlpath) === 0) {
            $path = substr($path, 1, strlen($homeurlpath) - 1);
        }
        if ($params && is_string($params)) {
            if ($decoded = @json_decode($params, true)) {
                $params = $decoded;
            } else if (strpos($params, '=') !== false) {
                if (!str_starts_with($params, '?')) {
                    $params = "?{$params}";
                }
                $path = $params;
                $params = null;
            }
        }

        return new moodle_url($path, $params);
    }
}
