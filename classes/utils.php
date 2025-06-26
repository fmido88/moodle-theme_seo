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
    public static function format_string($text) {
        return self::format_text_for_meta($text, 'string');
    }
    public static function get_page_url_path() {
        global $PAGE, $CFG, $SITE, $FULLME;
        if ($PAGE->has_set_url()) {
            return $PAGE->url->get_path();
        }
        return (new moodle_url($FULLME))->get_path();
    }
    
}
