<?php
// Copyright (c) Skin Cancer College Australasia.
// All rights reserved.
//
// This file is part of a proprietary plugin developed by Skin Cancer
// College Australasia for use with Moodle. It is NOT free software and is
// NOT released under the GNU General Public License.
//
// Unauthorised copying, distribution, modification, or use of this file,
// in whole or in part, via any medium, is strictly prohibited without the
// prior written permission of Skin Cancer College Australasia. The software
// is provided "as is", without warranty of any kind, express or implied.

/**
 * Field Data class for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local;

use stdClass;

/**
 * Lightweight value object representing the persisted content for one field of one submission.
 *
 * Wraps a casestudy_content row and exposes typed accessors over the generic
 * `content`/`contentformat`/`content1..4` columns.
 */
class field_data {
    /** @var string[] Names of the auxiliary content columns this object can pass through. */
    public $params = ['content1', 'content2', 'content3', 'content4'];

    /** @var \stdClass|null Backing record (lazy-initialised). */
    protected $result = null;

    /**
     * Wrap an existing record, or create an empty value object ready to be populated.
     *
     * @param \stdClass|null $data Optional initial record to wrap.
     */
    public function __construct(?stdClass $data = null) {
        $this->result = $data;
    }

    /**
     * Static factory mirror of the constructor — keeps caller sites readable.
     *
     * @param \stdClass|null $data Optional initial record.
     * @return field_data
     */
    public static function create(?stdClass $data = null): field_data {
        return new field_data($data);
    }

    /**
     * Magic accessor for arbitrary record properties.
     *
     * @param string $name Property name on the underlying record.
     * @return mixed|null
     */
    public function __get($name) {
        return $this->result->$name ?? null;
    }

    /**
     * Set the primary content value.
     *
     * @param mixed $content Stored as the canonical `content` column.
     */
    public function set_content($content) {
        $this->result->content = $content;
    }

    /**
     * Get the primary content value.
     *
     * @return mixed|null
     */
    public function get_content() {
        return $this->result->content ?? null;
    }

    /**
     * Set the content format constant (e.g. FORMAT_HTML).
     *
     * @param int $format Moodle text format constant.
     */
    public function set_content_format($format) {
        $this->result->contentformat = $format;
    }

    /**
     * Get the content format constant.
     *
     * @return int|null
     */
    public function get_content_format() {
        return $this->result->contentformat ?? null;
    }

    /**
     * Set one of the auxiliary content columns (content1..content4).
     *
     * Silently ignores unknown parameter names so callers cannot smuggle data into
     * arbitrary columns via this setter.
     *
     * @param mixed $content Value to store.
     * @param string $param Column name; must be one of {@see self::$params}.
     */
    public function set_additional_content($content, $param) {
        if (in_array($param, $this->params)) {
            $this->result->$param = $content;
        }
    }

    /**
     * Get one of the auxiliary content columns (content1..content4).
     *
     * @param string $param Column name; must be one of {@see self::$params}.
     * @return mixed|null
     */
    public function get_additional_content($param) {
        if (in_array($param, $this->params)) {
            return $this->result->$param ?? null;
        }
        return null;
    }

    /**
     * Convert this value object back to a stdClass row ready for $DB->update_record.
     *
     * @throws \coding_exception if `content` was never set — the field is mandatory
     *         on the casestudy_content table.
     * @return \stdClass
     */
    public function to_record(): stdClass {

        if (!property_exists($this->result, 'content')) {
            throw new \coding_exception('Field data must have content property to be converted to record.');
        }

        return $this->result ?? new stdClass();
    }
}
