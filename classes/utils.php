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

use core\xml_parser;
use core_text;
use core_useragent;
use curl;
use moodle_url;

/**
 * Class utils
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {

    /**
     * Clean text, Strip tags and links and escape html characters for a text content.
     *
     * @param ?string $text
     * @param string $type
     * @param int $textformat -1 means that the text already formatted just strip tags and minify the text.
     * @return string
     */
    public static function format_text_for_meta(?string $text, string $type = 'text', int $textformat = -1) {
        if ($text === null) {
            return '';
        }

        if ($textformat >= 0 || !\in_array($type, ['string', 'text'])) {
            $text = match($type) {
                'text'   => format_text($text, $textformat),
                'html'   => format_text($text, FORMAT_HTML),
                'string' => format_string($text),
                default  => $text,
            };
        }

        if ($text === null) {
            return '';
        }

        $text = s(strip_tags($text));

        // Minify text.
        $text = self::minify_text($text, $type !== 'string');

        return $text;
    }

    /**
     * Shorten some text to certain length of characters and make sure to
     * not cut through a word.
     * @param string $text
     * @param int $round
     * @return string
     */
    public static function shorten_text(string $text, int $round = 70) {
        $text = self::minify_text($text, false);
        $words = explode(' ', $text);
        $result = '';
        $count = 0;
        foreach ($words as $word) {
            $result .= " $word";
            $count += core_text::strlen($word) + 1;
            if ($count >= $round + 1) {
                break;
            }
        }
        return trim($result);
    }

    /**
     * Minify the given text by removing new lines and extra spaces.
     * @param string $text
     * @param bool $lower convert to lowercase.
     * @return string
     */
    public static function minify_text(string $text, bool $lower = true): string {
        $text = fix_utf8($text);
        if ($lower) {
            $text = core_text::strtolower($text);
        }

        do {
            $length = core_text::strlen($text);
            $text = trim($text);
            // Replacing tab, new lines, double spaces... with single space.
            $text = str_replace(['  ', "\n", "\r", "\t", "\v", "\x00"], " ", $text);
            $newlength = core_text::strlen($text);
        } while ($length > $newlength);

        return $text;
    }

    /**
     * Format strings to be suitable for being add inside meta tags.
     * @param ?string $text
     * @return string
     */
    public static function format_string(?string $text) {
        return self::format_text_for_meta($text, 'string', 0);
    }

    /**
     * Get the page url path.
     * @return string
     */
    public static function get_page_url_path() {
        global $PAGE;
        if ($PAGE->has_set_url()) {
            return $PAGE->url->get_path();
        }
        return (new moodle_url(qualified_me()))->get_path();
    }

    /**
     * Extract the relative path for a given url.
     * @param string|moodle_url $url
     * @return string
     */
    public static function extract_url_path(string|moodle_url $url) {
        $path = (new moodle_url($url))->get_path(true);
        $homeurlpath = (new moodle_url('/'))->get_path(false);

        $path = trim($path, '/');
        $homeurlpath = trim($homeurlpath, '/');

        $path = "/$path";
        $homeurlpath = "/$homeurlpath";

        if (\strlen($homeurlpath) > 1) {
            // Only get the relative url path.
            if (strpos($path, $homeurlpath) === 0) {
                $path = substr($path, \strlen($homeurlpath));
                if (empty($path)) {
                    $path = "/"; // Only happen in home page.
                }
            }
        }

        if (str_ends_with($path, '/index.php')) {
            $path = substr($path, 0, -10);
        }

        return $path;
    }

    /**
     * Get url from page path.
     * @param string $path
     * @param array|string|null $params
     * @return moodle_url
     */
    public static function get_url_from_path(string $path, array|string|null $params = null) {

        if ($params !== null && \is_string($params)) {
            $decoded = @json_decode($params, true);
            if (\is_array($decoded)) {
                $params = $decoded;
            } else if (strpos($params, '=') !== false) {
                if (!str_starts_with($params, '?')) {
                    $params = "?{$params}";
                }
                $path .= $params;
                $params = null;
            } else {
                $params = [];
            }
        }

        return new moodle_url($path, $params);
    }

    /**
     * Validate a certain link is accessible.
     * @param string|moodle_url $url
     * @return bool
     */
    public static function validate_link(string|moodle_url $url): bool {
        global $CFG;
        require_once("$CFG->libdir/filelib.php");
        $url = new moodle_url($url);

        $curl = new curl(['ignoresecurity' => $url->is_local_url()]);
        $curl->setopt([
            'CURLOPT_USERAGENT' => core_useragent::get_moodlebot_useragent() . ' (Moodle SEO)',
            'CURLOPT_NOBODY'    => true,
        ]);

        $rawresponse = $curl->get($url->out(false));
        $curlerrno   = $curl->get_errno();
        $info        = $curl->get_info();

        if (!empty($curlerrno) || $info['http_code'] != 200) {
            return false;
        }

        return true;
    }

    /**
     * Get the default country code.
     * @return ?string
     */
    public static function get_country(): ?string {
        global $CFG, $USER;
        $code = ($CFG->country ?? '') ?: ($USER->country ?? '');
        return $code ? core_text::strtolower($code) : null;
    }

    /**
     * Read the content of the current sitemap.
     * @return array<array>|null
     */
    public static function read_current_sitemap() {
        static $urls;

        if (isset($urls)) {
            return $urls;
        }

        $file = generator::get_site_map_url(); // Read from url to ensure access.
        $content = @file_get_contents($file);
        if (empty($content)) {
            return null;
        }

        $parsed = (new xml_parser())->parse($content);
        $xmlurls = $parsed['urlset']['#']['url'] ?? null;

        $urls = [];
        $properties = ['loc', 'lastmod', 'changefreq', 'priority'];
        foreach ($xmlurls as $url) {
            $parsed = [];
            foreach ($properties as $property) {
                $parsed[$property] = reset($url['#'][$property])['#'];
            }
            $urls[] = $parsed;
        }

        return $urls;
    }
}
