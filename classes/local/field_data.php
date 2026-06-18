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

class field_data {
    public $params = ['content1', 'content2', 'content3', 'content4'];

    protected $result = null;

    public function __construct(?stdClass $data = null) {
        $this->result = $data;
    }

    public static function create(?stdClass $data = null): field_data {
        return new field_data($data);
    }

    public function __get($name) {
        return $this->result->$name ?? null;
    }

    public function set_content($content) {
        $this->result->content = $content;
    }

    public function get_content() {
        return $this->result->content ?? null;
    }

    public function set_content_format($format) {
        $this->result->contentformat = $format;
    }

    public function get_content_format() {
        return $this->result->contentformat ?? null;
    }

    public function set_additional_content($content, $param) {
        if (in_array($param, $this->params)) {
            $this->result->$param = $content;
        }
    }

    public function get_additional_content($param) {
        if (in_array($param, $this->params)) {
            return $this->result->$param ?? null;
        }
        return null;
    }

    public function to_record(): stdClass {

        if (!property_exists($this->result, 'content')) {
            throw new \coding_exception('Field data must have content property to be converted to record.');
        }

        return $this->result ?? new stdClass();
    }
}
