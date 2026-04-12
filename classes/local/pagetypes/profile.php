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

use stdClass;
use theme_seo\seo;
use theme_seo\utils;

/**
 * Class profile
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile extends base {

    /**
     * Get the user.
     * @return stdClass|false
     */
    protected function get_user(): stdClass|false {
        global $DB;
        $id = $this->seo->get_context()->instanceid;
        $fields = ['id', 'description', 'descriptionformat', ...\core_user\fields::get_name_fields()];
        return $DB->get_record('user', ['id' => $id], implode(', ', $fields));
    }

    #[\Override()]
    protected function description(): string {
        $user = $this->get_user();
        if (!empty($user->description)) {
            return utils::format_text_for_meta($user->description, 'text', $user->descriptionformat);
        }
        return utils::format_text_for_meta(fullname($user));
    }

    #[\Override()]
    protected function schema_markup(): ?array {
        // Todo Add schema markup as public figure or something like that.
        return null;
    }
    #[\Override()]
    public static function is_this_type(seo $seo): bool {
        global $CFG;
        $context = $seo->get_context();
        $ismyprofile = ($context->contextlevel == CONTEXT_USER);

        if ($ismyprofile) {
            require_once("{$CFG->dirroot}/user/lib.php");
            $profileuser = \core_user::get_user($context->instanceid);

            if (!$profileuser || !user_can_view_profile($profileuser, $seo->page->course ?? null, $context)) {
                $ismyprofile = false;
            }
        }
        return $ismyprofile;
    }
}
