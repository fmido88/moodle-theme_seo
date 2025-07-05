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

namespace theme_seo\tasks;

use theme_seo\generator;

/**
 * Class sitemap
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sitemap extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for the task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('sitemap_generator_task', 'theme_seo');
    }
    /**
     * Execute the task.
     * @return void
     */
    public function execute() {
        $generator = new generator;
        $generator->generate_sitemap();
    }
}
