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

use core\output\theme_config;

/**
 * Theme SEO local functions.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get the parent theme's name from the theme configuration.
 */
function theme_seo_get_parent_theme() {
    $themelist = array_keys(core_component::get_plugin_list('theme'));

    foreach ($themelist as $themename) {
        if ($themename == 'seo') {
            continue;
        }
        $themes[$themename] = $themename;
    }

    $parent = get_config('theme_seo', 'parent');

    if ($parent && in_array($parent, $themes) && $parent != 'seo') {
        return $parent;
    } else if ($parent) {
        if ($parent == 'seo') {
            debugging('Cannot use seo as parent theme', DEBUG_DEVELOPER);
        } else {
            debugging("Parent theme $parent not found it may be deleted or disabled", DEBUG_DEVELOPER);
        }
    }

    return 'boost';
}

/**
 * Get a list of parents to this theme.
 * @return array
 */
function theme_seo_get_list_of_parents() {
    return theme_config::load('seo')->parents;
}

/**
 * Get the parent theme class name for core_renderer.
 * @param  string      $parenttheme
 * @return string|null
 */
function theme_seo_get_parent_theme_core_renderer($parenttheme) {
    if ($parenttheme === 'boost') {
        return \theme_boost\output\core_renderer::class;
    }
    $prefix = "\\theme_{$parenttheme}";

    $possibleclasses = [
        "{$prefix}\\core_renderer",
        "{$prefix}_core_renderer",
        "{$prefix}\\output\\core_renderer",
        "{$prefix}\\output\\core\\core_renderer",
        "{$prefix}\\output\\core\\renderer",
    ];

    foreach ($possibleclasses as $class) {
        if (class_exists($class)) {
            return $class;
        }
    }

    return null;
}
