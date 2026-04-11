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

use DOMDocument;
use moodle_url;
use SimpleXMLElement;

/**
 * Class generator.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    /**
     * Convert array data to xml tags.
     * @param  array            $data
     * @param  SimpleXMLElement $xml
     * @return void
     */
    protected function array_to_xml(array $data, SimpleXMLElement &$xml) {
        foreach ($data as $key => $value) {
            if ($key === '@attributes' && \is_array($value)) {
                foreach ($value as $attr => $attrvalue) {
                    $xml->addAttribute($attr, $attrvalue);
                }
                continue;
            }

            if (is_numeric($key)) {
                $key = 'url'; // Ensuring valid XML tags.
            }

            if (\is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->array_to_xml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * Summary of format_url.
     * @param string|moodle_url $url
     * @param ?int              $lastmodified
     * @param ?string           $changefreq   daily, weekly, monthly or yearly
     * @param ?float            $priority     [0 - 1]
     * @param string            $type         type of the page: course, module, ..
     *
     * @return ?array{changefreq: mixed, lastmod: string, loc: string, priority: mixed}
     */
    protected function format_url(
        string|moodle_url $url,
        ?int $lastmodified = null,
        ?string $changefreq = null,
        ?float $priority = null,
        string $type = 'course'
    ) {
        if (!utils::validate_link($url)) {
            return null;
        }

        if ($url instanceof moodle_url) {
            $url = $url->out(false);
        }

        if (!$lastmodified) {
            $lastmodified = time();
        }

        if (!$changefreq) {
            $changefreq = match($type) {
                'course'   => 'monthly',
                'activity' => 'daily',
                'page'     => 'monthly',
                'static'   => 'yearly',
                default    => 'weekly',
            };
        }

        if (!$priority) {
            $priority = '0.5';
        }

        return [
            'loc'        => $url,
            'lastmod'    => date('Y-m-d', $lastmodified),
            'changefreq' => $changefreq,
            'priority'   => $priority,
        ];
    }

    /**
     * Get xml document string for an array of urls.
     * @param  array       $urls
     * @return bool|string
     */
    protected function get_xml(array $urls) {

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9'); // Add namespace.
        $this->array_to_xml(array_values(array_filter($urls)), $xml);

        // Format the XML.
        $dom = new DOMDocument('1.0', 'UTF-8');

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;
        $dom->loadXML($xml->asXML());

        // Save or display XML.
        return $dom->saveXML();
    }

    /**
     * Generate the sitemap.
     * @param bool $contentonly
     * @return string
     */
    public function generate_sitemap(bool $contentonly = false): string {
        global $CFG, $DB;

        $urls = [];

        $baseurl = $CFG->wwwroot;

        $home   = $DB->get_record('course', ['id' => SITEID], 'timemodified');
        $urls[] = $this->format_url($baseurl, $home->timemodified, 'monthly', '1.0');

        $courses = $DB->get_records_select(
            'course',
            'visible = 1 AND id <> :siteid',
            ['siteid' => SITEID],
            '',
            'id, fullname, timemodified'
        );

        // Add courses.
        foreach ($courses as $course) {
            $url    = new moodle_url('/course/view.php', ['id' => $course->id]);
            $urls[] = $this->format_url($url, $course->timemodified, 'monthly', '0.6');
        }

        // Add categories.
        $categories = $DB->get_records('course_categories', ['visible' => 1], '', 'id, timemodified');

        foreach ($categories as $category) {
            $url    = new moodle_url('/course/index.php', ['categoryid' => $category->id]);
            $urls[] = $this->format_url($url, $category->timemodified, 'monthly', '0.5');
        }

        // Custom static pages and user defined urls.
        $custompages = get_config('theme_seo', 'custompages');
        // Todo: to be added into settings.php.
        if (!empty($custompages)) {
            $pages = explode("\n", $custompages);

            foreach ($pages as $rawentry) {
                if (empty(trim($rawentry ?? ''))) {
                    continue;
                }

                $page = clean_param($rawentry, PARAM_PATH) ?: clean_param($rawentry, PARAM_LOCALURL);

                if (empty($page)) {
                    continue;
                }

                $url  = new moodle_url($page);
                if (!$url->is_local_url()) {
                    continue;
                }

                $relativepath = utils::extract_url_path($url);
                $file = "{$CFG->dirroot}{$relativepath}";

                if (!utils::validate_link($url)) {
                    // We should notify the admin about invalid link.
                    continue;
                }

                $lastmodified = file_exists($file) ? filemtime($file) : null;

                $urls[] = $this->format_url($url, $lastmodified, type: 'custom');
            }
        }

        // Blogs.
        $blogs = $DB->get_records('post', ['publishstate' => 'public'], '', 'id, lastmodified');

        foreach ($blogs as $blog) {
            $url    = new moodle_url('/blog/index.php', ['id' => $blog->id]);
            $urls[] = $this->format_url($url, $blog->lastmodified, 'yearly', '0.8');
        }

        // Pages from local_pg.
        if (class_exists('\local_pg\serve')) {
            $visible = \local_pg\helper::ALLOW_GUEST;
            $pages   = $DB->get_records_select(
                'local_pg_pages',
                'visible <= :vis',
                ['vis' => $visible],
                'parent ASC',
                'id, timemodified, shortname'
            );

            foreach ($pages as $page) {
                $url    = new moodle_url('/local/pg/index.php/' . $page->shortname, ['page' => $page->id]);
                $urls[] = $this->format_url($url, $page->timemodified);
            }
        }

        $sitemap = $this->get_xml($urls);
        if (!$contentonly) {
            file_put_contents("$CFG->dirroot/sitemap.xml", $sitemap);
        }

        return $sitemap;
    }

    /**
     * Return the url to the sitemap.
     * @return string
     */
    public static function get_site_map_url(): string {
        global $CFG;
        return "$CFG->wwwroot/sitemap.xml";
    }

    /**
     * Updated robots.txt file.
     * @param bool $contentonly
     * @return string
     */
    public static function update_robots_txt(bool $contentonly = false) {
        global $CFG;
        $sitemapurl = self::get_site_map_url();
        $sitemapline = "Sitemap: $sitemapurl";

        $robotsfile = "$CFG->dirroot/robots.txt";
        // Default robots.txt content for a new file.
        $defaultcontent = <<<EOT
User-agent: *
Disallow: /admin

$sitemapline
EOT;

        // Check if robots.txt exists, create if not.
        if (!file_exists($robotsfile)) {
            $contentonly || file_put_contents($robotsfile, $defaultcontent);

            return $defaultcontent;
        }

        $robotscontent = file_get_contents($robotsfile);

        // Check if a sitemap line exists.
        if (strpos($robotscontent, 'Sitemap:') !== false) {
            // Update existing sitemap line if necessary.
            $robotscontent = preg_replace('/Sitemap:.*/', $sitemapline, $robotscontent);
        } else {
            // Append sitemap line to robots.txt ...
            $robotscontent .= PHP_EOL . $sitemapline;
        }

        // Write updated content back to robots.txt ...
        $contentonly || file_put_contents($robotsfile, $robotscontent);
        return $robotscontent;
    }
}
