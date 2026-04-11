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

namespace theme_seo\local\pagetypes;

use core_course_list_element;
use theme_seo\seo;
use theme_seo\utils;

/**
 * Class course.
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course extends base {
    /**
     * Course object.
     * @var core_course_list_element
     */
    protected core_course_list_element $course;

    /**
     * Get the course from the page context.
     * @return core_course_list_element|null
     */
    protected function get_course(): ?core_course_list_element {
        global $COURSE;

        if (isset($this->course)) {
            return $this->course;
        }

        $page = $this->seo->page;
        $context = $this->seo->get_context();
        $course = !empty($COURSE) ? fullclone($COURSE)
                                  : (
                                      !empty($page->course) ? fullclone($page->course)
                                                           : @get_course($context->instanceid)
                                  );

        if (!$course) {
            return null;
        }

        $this->course = new core_course_list_element($course);

        return $this->course;
    }

    #[\Override()]
    protected function description(): string {
        if (isset($this->description)) {
            return $this->description;
        }

        $course = $this->get_course();

        if (!$course) {
            return '';
        }
        $helper = new \coursecat_helper();
        $this->description = utils::format_text_for_meta($helper->get_course_formatted_summary($course));

        return $this->description;
    }

    #[\Override()]
    protected function schema_markup(): ?array {
        global $CFG, $SITE;

        $summary = $this->description;
        $course = $this->get_course();

        if (!$course || $course->id == SITEID) {
            return parent::schema_markup();
        }

        // Course Schema Markup.
        $courseschema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Course',
            'name'        => utils::format_text_for_meta($course->get_formatted_fullname()),
            'description' => $summary,
            'provider'    => [
                '@type' => 'EducationalOrganization',
                'name'  => utils::format_string($SITE->fullname),
                'url'   => $CFG->wwwroot,
            ],
            'url'              => $this->seo->get_url()->out(false),
            'courseMode'       => 'online',
            'educationalLevel' => 'Intermediate',
            'inLanguage'       => current_language(),
        ];

        // If the course has a start date.
        if (!empty($course->startdate)) {
            $courseschema['hasCourseInstance'] = [
                '@type'      => 'CourseInstance',
                'startDate'  => date('Y-m-d', $course->startdate),
                'courseMode' => 'online',
                'inLanguage' => current_language(),
            ];
        }

        return $courseschema;
    }

    #[\Override()]
    public static function is_this_type(seo $seo): bool {
        $pageurlpath = $seo->get_page_url_path();
        $context = $seo->get_context();

        $iscoursepage = \in_array($pageurlpath, ['/course/view.php', '/enrol/index.php'])
                        || $context->contextlevel == CONTEXT_COURSE;

        if ($iscoursepage && $context->contextlevel != CONTEXT_COURSE) {
            $id = $seo->get_url_params()['id'] ?? 0;

            if ($id) {
                $seo->set_context(\context_course::instance($id));
            }
        }

        return $iscoursepage;
    }
}
