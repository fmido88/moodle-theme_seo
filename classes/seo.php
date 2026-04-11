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

use cache;
use context;
use core\output\renderer_base;
use core_cache\cacheable_object_interface;
use core_collator;
use core_renderer;
use core_useragent;
use curl;
use html_writer;
use moodle_page;
use moodle_url;
use stdClass;
use theme_seo\local\pagetypes\base;

/**
 * SEO main class to format meta tags, title and other SEO related elements
 * Also to add.
 *
 * @package    theme_seo
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seo implements cacheable_object_interface {
    /**
     * Moodle page instance.
     * @var moodle_page
     */
    public moodle_page $page;

    /**
     * Current page url.
     * @var moodle_url
     */
    protected moodle_url $pageurl;

    /**
     * SEO record for the current page.
     * @var object{
     *      id:              int,
     *      page_path:       string,
     *      page_params:     string,
     *      title:           string,
     *      overridetitle:   int,
     *      meta_description:string,
     *      overridedesc:    int,
     *      main_keyword:    string,
     *      sub_keywords:    string,
     *      overridekeys:    int,
     *      indexable:       int|bool
     * }
     */
    protected stdClass $record;

    /**
     * Is the page public or not.
     * @var ?bool
     */
    protected ?bool $ispublic;

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
     * The schema markup as associative array.
     * @var array
     */
    protected array $schemamarkup;

    /**
     * The page title.
     * @var string
     */
    protected string $title;

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
    protected string $content = '';

    /**
     * SEO constructor.
     * @param moodle_page|null       $page
     * @param string|moodle_url|null $pageurl
     * @param ?stdClass               $cacheddata
     * @return void
     */
    public function __construct(
        ?moodle_page $page = null,
        string|moodle_url|null $pageurl = null,
        ?stdClass $cacheddata = null,
    ) {
        global $PAGE, $DB;

        $this->page = $page ?? $PAGE;

        $this->context = $this->page->context;

        if (empty($pageurl)) {
            $pageurl = $this->page->has_set_url() ? $this->page->url : qualified_me();
        }

        $this->pageurl = new moodle_url($pageurl);

        if (self::is_testing()) {
            // Don't load cached data or perform another curl request
            // during testing to avoid infinite loop.
            return;
        }

        if (!empty($cacheddata)) {
            foreach ($cacheddata as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }

        try {
            $this->ispublic ??= $this->is_public_page();
        } catch (\Throwable $e) {
            if (AJAX_SCRIPT) {
                throw $e;
            }
            if (error_reporting() >= DEBUG_ALL) {
                $exception = get_exception_info($e);
                $exception->backtrace = format_backtrace((array)($exception->backtrace));
                $debug = '';

                foreach ($exception as $key => $value) {
                    $debug .= "$key: $value<br>";
                }
                debugging($debug, DEBUG_ALL);
            }
        }

        $this->load_record();
        // Only update caches from the ajax request.
        !AJAX_SCRIPT || $this->update_cached();
    }

    /**
     * Prepare the class to cache.
     * @return stdClass
     */
    public function prepare_to_cache() {
        $data = new stdClass();
        $data->ispublic = $this->is_public_page();
        $data->redirect = $this->is_redirected();
        $data->redirecturl = $this->redirecturl;
        $data->indexable = $this->is_indexable();
        $data->pageurl = $this->get_url()->out(false);

        // For now we just store the main properties of the page to avoid testing the page through curl
        // which delay the page loading significantly. Now the testing is through ajax only.
        // Don't store content or curlinfo yet as it is only needed in testing the page which not use cache.

        // Caching schema markup, description, keywords and record data.
        $metaproperties = ['record', 'keywords', 'description', 'schemamarkup', 'title'];

        foreach ($metaproperties as $property) {
            if (isset($this->$property)) {
                $data->$property = $this->$property;
            }
        }

        $page = new stdClass();
        $page->title = $this->page->title;
        $page->heading = $this->page->heading;
        $page->contextid = $this->get_context()->id;
        $page->url = $this->page->has_set_url() ? $this->page->url->out(false) : null;
        $page->pagetype = $this->page->pagetype;
        $page->course = $this->page->course;

        $data->page = $page;

        return $data;
    }

    /**
     * Restore the class from the cache.
     * @param  stdClass $data
     * @return seo
     */
    public static function wake_from_cache($data) {
        $cachedpage = fullclone($data->page);
        unset($data->page);

        if (AJAX_SCRIPT) {
            $page = new moodle_page();

            $context = \core\context::instance_by_id($cachedpage->contextid);
            $page->set_context($context);

            foreach ($cachedpage as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                $method = "set_{$key}";
                $page->$method($value);
            }
        }

        return new self($page ?? null, null, $data);
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
     * @param  ?moodle_page       $page
     * @return seo
     */
    public static function get(moodle_url|string $url, ?moodle_page $page = null): self {
        $url = new moodle_url($url);
        $key = md5($url->out(false));

        if (isset(self::$cached[$key])) {
            return self::$cached[$key];
        }

        $cache = cache::make('theme_seo', 'seo');

        if ($seo = $cache->get($key)) {
            return $seo;
        }

        $seo = new self($page, $url);

        return $seo;
    }

    /**
     * Update the cached SEO instance.
     * @return void
     */
    public function update_cached(): void {
        if ($this->ispublic === null) {
            // Not loaded to be cached.
            return;
        }
        $key = md5($this->get_url()->out(false));

        self::$cached[$key] = $this;
        $cache = cache::make('theme_seo', 'seo');
        $cache->set('seo', $this);
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
        $pagepath = $this->get_page_url_path();

        $urlparams = $this->get_url_params();

        $params = ['pagepath' => $DB->sql_like_escape($pagepath)];
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
        $loginpages = ['login-index', 'login-signup'];

        if ($allowindexing == 2 || ($allowindexing == 0 && \in_array($this->page->pagetype, $loginpages))) {
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
     * Get the response that contains the page content as the crawler suppose
     * to see it.
     * @param  bool      $forpreview
     * @return ?stdClass
     */
    public function get_crawler_page_content(bool $forpreview = false): ?stdClass {
        global $CFG;
        require_once("$CFG->libdir/filelib.php");
        $curl = new curl(['ignoresecurity' => true]);
        $curl->setopt(['CURLOPT_USERAGENT' => core_useragent::get_moodlebot_useragent() . ' (Moodle SEO)']);

        $params = [
            'url'     => $this->pageurl->out(false),
            'preview' => $forpreview,
            'debug'   => false,
        ];

        $rawresponse = $curl->post((new moodle_url('/theme/seo/testpage.php', $params))->out(false));
        $curlerrno = $curl->get_errno();
        $info = $curl->get_info();

        if (!empty($curlerrno) || $info['http_code'] != 200) {
            $this->ispublic = false;

            return null;
        }

        if (!$response = json_decode($rawresponse)) {
            // Wrong response and this may be internal error
            // don't initialize $ispublic.
            // Todo: debug this if happen.
            $this->curlinfo = []; // Avoid endless loop.
            return null;
        }

        $this->ispublic = !(bool)$response->error;

        if (!$this->ispublic) {
            return $response;
        }

        $this->curlinfo = $response->info;

        $curlurl = new moodle_url($response->info->url);

        if ($curlurl->compare($this->get_url(), URL_MATCH_BASE)) {
            if (empty($response->info->redirect_url)) {
                $this->redirect = false;
            } else {
                $this->redirect = true;
                $this->redirecturl = new moodle_url($response->info->redirect_url);
            }
        } else {
            $this->redirect = true;
            $this->redirecturl = $curlurl;
        }

        $this->content = $response->cleanedcontent;

        return $response;
    }

    /**
     * Check if the page is public or not.
     * @return bool
     */
    public function is_public_page(): ?bool {
        if (isset($this->ispublic)) {
            return $this->ispublic;
        }

        if (self::is_testing()) {
            // Already reached by test page.
            return true;
        }

        if (!isloggedin() || isguestuser()) {
            // Already accessed by guest user no need to check.
            return true;
        }

        if (!AJAX_SCRIPT) {
            // Don't initialize properties unless this is Ajax.
            return null;
        }

        $this->get_crawler_page_content();

        return $this->ispublic;
    }

    /**
     * Get the preview url for the page.
     * @return \core\url
     */
    public function get_preview_url(): moodle_url {
        return new moodle_url('/theme/seo/preview.php', [
            'url'              => $this->get_url()->out(false),
            'contextid'        => $this->get_context()->id,
            'page[title]'      => $this->page->title,
            'page[heading]'    => $this->page->heading,
            'page[pagelayout]' => $this->page->pagelayout,
            'page[pagetype]'   => $this->page->pagetype,
        ]);
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
    public function is_redirected(): ?bool {
        if (!isset($this->curlinfo) && AJAX_SCRIPT) {
            $this->is_public_page();
        }

        return $this->redirect ?? null;
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
        return utils::extract_url_path($this->get_url());
    }

    /**
     * Get the page url params.
     * @return array
     */
    public function get_url_params(): array {
        $params = $this->get_url()->params();
        core_collator::ksort($params);

        return $params;
    }

    /**
     * Get the page url.
     * @return ?moodle_url
     */
    public function get_url(): ?moodle_url {
        $url = ($this->is_redirected() && !empty($this->redirecturl)) ? $this->redirecturl : $this->pageurl;

        if ($url === null) {
            return $url;
        }
        $url = clone $url;
        $params = $url->params();
        $url->remove_all_params();
        core_collator::ksort($params);

        // Todo: we actully should exclude some non-important parameter like 'redirect'
        // in the homepage and other to avoid multiple instances for the same page.
        return new moodle_url($url, $params);
    }

    /**
     * Get the page title.
     * @param  string $original
     * @return string
     */
    public function page_title(string $original = ''): string {
        global $SITE;

        if (isset($this->title)) {
            return $this->title;
        }

        if (empty($original)) {
            $original = $this->page->title;
        }

        $hasrecordtitle = isset($this->record->overridetitle) && !empty($this->record->title);

        if ($hasrecordtitle) {
            if ((int)($this->record->overridetitle) === self::OVERRIDE_REPLACE) {
                $this->title = utils::shorten_text(utils::format_string($this->record->title));

                return $this->title;
            }
        }

        if (!empty($original) && str_word_count($original) > 3) {
            // Already had a title.
            $pagetitle = $original;
        } else {
            if ($hasrecordtitle && (int)($this->record->overridetitle) === self::OVERRIDE_NOTEXIST) {
                $this->title = utils::shorten_text(utils::format_string($this->record->title));

                return $this->title;
            }

            $sitename = utils::format_string($SITE->fullname);

            $heading = empty($original) ? $this->page->heading : $original;

            // Override title by heading and append sitename.
            if (!empty($heading)) {
                $heading = utils::format_string($heading);
                $pagetitle = "{$heading} | {$sitename}";
            } else {
                $pagetitle = $sitename;
            }
        }

        if ($hasrecordtitle && (int)($this->record->overridetitle) === self::OVERRIDE_CONCAT) {
            $pagetitle .= ' ' . utils::format_string($this->record->title);
        }

        // Recommended charachters is 70.
        $this->title = utils::shorten_text($pagetitle);

        return $this->title;
    }

    /**
     * Get the robots meta tag to be added.
     * @return string
     */
    protected function get_robots_meta(): string {
        $instructions = [];
        $instructions[] = $this->is_indexable() ? 'index' : 'noindex';
        $instructions[] = 'follow';
        // Todo: add advanced options in the page settings to override the robots tag.
        $content = implode(', ', $instructions);

        return "<meta name=\"robots\" content=\"$content\" />";
    }

    /**
     * Get meta tags to be added to the html head.
     * @param  mixed  $origdescritpion
     * @param  mixed  $origkeywords
     * @return string
     */
    public function pre_head_html($origdescritpion = '', $origkeywords = ''): string {
        $output = '';

        if ($this->metaadded) {
            return $output;
        }

        try {
            $output .= $this->get_robots_meta();

            // If URL should not be indexed, add the noindex meta tag to page.
            if (!$this->is_indexable()) {
                $this->metaadded = true;

                return $output;
            }

            if (self::is_testing()) {
                $this->metaadded = true;

                return $output;
            }

            if (!$this->is_loaded()) {
                $this->load_keywords($origkeywords);

                // Load schema markup and description according to page type.
                $pagetype = base::get_page_type_class($this);
                $pagetype->load($origdescritpion);

                $this->update_cached();
            }

            $output .= $this->get_keywords_meta();
            $output .= $this->get_description_meta();
            $output .= $this->get_schema_markup_tag();

            $this->metaadded = true;
        } catch (\Throwable $e) {
            $exception = get_exception_info($e);
            $exception->backtrace = format_backtrace($exception->backtrace, true);
            $debug = '<pre>';

            foreach ($exception as $key => $value) {
                $debug .= "$key: $value<br>\n";
            }
            $debug .= '</pre>';
            debugging($debug, DEBUG_ALL);
        }

        return $output;
    }

    /**
     * Setter for markup schema.
     * @param  array $schema
     * @return void
     */
    public function set_schema_markup(array $schema): void {
        $this->schemamarkup = $schema;
    }

    /**
     * Return the meta tag of markup schema.
     * @return string
     */
    protected function get_schema_markup_tag(): string {
        if (empty($this->schemamarkup)) {
            return '';
        }

        $schema = @json_encode($this->schemamarkup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (empty($schema)) {
            ob_start();
            var_dump($this->schemamarkup);
            $debug = ob_get_clean();
            $debug .= json_last_error_msg();
            debugging("The schema markup provided <pre>{$debug}</pre> is not valid", DEBUG_DEVELOPER);

            return '';
        }

        return "<script type=\"application/ld+json\">$schema</script>\n";
    }

    /**
     * Load the keywords.
     * @param  array|string $default
     * @return void
     */
    protected function load_keywords(array|string $default = ''): void {
        $behaviour = (int)($this->record->overridekeys ?? self::OVERRIDE_CONCAT);
        $storedkeys = $this->get_stored_keywords();

        if (!empty($storedkeys) && $behaviour === self::OVERRIDE_REPLACE) {
            $this->keywords = $storedkeys;

            return;
        }

        $defaultkeys = $this->get_default_keywords();
        $existedkeys = [];

        if (!empty($default)) {
            if (is_string($default)) {
                $default = explode(',', $default);
            }
            $existedkeys = array_filter(array_map('trim', $default));
        }

        $keywords = array_unique(array_merge($existedkeys, $defaultkeys));

        if ($behaviour === self::OVERRIDE_NOTEXIST && count($keywords) > 2) {
            $this->keywords = $keywords;

            return;
        }

        $this->keywords = array_unique(array_merge($keywords, $storedkeys));
    }

    /**
     * Add keywords meta tag to the page.
     * @return string
     */
    protected function get_keywords_meta(): string {
        $keywords = $this->get_keywords();
        // Max recomended 10 keywords.
        $keywords = array_slice($keywords, 0, 10);
        $keywords = array_map([utils::class, 'minify_text'], $keywords);

        return "<meta name='keywords' content='" . implode(', ', $keywords) . "'/>";
    }

    /**
     * Generate and return default keywords for this page
     * check for tags in this context and parent contexts
     * and the page title and header.
     * @return array
     */
    public function get_default_keywords() {
        $tags = self::get_tags();
        $keywords = [];

        if (\count($tags) < 5) {
            $pagetitle = $this->page->title;
            $heading = $this->page->heading;

            if (!empty($heading)) {
                $keywords = array_merge(explode('|', $heading), $keywords);
            } else if (!empty($pagetitle)) {
                $keywords = array_merge(explode('|', $pagetitle), $keywords);
            }
        }

        return array_unique(array_filter(array_map('trim', \array_merge($keywords, $tags))));
    }

    /**
     * Get the keywords stored in record.
     * @return array
     */
    public function get_stored_keywords() {
        $keywords = [];

        if (isset($this->record->overridekeys)) {
            if (!empty($this->record->main_keyword)) {
                $keywords[] = trim($this->record->main_keyword);
            }

            if (!empty($this->record->sub_keywords)) {
                $subkeywords = array_filter(array_map(utils::class . '::format_string', explode(',', $this->record->sub_keywords)));
                $keywords = array_unique(array_merge($keywords, $subkeywords));
            }
        }

        return $keywords;
    }

    /**
     * Get the keywords for the page.
     * @return array
     */
    public function get_keywords() {
        if (!empty($this->keywords)) {
            return $this->keywords;
        }

        return [];
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
            $contextsids = array_map(fn (\core\context $cont) => $cont->id, $this->context->get_parent_contexts(true));
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
     * Setter for page description.
     * @param  string $generated
     * @param  string $original
     * @return void
     */
    public function set_description(string $generated, string $original = ''): void {
        $behaviour = (int)($this->record->overridedesc ?? self::OVERRIDE_CONCAT);
        $stored = '';

        if (!empty($this->record->meta_description)) {
            $stored = utils::format_text_for_meta($this->record->meta_description, 'text', FORMAT_MOODLE);
        }

        $original = !empty($original) ? utils::format_text_for_meta($original, 'text', FORMAT_MOODLE) : '';
        $generated = !empty($generated) ? utils::format_text_for_meta($generated) : '';

        $description = match(true) {
            $behaviour === self::OVERRIDE_NOTEXIST && !empty($original)   => $original,
            $behaviour === self::OVERRIDE_NOTEXIST && !empty($generated)  => $generated,
            $behaviour === self::OVERRIDE_REPLACE && !empty($stored)      => $stored,
            $behaviour === self::OVERRIDE_CONCAT                          => implode(' ', [$original, $generated, $stored]),
            default                                                       => $original ?: $generated,
        };
        $this->description = $description;
    }

    /**
     * Get the description meta tag for this page.
     * @return string
     */
    protected function get_description_meta(): string {
        $description = $this->description;
        $tag = '';

        if (!empty($description)) {
            $description = utils::format_text_for_meta($description, 'text', FORMAT_HTML);
            // Recommended 150 - 160 char.
            $description = utils::shorten_text($description, 160);
            $tag = "<meta name=\"description\" content=\"{$description}\">";
        }

        return $tag;
    }

    /**
     * Check if the description and keywords is loaded of not.
     * @return bool
     */
    protected function is_loaded(): bool {
        return isset($this->description) && !empty($this->keywords);
    }
}
