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
 * Preview a page as a guest user.
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_seo\seo;

require('../../config.php');

require_admin();
$url = required_param('url', PARAM_LOCALURL);
$contextid = required_param('contextid', PARAM_INT);
$page = optional_param_array('page', [], PARAM_TEXT);

$currentuser = fullclone($USER);
$guestuser = get_complete_user_data('username', 'guest');

$guestuser->sesskey = sesskey();

\core\session\manager::set_user($guestuser);

$context = \core\context::instance_by_id($contextid);

$url = new moodle_url($url);
$PAGE->set_url($url);
$PAGE->set_context($context);

foreach ($page as $key => $value) {
    $method = "set_{$key}";
    if (empty($value) || !method_exists($PAGE, $method)) {
        continue;
    }
    $PAGE->$method($value);
}

$seo = seo::get($url);
$crawler = $seo->get_crawler_page_content(true);

if (empty($crawler->cleanedcontent)) {
    echo '<pre>';
    var_dump($url, $crawler, $seo);
    echo '</pre>';

    \core\session\manager::set_user($currentuser);
    die;
}

$rawcontent = preg_replace('/"sesskey":"\w+"/', "\"sesskey\":\"{$currentuser->sesskey}\"", $crawler->rawcontent);

echo $rawcontent;

\core\session\manager::set_user($currentuser);
