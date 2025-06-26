<?php
// This file is part of Ranking block for Moodle - http://moodle.org/
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
 * Theme SEO block settings file
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . "/locallib.php");
// This is used for performance, we don't need to know about these settings on every page in Moodle, only when
// we are looking at the admin settings pages.
if ($ADMIN->fulltree) {
    $themes = get_list_of_themes();
    $options = [];
    foreach ($themes as $theme => $object) {
        if ($theme == 'seo') {
            continue;
        }
        $options[$theme] = $object->get_theme_name();
    }

    $settings = new admin_settingpage('themesettingseo', get_string('settings'));
    $settings->add(new admin_setting_configselect('theme_seo/parent',
                                                get_string('parenttheme', 'theme_seo'),
                                                get_string('parenttheme_help', 'theme_seo'),
                                                'boost',
                                                $options));
}
