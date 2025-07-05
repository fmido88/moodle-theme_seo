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
use moodle_page;
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
     * The current page object.
     * @var moodle_page
     */
    public moodle_page $page;

    /**
     * Footer SEO management widget for the current page.
     * @param ?seo $seo
     */
    public function __construct(?seo $seo = null) {
        global $OUTPUT, $FULLME;

        if ($seo === null) {
            $seo = seo::get($FULLME, $OUTPUT);
        }

        $this->page = $seo->page;
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
    public function export_for_template(\renderer_base $output) {
        $context = [
            'public'      => $this->seo->is_public_page(),
            'indexable'   => $this->seo->is_indexable(),
            'redirected'  => $this->seo->is_redirected(),
            'url'         => $this->seo->get_url(),
            'managable'   => $this->seo->is_public_page() && !$this->seo->is_redirected(),
            'managerurl'  => new moodle_url('/theme/seo/manage.php', ['pageurl' => $this->seo->get_url()->out(false)]),
            'context'     => $this->seo->get_context()->get_level_name(),
            'contextname' => $this->seo->get_context()->get_context_name(),
            'instanceid'  => $this->seo->get_context()->instanceid,
        ];

        return $context;
    }
}
