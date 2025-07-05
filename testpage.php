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
 * Purpose of this page:
 * - to test a url if it is public, require login or redirects to another page.
 * - To get the content of the page as it is shown to guest users hence to crawlers.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require_once('../../config.php');

$useragent = core_useragent::get_user_agent_string();

if (!strstr($useragent, '(Moodle SEO)')) {
    die();
}

$useragent = trim(str_replace('(Moodle SEO)', '', $useragent));

if (isloggedin() && !isguestuser()) {
    echo json_encode(['status' => 'Test page only work for guest user\'s to test robots crawlers', 'error' => true]);
    die();
}

$originalurl = optional_param('url', '', PARAM_LOCALURL);

if (empty($originalurl)) {
    echo json_encode(['status' => 'Invalid url passes', 'error' => true]);
    die();
}

$url = new moodle_url($originalurl, ($_GET ?? []) + ($_POST ?? []));
$url->remove_params('url');
$url->param('ignore_seo_check', true);

$params    = $url->params();
$newparams = [];

foreach ($params as $key => $value) {
    $key             = str_replace('amp;', '', $key);
    $newparams[$key] = $value;
}
$url->remove_all_params();
$url->params($newparams);

// The parameter ignore_seo_check is very important to not loop the page.
$loginurl = new moodle_url(get_login_url(), ['ignore_seo_check' => true]);

$fetchurlcontent = function ($url) {
    global $CFG;
    require_once("$CFG->dirroot/lib/filelib.php");

    $url        = new moodle_url($url);
    $ishomepage = $url->compare(new moodle_url('/'), URL_MATCH_BASE);

    // Login the crawler as guest.
    if ((!isloggedin() || !isguestuser()) && !$ishomepage) {
        $user = get_complete_user_data('username', 'guest');
        complete_user_login($user);
    }

    $sessioncookie = session_name() . '=' . session_id();
    session_write_close();

    $curl = new curl(['ignoresecurity' => true]);
    $curl->setopt(['CURLOPT_USERAGENT' => "$useragent MoodleThemeSEO/1.0.0"]);

    if (!$ishomepage) {
        $headers = [
            "Cookie: $sessioncookie",
            'Connection: keep-alive',
        ];
        $curl->setHeader($headers);
    }

    $output  = $curl->get($url);
    $errorno = $curl->get_errno();
    $info    = $curl->get_info();

    if ($errorno) {
        echo json_encode(['status' => $curl->error, 'error' => true]);
        die();
    }

    return [$output, $info];
};

try {
    $filename       = $CFG->dirroot . $url->get_path();
    $errorreporting = error_reporting();

    error_reporting(DEBUG_NONE);

    ob_start();

    [$output, $info] = $fetchurlcontent($url->out(false));

    echo $output;

    $rawcontent = ob_get_clean();

    if ($info['http_code'] != 200) {
        echo json_encode(['status' => $info['http_code'], 'error' => true, 'info' => $info]);
        die();
    }

    $curlurl = !empty($info['redirect_url']) ? $info['redirect_url'] : $info['url'];
    $curlurl = new moodle_url($curlurl);

    if (optional_param('preview', false, PARAM_BOOL)) {
        echo s($rawcontent);
        die;
    }

    $cleanedcontent = clean_text($rawcontent, FORMAT_HTML);
    $content        = trim(strip_tags($cleanedcontent));

    if (!$url->compare($loginurl, URL_MATCH_BASE)) {
        if ($loginurl->compare($curlurl, URL_MATCH_BASE) || $curlurl->compare($loginurl, URL_MATCH_BASE)) {
            $content = 'login page';
        }
    }

    error_reporting($errorreporting);
} catch (Throwable $e) {
    echo json_encode(['status' => $e->getMessage(), 'error' => true]);
    die();
}

if (!empty($content) && $content !== 'login page') {
    echo json_encode([
                        'status'         => 'accessible',
                        'error'          => false,
                        'cleanedcontent' => $cleanedcontent, // I think we should use this content for testing the seo of the page.
                        'contenttext'    => $content,
                        'info'           => $info,
                    ]);
} else if ($content == 'login page') {
    echo json_encode(['status' => 'Required login', 'error' => true]);
} else {
    echo json_encode(['status' => 'not accessible', 'error' => true]);
}
