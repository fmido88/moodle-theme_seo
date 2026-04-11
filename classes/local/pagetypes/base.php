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

use core\exception\coding_exception;
use theme_seo\seo;

/**
 * Class base.
 *
 * @package    theme_seo
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * The loaded description for this page type.
     * @var string
     */
    protected string $description;

    /**
     * Schema markup for this page type.
     * @var array
     */
    protected array $schema;

    /**
     * Prepare the meta tags according to the type
     * of the page.
     * @param seo $seo
     */
    final public function __construct(
        /** @var seo */
        protected seo $seo,
    ) {
    }

    /**
     * Generate and return description.
     * @return string
     */
    abstract protected function description(): string;

    /**
     * Generate and return the schema markup.
     * @return ?array
     */
    protected function schema_markup(): ?array {
        return null;
    }

    /**
     * Load description and markup schema to the seo object.
     * @param  string $originaldesc
     * @return void
     */
    final public function load(string $originaldesc = ''): void {
        $description = $this->description();
        $this->seo->set_description($description, $originaldesc);

        if ($schema = $this->schema_markup()) {
            $this->seo->set_schema_markup($schema);
        }
    }

    /**
     * Lazy way to load.
     * @param  seo    $seo
     * @param  string $originaldesc
     * @return void
     */
    final public static function load_all(seo $seo, string $originaldesc = ''): void {
        $type = new static($seo);
        $type->load($originaldesc);
    }

    /**
     * Return class names of page types classes.
     * @return base[]|string[]
     */
    final public static function get_type_classes(): array {
        $types = [
            'home',
            'course',
            'coursecat',
            'module',
            'page',
            'profile',
            'blog',
            'null_type',
        ];
        $classes = array_map(fn ($type) => __NAMESPACE__ . "\\$type", $types);

        // Todo: add a hook to get page type classes from other plugins with ordering options.
        return array_filter($classes, fn ($class) => class_exists($class) && is_subclass_of($class, self::class));
    }

    /**
     * Get instance of the page type class for the current page.
     * @param  seo              $seo
     * @throws coding_exception
     * @return base
     */
    final public static function get_page_type_class(seo $seo): self {
        $classnames = self::get_type_classes();

        foreach ($classnames as $class) {
            if ($class::is_this_type($seo)) {
                return new $class($seo);
            }
        }

        // Should never reached as null_type always there and always return true.
        throw new coding_exception('Cannot specify the page type for the current page.');
    }

    /**
     * Check if the current page is belong to this type.
     * @param  seo  $seo
     * @return void
     */
    abstract public static function is_this_type(seo $seo): bool;
}
