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

/**
 * Field Data class for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\local;

use stdClass;

class field_data {


    public $params = ['content1', 'content2', 'content3', 'content4'];

    protected $result = null;

    public function __construct(?stdClass $data=null) {
        $this->result = $data;
    }

    public static function create(?stdClass $data=null) : field_data {
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

    public function to_record() : stdClass {

        if (!property_exists($this->result, 'content')) {
            throw new \coding_exception('Field data must have content property to be converted to record.');
        }

        return $this->result ?? new stdClass();
    }

}