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

namespace theme_seo\form;

use theme_seo\seo;

defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/formslib.php");

/**
 * Class manager.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager extends \moodleform {
    /**
     * Form definition.
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $data  = $this->_customdata;

        $overrideoptions = [
            seo::OVERRIDE_NOTEXIST => get_string('override_notexist', 'theme_seo'),
            seo::OVERRIDE_REPLACE  => get_string('override_replace', 'theme_seo'),
            seo::OVERRIDE_CONCAT   => get_string('override_concat', 'theme_seo'),
        ];

        $mform->addElement('checkbox', 'indexable', get_string('indexable', 'theme_seo'));
        $mform->setDefault('indexable', true);

        $mform->addElement('text', 'title', get_string('title', 'theme_seo'));
        $mform->setType('title', PARAM_TEXT);

        $mform->addElement('select', 'overridetitle', get_string('overridetitle', 'theme_seo'), $overrideoptions);

        $mform->addElement('textarea', 'meta_description', get_string('meta_description', 'theme_seo'));
        $mform->setType('meta_description', PARAM_TEXT);

        $mform->addElement('select', 'descriptionoverride', get_string('descriptionoverride', 'theme_seo'), $overrideoptions);

        $mform->addElement('text', 'main_keyword', get_string('main_keyword', 'theme_seo'));
        $mform->setType('main_keyword', PARAM_TEXT);

        $mform->addElement('text', 'sub_keywords', get_string('sub_keywords', 'theme_seo'));
        $mform->setType('sub_keywords', PARAM_TEXT);

        $mform->addElement('select', 'overridekeys', get_string('keywordsoverride', 'theme_seo'), $overrideoptions);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'page_path');
        $mform->setType('page_path', PARAM_TEXT);
        $mform->setDefault('page_path', $data['pagepath']);

        $mform->addElement('hidden', 'page_params');
        $mform->setType('page_params', PARAM_TEXT);
        $mform->setDefault('page_params', $data['pageparams']);

        $this->add_action_buttons();
    }
}
