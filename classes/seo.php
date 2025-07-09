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

namespace theme_seo;

use context;
use core\output\renderer_base;
use core_renderer;
use core_useragent;
use curl;
use html_writer;
use moodle_page;
use moodle_url;
use stdClass;

/**
 * SEO main class to format meta tags, title and other SEO related elements
 * Also to add.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seo {
    /**
     * Moodle page instance.
     * @var moodle_page
     */
    public moodle_page $page;

    /**
     * Output renderer instance.
     * @var core_renderer
     */
    protected renderer_base $output;

    /**
     * Current page url.
     * @var moodle_url
     */
    protected moodle_url $pageurl;

    /**
     * SEO record for the current page.
     * @var object{id:int,
     *             page_path:string,
     *             page_params:string,
     *             title:string,
     *             overridetitle:int,
     *             meta_description:string,
     *             overridedesc:int,
     *             main_keyword:string,
     *             sub_keywords:string,
     *             overridekeys:int,
     *             indexable:int|bool}
     */
    protected stdClass $record;

    /**
     * Is the page public or not.
     * @var bool
     */
    protected bool $ispublic;

    /**
     * Is the page is indixable or not.
     * @var bool
     */
    protected bool $indexable;

    /**
     * Is the page redirect to another url for guests or not.
     * @var bool
     */
    public bool $redirect = false;

    /**
     * The meta description of the page.
     * @var string
     */
    protected string $description;

    /**
     * The meta keywords of the page.
     * @var array
     */
    protected array $keywords;

    /**
     * Is the meta tags added to the page or not.
     * @var bool
     */
    protected bool $metaadded = false;

    /**
     * Redirected url for the page in case of guest.
     * @var moodle_url|null
     */
    public ?moodle_url $redirecturl = null;

    /**
     * The curl info returned from the test page.
     * @var stdClass|array
     */
    public stdClass|array $curlinfo;

    /**
     * The page context.
     * @var context
     */
    protected context $context;

    /**
     * Override only if no automatic meta generated.
     * @var int
     */
    public const OVERRIDE_NOTEXIST = 0;

    /**
     * Override the existing meta tags with the specified one.
     * @var int
     */
    public const OVERRIDE_REPLACE = 1;

    /**
     * Append the specified meta tags to the existing ones.
     * @var int
     */
    public const OVERRIDE_CONCAT = 2;

    /**
     * Cache the SEO instance for multiple calls in the same request.
     * @var self[]
     */
    protected static $cached = [];

    /**
     * User agent string.
     * @var string
     */
    protected static $useragent;

    /**
     * The content of the page as appeared to the search engine (guests).
     * @var string
     */
    protected $content = '';

    /**
     * SEO constructor.
     * @param  \renderer_base         $output
     * @param  moodle_page|null       $page
     * @param  string|moodle_url|null $pageurl
     * @return void
     */
    public function __construct(renderer_base $output, ?moodle_page $page = null, string|moodle_url|null $pageurl = null) {
        global $FULLME, $PAGE, $DB;

        $this->output = $output;
        $this->page   = $page ?? @$this->output->get_page() ?? $PAGE;

        $this->context = $this->page->context;

        if (empty($pageurl)) {
            $pageurl = $this->page->has_set_url() ? $this->page->url : $FULLME;
        }

        $this->pageurl = new moodle_url($pageurl);

        if (self::is_testing()) {
            return;
        }

        try {
            $this->ispublic = $this->is_public_page();
            // About 3 seconds for homepage and 2 sec for other pages.
            // It only affects admins.
        } catch (\Throwable $e) {
            if (error_reporting() >= DEBUG_ALL) {
                $exception            = get_exception_info($e);
                $exception->backtrace = format_backtrace($exception->backtrace);
                $debug                = '';

                foreach ($exception as $key => $value) {
                    $debug .= "$key: $value<br>";
                }
                debugging($debug, DEBUG_ALL);
            }
        }

        $this->load_record();
        $this->update_cached();
    }

    /**
     * Get the context of the page.
     * @return context
     */
    public function get_context(): context {
        return $this->context;
    }

    /**
     * Get the SEO instance for the specified page.
     * @param  \moodle_url|string $url
     * @param  ?renderer_base     $output
     * @return seo
     */
    public static function get(moodle_url|string $url, ?renderer_base $output = null): self {
        global $OUTPUT;
        $url = new moodle_url($url);
        $key = base64_encode($url->out(false));

        if (isset(self::$cached[$key])) {
            return self::$cached[$key];
        }

        $seo = new self($output ?? $OUTPUT, null, $url);

        return $seo;
    }

    /**
     * Update the cached SEO instance.
     * @return void
     */
    public function update_cached(): void {
        $key                = base64_encode($this->pageurl->out(false));
        self::$cached[$key] = $this;
    }

    /**
     * Set the context to extract the data from.
     * @param  context $context
     * @return void
     */
    public function set_context(context $context) {
        $this->context = $context;
    }

    /**
     * Load the SEO record for the current page.
     * @return void
     */
    private function load_record(): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('theme_seo')) {
            return;
        }

        $pagepathlike = $DB->sql_like('seo.page_path', ':pagepath', false, false);

        $sql = "SELECT *
                FROM {theme_seo} seo
                WHERE $pagepathlike
                ORDER BY seo.page_params ASC";
        $pagepath  = $this->get_page_url_path();
        $urlparams = $this->get_url_params();

        $params  = ['pagepath' => $DB->sql_like_escape($pagepath)];
        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $record) {
            if (empty($record->page_params)) {
                $this->record = $record;
                break;
            }

            // Record with no page params overrides all.
            $pageparams = @json_decode($record->page_params, true);

            if (empty($pageparams)) {
                $this->record = $record;
                break;
            }

            if (array_intersect_assoc($urlparams, $pageparams) == $pageparams) {
                $this->record = $record;
            }
        }
    }

    /**
     * Check if we are during testing request.
     * @return bool
     */
    public static function is_testing(): bool {
        if (!isset(self::$useragent)) {
            self::$useragent = core_useragent::get_user_agent_string();
        }

        return strstr(self::$useragent, '(Moodle SEO)')
            || strstr(self::$useragent, 'MoodleThemeSEO')
            || optional_param('ignore_seo_check', false, PARAM_BOOL);
    }

    /**
     * Check if this page is indexable.
     * @return bool
     */
    public function is_indexable(): bool {
        global $CFG;

        if (isset($this->indexable)) {
            return $this->indexable;
        }
        // Check if indexing is disabled in the website.
        $allowindexing = $CFG->allowindexing ?? 0;
        $loginpages    = ['login-index', 'login-signup'];

        if ($allowindexing == 2 || ($allowindexing == 0 && in_array($this->page->pagetype, $loginpages))) {
            $this->indexable = false;

            return false;
        }

        if (!empty($this->record) && isset($this->record->indexable) && !$this->record->indexable) {
            $this->indexable = false;

            return false;
        }

        $this->indexable = true;

        return true;
    }

    /**
     * Check if the page is public or not.
     * @return bool
     */
    public function is_public_page(): bool {
        if (isset($this->ispublic)) {
            return $this->ispublic;
        }

        if (self::is_testing()) {
            return false;
        }

        if (!isloggedin() || isguestuser()) {
            // Already accessed by guest user no need to check.
            return true;
        }

        global $CFG;
        require_once("$CFG->libdir/filelib.php");
        $curl = new curl(['ignoresecurity' => true]);
        $curl->setopt(['CURLOPT_USERAGENT' => core_useragent::get_moodlebot_useragent() . ' (Moodle SEO)']);

        $params = [
            'url' => $this->pageurl->out(false),
        ];
        $rawresponse = $curl->post(new moodle_url('/theme/seo/testpage.php', $params));
        $curlerrno   = $curl->get_errno();
        $info        = $curl->get_info();

        if (!empty($curlerrno) || $info['http_code'] != 200) {
            $this->ispublic = false;

            return false;
        }

        if (!$response = json_decode($rawresponse)) {
            return false;
        }

        $public = !(bool)$response->error;

        if (!$public) {
            return $public;
        }

        $this->curlinfo = $response->info;
        $curlurl        = new moodle_url($response->info->url);

        if ($curlurl->compare($this->pageurl, URL_MATCH_BASE)) {
            if (empty($response->info->redirect_url)) {
                $this->redirect = false;
            } else {
                $this->redirect    = true;
                $this->redirecturl = new moodle_url($response->info->redirect_url);
            }
        } else {
            $this->redirect    = true;
            $this->redirecturl = $curlurl;
        }

        $this->content = $response->cleanedcontent;

        return $public;
    }

    /**
     * Get the preview url for the page.
     * @return \core\url
     */
    public function get_preview_url(): moodle_url {
        return new moodle_url('/theme/seo/testpage.php', ['url' => $this->pageurl->out(false), 'preview' => true]);
    }

    /**
     * Get the content of the page as appeared to the search engine.
     * @return string
     */
    public function get_content_as_guest() {
        return html_writer::div($this->content, 'seo-page-content d-none hidden', ['data-purpose' => 'seo-page-content']);
    }

    /**
     * Check if the page is redirected for guests.
     * @return bool
     */
    public function is_redirected(): bool {
        if (!isset($this->curlinfo)) {
            $this->is_public_page();
        }

        return $this->redirect;
    }

    /**
     * Check if the page is manageable by the SEO manager.
     * @return bool
     */
    public function is_manageable(): bool {
        return $this->is_public_page() && !$this->is_redirected();
    }

    /**
     * Check if the page is allowed to be crawled by search engines
     * and not throw error or redirect to login page.
     * @return bool
     */
    public function is_crawler_allowed(): bool {
        return $this->is_indexable() && $this->is_public_page();
    }

    /**
     * Get the page url path.
     * @return string
     */
    public function get_page_url_path(): string {
        // This is a problem if the moodle installation is in sub-path.
        // for example if the home page is like (http://example.com/moodle).
        $path = $this->get_url()->get_path();

        // Let's try to subtract the home path from it.
        $homepath = (new moodle_url('/'))->get_path();
        if (strlen($homepath) < 2) { // Usually '/'.
            return $path;
        }

        if (!str_starts_with($homepath, '/')) {
            $homepath = "/{$homepath}";
        }

        if (!str_ends_with($homepath, '/')) {
            $homepath = "{$homepath}/";
        }

        if (!str_starts_with($path, '/')) {
            $path = "/{$path}";
        }

        if (str_starts_with($path, $homepath)) {
            return substr($path, strlen($homepath) - 1);
        }

        debugging("The homepage path: $homepath does not match the current page path $path", DEBUG_DEVELOPER);
        return $path;
    }

    /**
     * Get the page url params.
     * @return array
     */
    public function get_url_params(): array {
        return $this->get_url()->params();
    }

    /**
     * Get the page url.
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return ($this->redirect && !empty($this->redirecturl)) ? $this->redirecturl : $this->pageurl;
    }

    /**
     * Get the page title.
     * @param  string $original
     * @return string
     */
    public function page_title($original = ''): string {
        global $SITE;

        if (empty($original)) {
            $original = $this->page->title;
        }

        $hasrecordtitle = isset($this->record->overridetitle) && !empty($this->record->title);

        if ($hasrecordtitle) {
            if ($this->record->overridetitle == self::OVERRIDE_REPLACE) {
                return utils::format_string($this->record->title);
            }
        }

        if (!empty($original) && str_word_count($original) > 5) {
            $pagetitle = $original;
        } else {
            if ($hasrecordtitle && $this->record->overridetitle == self::OVERRIDE_NOTEXIST) {
                return utils::format_string($this->record->title);
            }

            $sitename = utils::format_string($SITE->fullname);

            $heading = empty($original) ? ($this->page->heading ?? '') : $original;

            if (!empty($heading)) {
                $heading   = utils::format_string($heading);
                $pagetitle = "{$heading} | {$sitename}";
            } else {
                $pagetitle = $sitename;
            }
        }

        if ($hasrecordtitle && $this->record->overridetitle == self::OVERRIDE_CONCAT) {
            $pagetitle .= ' ' . utils::format_string($this->record->title);
        }

        return $pagetitle;
    }

    /**
     * Get meta tags to be added to the html head.
     * @return string
     */
    public function pre_head_html(): string {
        $output = '';

        try {
            $pageurlpath = $this->get_page_url_path();

            // If URL should not be indexed, add the noindex meta tag to page.
            if (!$this->is_indexable()) {
                $output .= '<meta name="robots" content="noindex, follow" />';
                $this->metaadded = true;

                return $output;
            }

            if (is_siteadmin()) {
                $jsargs = [
                    'public'     => $this->is_public_page(),
                    'redirected' => $this->is_redirected(),
                ];
                $this->page->requires->js_call_amd('theme_seo/analyze', 'init', $jsargs);
            }

            if (self::is_testing() // During internal test for the publicity of the page.
                || !$this->is_crawler_allowed() // If the page is not public so no need to add these meta tags
                                                // where no crawlers are allowed.
                || $this->is_redirected() // The page already redirected to another page for guests, no need too.
            ) {
                $this->metaadded = true;

                return $output;
            }

            $iscoursepage = in_array($pageurlpath, ['/course/view.php', '/enrol/index.php'])
                            || $this->context->contextlevel == CONTEXT_COURSE;

            if ($iscoursepage && $this->context->contextlevel != CONTEXT_COURSE) {
                $id = $this->get_url_params()['id'] ?? 0;

                if ($id) {
                    $this->context = \context_course::instance($id);
                }
            }

            $this->add_keywords_meta($output);

            if ($iscoursepage) {
                $this->course($output);
            } else if ($this->context->contextlevel == CONTEXT_COURSECAT) {
                $this->coursecat($output);
            } else if ($this->context->contextlevel == CONTEXT_MODULE) {
                // Check if the module is a part of public course or in a homepage first.
                $this->module($output);
            } else if ($this->context->contextlevel == CONTEXT_USER) {
                $profileuser = \core_user::get_user($this->context->instanceid);

                if ($profileuser && user_can_view_profile($profileuser, $this->page->course ?? null, $this->context)) {
                    $this->profile($output);
                }
            } else if (strpos($pageurlpath, '/blog') === 0) {
                $this->blog($output);
            } else if (class_exists('\local_page\context\page')
                       && $this->context->contextlevel == \local_page\context\page::LEVEL) {
                $this->page($output);
            }

            $this->metaadded = true;
        } catch (\Throwable $e) {
            if (error_reporting() >= DEBUG_ALL) {
                $exception            = get_exception_info($e);
                $exception->backtrace = format_backtrace($exception->backtrace, true);
                $debug                = '<pre>';

                foreach ($exception as $key => $value) {
                    $debug .= "$key: $value<br>\n";
                }
                $debug .= '</pre>';
                debugging($debug, DEBUG_ALL);
            }
        }

        return $output;
    }

    /**
     * Add keywords meta tag to the page.
     * @param  string $output
     * @return string
     */
    private function add_keywords_meta(&$output = ''): string {
        $keywords = $this->get_keywords();
        $output .= "<meta name='keywords' content='" . implode(', ', $keywords) . "'/>";

        return $output;
    }

    /**
     * Get the keywords for the page.
     * @return array
     */
    public function get_keywords() {
        global $SITE;

        if (!empty($this->keywords)) {
            return $this->keywords;
        }

        $keywords = [];

        if (isset($this->record->overridekeys)) {
            if (!empty($this->record->main_keyword)) {
                $keywords[] = trim($this->record->main_keyword);
            }

            if (!empty($this->record->sub_keywords)) {
                $subkeywords = array_filter(array_map(utils::class . '::format_string', explode(',', $this->record->sub_keywords)));
                $keywords    = array_unique(array_merge($keywords, $subkeywords));
            }

            if ($this->record->overridekeys == self::OVERRIDE_REPLACE) {
                $this->keywords = $keywords;

                return $keywords;
            }
        }

        $tags = self::get_tags();

        if (count($tags) < 10) {
            $pagetitle = $this->output->page_title();
            $heading   = $this->page->heading;

            if (!empty($heading)) {
                $keywords = array_merge(explode('|', $heading), $keywords);
            } else if (!empty($pagetitle)) {
                $keywords = array_merge(explode('|', $pagetitle), $keywords);
            }
        }

        $keywords = array_unique(array_filter(array_map('trim', array_merge($keywords, $tags)))); // Got the exception here.

        if (count($keywords) < 9) {
            $keywords[] = utils::format_string($SITE->fullname);
            $keywords[] = utils::format_string($SITE->shortname);
        }

        $keywords       = array_unique(array_filter(array_map('trim', $keywords)));
        $this->keywords = $keywords;

        return $keywords;
    }

    /**
     * Get the tags for the page.
     * @return array
     */
    private function get_tags() {
        global $DB;
        $keywords = [];

        // Add tags as keywords.
        if (!empty($this->context->id) && $this->context instanceof \core\context) {
            $contextsids = array_map(function (\core\context $cont) {
                return $cont->id;
            }, $this->context->get_parent_contexts(true));
            [$contextsql, $contextsqlparams] = $DB->get_in_or_equal($contextsids, SQL_PARAMS_NAMED);

            $subsql = "SELECT DISTINCT t.id
                        FROM {tag} t
                        JOIN {tag_instance} ti ON t.id = ti.tagid
                        JOIN {context} ctx ON ti.contextid = ctx.id
                        WHERE contextid {$contextsql}";

            $sql = "SELECT tt.id, tt.name
                    FROM ($subsql) tv
                    JOIN {tag} tt ON tt.id = tv.id";
            $tags = $DB->get_records_sql($sql, $contextsqlparams);

            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
        }

        return $keywords;
    }

    /**
     * Get the description for the page from the record.
     * @param  string $original The original description.
     * @return string
     */
    protected function add_description($original = '') {
        $output      = '';
        $description = '';

        if (!empty($this->record->meta_description)) {
            $description = $this->record->meta_description;
        }

        $override = $this->record->overridedesc ?? -1;

        $description = match(true) {
            $override == self::OVERRIDE_NOTEXIST && empty($original)     => $description,
            $override == self::OVERRIDE_REPLACE  && !empty($description) => $description,
            $override == self::OVERRIDE_CONCAT                           => "$description $original",
            default                                                      => $original,
        };

        if (!empty($description)) {
            $description = utils::format_text_for_meta($description, 'text', FORMAT_HTML);
            $output .= "<meta name='description' content='{$description}'>";
        }

        return $output;
    }

    /**
     * Add course schema markup to the page and desctiption.
     * @param  string $output
     * @return void
     */
    private function course(&$output = '') {
        global $COURSE, $CFG, $SITE;
        $course = !empty($COURSE) ? fullclone($COURSE)
                                    : (!empty($this->page->course) ? fullclone($this->page->course)
                                    : @get_course($this->context->instanceid));

        if ($course) {
            $course  = new \core_course_list_element($course);
            $helper  = new \coursecat_helper();
            $summary = utils::format_text_for_meta($helper->get_course_formatted_summary($course));
            $output .= $this->add_description($summary ?? '');

            if ($course->id == SITEID) {
                return;
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
                'url'              => $this->page->url->out(false),
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

            $output .= '<script type="application/ld+json">'
                    . json_encode($courseschema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
        }
    }

    /**
     * Add course category schema markup to the page and desctiption.
     * @param  string $output
     * @return void
     */
    private function coursecat(&$output = '') {
        $categoryid = $this->context->instanceid;
        $category   = \core_course_category::get($categoryid, IGNORE_MISSING);
        $cathelper  = new \coursecat_helper();

        if ($category) {
            $summary = utils::format_text_for_meta($cathelper->get_category_formatted_description($category));
            $output .= $this->add_description($summary ?? '');
            // Todo: Add schema markup for categories.
        }
    }

    /**
     * Add module desctiption meta tag from module intro.
     * @param  string $output
     * @return void
     */
    private function module(&$output = '') {
        global $DB;
        $moduleid = $this->context->instanceid;
        $sql      = 'SELECT cm.id, cm.instance, m.name as modname
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id
                WHERE cm.id = :moduleid';
        $params = ['moduleid' => $moduleid];

        try {
            $module   = $DB->get_record_sql($sql, $params);
            $instance = $DB->get_record($module->modname, ['id' => $module->instance], 'id, intro, introformat');
            // Todo: In case of forum discussion page, get the post content as description.
        } catch (\Throwable $e) {
            echo '<pre>';
            var_dump(get_exception_info($e));
            echo '</pre>';

            return;
        }

        if ($instance) {
            $summary = utils::format_text_for_meta($instance->intro, 'text', $instance->introformat);
            $output .= $this->add_description($summary ?? '');
        }
    }

    /**
     * Handle blog pages meta tags and schema.
     * @param  string $output
     * @return void
     */
    private function blog(&$output = '') {
        // Todo: Get part of the blog post as description.
        // Todo: Add schema markup for blog post as article.
    }

    /**
     * Handle public profiles pages.
     * @param  string $output
     * @return void
     */
    private function profile(&$output = '') {
        // Todo: get user description as description.
        // Todo Add schema markup as public figure or something like that.
    }

    /**
     * Handle pages like in local_pg.
     * @param  string $output
     * @return void
     */
    private function page(&$output = '') {
        // Todo add part of the page content as description.
    }
}
