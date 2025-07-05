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
 * Overriden theme boost core renderer.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_seo\output;

use theme_boost\output\core_renderer as boost_renderer;
use theme_seo\seo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/theme/seo/locallib.php');
require_once("{$CFG->dirroot}/course/renderer.php");

// Get the actual parent theme dynamically.
$parentthemes = theme_seo_get_list_of_parents();
$parentclass  = \core\output\core_renderer::class;

$parentclassexists = false;

while (!empty($parentthemes) && !$parentclassexists) {
    $parenttheme = array_shift($parentthemes);

    if ($class = theme_seo_get_parent_theme_core_renderer($parenttheme)) {
        $parentclass       = $class;
        $parentclassexists = true;
        break;
    }
}

// Use the parent renderer if available; otherwise, default to Boost.
if (!$parentclassexists) {
    // Fallback should never happen.
    debugging("Parent class core_renderer for theme $parenttheme not found. Falling back to Boost.", DEBUG_DEVELOPER);
    $parentclass = boost_renderer::class;
}

class_alias($parentclass, __NAMESPACE__ . '\\theme_seo_parent_core_renderer');

if (!class_exists(__NAMESPACE__ . '\\theme_seo_parent_core_renderer')) {
    debugging('Class ' . __NAMESPACE__ . '\\theme_seo_parent_core_renderer not defined', DEBUG_DEVELOPER);

    // This mainly for autocomplete and should never be reached.
    /**
     * {@inheritDoc}
     */
    class theme_seo_parent_core_renderer extends boost_renderer {
    }
}

/**
 * {@inheritDoc}
 */
class core_renderer extends theme_seo_parent_core_renderer {
    /**
     * SEO helper class.
     * @var seo
     */
    public seo $seo;

    /**
     * Get the seo helper class.
     * @return seo
     */
    public function get_seo(): seo {
        global $FULLME;

        if (isset($this->seo)) {
            return $this->seo;
        }
        $url       = $this->page->has_set_url() ? $this->page->url : $FULLME;
        $this->seo = seo::get($url, $this);

        return $this->seo;
    }

    /**
     * {@inheritDoc}
     * @return string
     */
    public function standard_head_html() {
        $output = parent::standard_head_html();
        // Remove the keywords meta tag to add it again.
        $output = preg_replace('/<meta\s+name\s*=\s*"keywords"\s*content\s*=\s*"[^"]*"\s*\/?>/i', '', $output);
        $output = preg_replace('/<meta\s+name\s*=\s*"description"\s*content\s*=\s*"[^"]*"\s*\/?>/i', '', $output);
        $output = preg_replace('/<meta\s+name\s*=\s*"robots"\s*content\s*=\s*"[^"]*"\s*\/?>/i', '', $output);

        return $this->get_seo()->pre_head_html() . $output;
    }

    /**
     * {@inheritDoc}
     * if not found return the page heading or site name.
     */
    public function page_title() {
        $pagetitle = parent::page_title();

        return $this->get_seo()->page_title($pagetitle);
    }

    /**
     * {@inheritDoc}
     * @return string
     */
    public function footer() {
        if (!is_siteadmin()) {
            return parent::footer();
        }

        $manager       = new manager_footer($this->get_seo());
        $managerfooter = $this->page->get_renderer('theme_seo')->render($manager);

        return $this->get_seo()->get_content_as_guest() . $managerfooter . parent::footer();
    }
}
