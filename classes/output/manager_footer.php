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

namespace theme_seo\output;

use core\output\renderable;
use core\output\templatable;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;
use theme_seo\seo;

/**
 * Class manager_footer.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager_footer implements renderable, templatable {
    /**
     * The seo class object.
     * @var seo
     */
    public seo $seo;

    /**
     * Footer SEO management widget for the current page.
     * @param ?seo $seo
     */
    public function __construct(?seo $seo = null) {
        global $PAGE;

        if ($seo === null) {
            $seo = seo::get(qualified_me(), $PAGE);
        }

        $this->seo  = $seo;
    }

    /**
     * {@inheritDoc}
     * @param \renderer_base $output
     * @return array{context: string,
     * contextname: string,
     * indexable: bool,
     * instanceid: int,
     * managable: bool,
     * managerurl: \core\url,
     * public: bool,
     * redirected: bool,
     * url: moodle_url}
     */
    public function export_for_template(?\renderer_base $output = null) {
        // Ensure load.
        $this->seo->is_public_page();
        $this->seo->pre_head_html();

        $defaultschema = $this->seo->get_default_schema_markup();

        $manparams = [
            'pageurl' => $this->seo->get_url()->out(false),
            'contextid' => $this->seo->get_context()->id,
        ];

        if (!empty($defaultschema)) {
            $manparams['schema_markup'] = $defaultschema;
        }

        $manageurl = new moodle_url('/theme/seo/manage.php', $manparams);

        /**
         * @var core_renderer
         */
        $renderer = $this->seo->page->get_renderer('core');
        $managebutton = $renderer->single_button($manageurl, get_string('seomanage', 'theme_seo'));

        $loadinfo = null;
        if (!empty($this->seo->curlinfo)) {
            // We need 'total_time', 'size_download' for now.
            $info = (array)$this->seo->curlinfo;
            $loadinfo = [
                'total_time'    => format_float($info['total_time'], 3),
                'size_download' => format_float($info['size_download'] / 1024, 2),
            ];
        }
        // var_dump($this->seo);
        // die;
        $context = [
            'public'      => $this->seo->is_public_page(),
            'indexable'   => $this->seo->is_indexable(),
            'redirected'  => $this->seo->is_redirected(),
            'managable'   => $this->seo->is_manageable(),
            'crawlable'   => $this->seo->is_crawler_allowed(),
            'url'         => $this->seo->get_url()->out(false),
            'managerbutton'  => $managebutton,
            'previewurl'  => $this->seo->get_preview_url()->out(false),
            'context'     => $this->seo->get_context()->get_level_name(),
            'contextname' => $this->seo->get_context()->get_context_name(),
            'content'     => $this->seo->get_content_as_guest(),
            'instanceid'  => $this->seo->get_context()->instanceid,
            'contextid'   => $this->seo->get_context()->id,
            'load_info'   => $loadinfo,
        ];

        return $context;
    }

    /**
     * Returns description for external function.
     * @return external_single_structure
     */
    public static function export_external_parameters() {
        return new external_single_structure([
            'public'        => new external_value(PARAM_BOOL),
            'indexable'     => new external_value(PARAM_BOOL),
            'redirected'    => new external_value(PARAM_BOOL),
            'managable'     => new external_value(PARAM_BOOL),
            'crawlable'     => new external_value(PARAM_BOOL),
            'url'           => new external_value(PARAM_LOCALURL),
            'managerbutton' => new external_value(PARAM_RAW),
            'previewurl'    => new external_value(PARAM_LOCALURL),
            'context'       => new external_value(PARAM_TEXT),
            'contextname'   => new external_value(PARAM_TEXT),
            'content'       => new external_value(PARAM_RAW),
            'instanceid'    => new external_value(PARAM_INT),
            'contextid'     => new external_value(PARAM_INT),
            'load_info'     => new external_single_structure([
                'total_time'    => new external_value(PARAM_FLOAT),
                'size_download' => new external_value(PARAM_FLOAT),
            ], allownull: NULL_ALLOWED)
        ], allownull: NULL_ALLOWED);
    }
}
