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
 * Form to manage the page seo.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use theme_seo\form\manager;
use theme_seo\utils;

require('../../config.php');

require_admin();

if ($pageurl = optional_param('pageurl', null, PARAM_LOCALURL)) {
    $pageurl = new moodle_url($pageurl);
} else {
    $path = required_param('page_path', PARAM_PATH);
    $paramsstring = required_param('page_params', PARAM_TEXT);

    $pageurl = utils::get_url_from_path($path, $paramsstring);
}

$contextid = required_param('contextid', PARAM_INT);
$schemamarkup = optional_param('schema_markup', null, PARAM_TEXT);

$url = new moodle_url('/theme/seo/manage.php', ['pageurl' => $pageurl->out(false), 'contextid' => $contextid]);

$PAGE->set_url($url);
$PAGE->set_context(context::instance_by_id($contextid));

$title = get_string('seomanage', 'theme_seo');
$PAGE->set_heading($title);
$PAGE->set_title($title);

$pagepath = utils::extract_url_path($pageurl);

$pageparams = $pageurl->params();
core_collator::ksort($pageparams);

$form = new manager(null, [
    'pagepath'      => $pagepath,
    'pageparams'    => json_encode($pageparams),
    'contextid'     => $contextid,
    'schema_markup' => $schemamarkup,
]);

if ($records = $DB->get_records('theme_seo', ['page_path' => $pagepath])) {
    foreach ($records as $record) {
        if (!empty($record->page_params)) {
            $pageparams = array_map('trim', json_decode($record->page_params, true));
            if (array_intersect($pageparams, $pageurl->params()) == $pageparams) {
                $form->set_data($record);
                break;
            }
        }
    }
}

if ($form->is_cancelled()) {
    redirect($pageurl);
} else if ($data = $form->get_data()) {
    if (empty($data->indexable)) {
        $data->indexable = 0;
    }

    if (!empty($data->id)) {
        $DB->update_record('theme_seo', $data);
    } else {
        $DB->insert_record('theme_seo', $data);
    }

    redirect($pageurl);
}

echo $OUTPUT->header();

$form->display();

echo $OUTPUT->footer();
