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
    protected function array_to_xml($data, &$xml) {
        foreach ($data as $key => $value) {
            if ($key === '@attributes' && is_array($value)) {
                foreach ($value as $attr => $attrvalue) {
                    $xml->addAttribute($attr, $attrvalue);
                }
                continue;
            }

            if (is_numeric($key)) {
                $key = 'url'; // Ensuring valid XML tags.
            }

            if (is_array($value)) {
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
     * @param int               $lastmodified
     * @param string            $changefreq   daily, weekly, monthly or yearly
     * @param float             $priority     [0 - 1]
     * @param string            $type         type of the page: course, module, ..
     *
     * @return array{changefreq: mixed, lastmod: string, loc: string, priority: mixed}
     */
    protected function format_url(
        string|\moodle_url $url,
        $lastmodified = null,
        $changefreq = null,
        $priority = null,
        $type = 'course'
    ) {
        if ($url instanceof \moodle_url) {
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
    public function get_xml(array $urls) {
        // Sitemap data.
        $data = [
            'urlset' => [
                '@attributes' => ['xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9'], // Namespace for sitemap.
                'url'         => array_values($urls),
            ],
        ];

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9'); // Add namespace.
        $this->array_to_xml($data['urlset']['url'], $xml);

        // Format the XML.
        $dom = new \DOMDocument('1.0', 'UTF-8');

        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;
        $dom->loadXML($xml->asXML());

        // Save or display XML.
        return $dom->saveXML();
    }

    /**
     * Generate the sitemap.
     * @return void
     */
    public function generate_sitemap(): void {
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

        // Custom static pages urls.
        // Todo: to be added.
        if (!empty($custompages)) {
            $pages = explode("\n", $custompages);

            foreach ($pages as $page) {
                $page = clean_param($page, PARAM_SAFEPATH);

                $url  = new moodle_url($page);
                $file = "$CFG->dirroot/$page";

                if (!file_exists($file)) {
                    continue;
                }
                $urls[] = $this->format_url($url, filemtime("$file"), type: 'custom');
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
        file_put_contents("$CFG->dirroot/sitemap.xml", $sitemap);
        // Ensure robots.txt exists and is updated with the sitemap location.
        $this->update_robots_txt("$CFG->dirroot/sitemap.xml");
    }

    /**
     * Updated robots.txt file.
     * @param  string $sitemapurl
     * @return void
     */
    private function update_robots_txt($sitemapurl) {
        global $CFG;
        $sitemapline = "Sitemap: $sitemapurl";

        $robotsfile = "$CFG->dirroot/robots.txt";
        // Default robots.txt content for a new file.
        $defaultcontent = <<<EOT
User-agent: *
Disallow: /admin
Disallow: /user

$sitemapline
EOT;

        // Check if robots.txt exists, create if not.
        if (!file_exists($robotsfile)) {
            file_put_contents($robotsfile, $defaultcontent);

            return;
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
        file_put_contents($robotsfile, $robotscontent);
    }
}
