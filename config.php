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
 * SEO theme config.
 * No need for configurations as all taken from the parent theme.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . "/theme/seo/locallib.php");

$parenttheme = theme_seo_get_parent_theme();

$parent = theme_config::load($parenttheme);

$THEME->parents = $parent->parents ?? [];
if (!in_array($parenttheme, $THEME->parents)) {
    $THEME->parents = array_unique(array_merge([$parenttheme], $THEME->parents));
}

$THEME->name = 'seo';
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
