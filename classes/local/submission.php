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
 * Case Study submission class
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\local;

use core_user;
use mod_casestudy\local\field_manager;
use mod_casestudy\local\helper;
use stdClass;

class submission implements \renderable, \templatable {

    public static $instance;

    protected casestudy $casestudy;

    protected $submission;

    protected $cm;

    public static function instance(int $submissionid): submission {

        if (empty(self::$instance[$submissionid])) {
            self::$instance[$submissionid] = new self($submissionid);
        }

        return self::$instance[$submissionid];
    }

    /**
     *
     */
    public function __construct(int $submissionid, $cm = null, $context = null) {
        global $DB;

        $this->submission = helper::get_submission($submissionid);
        $this->cm = $cm ?: get_coursemodule_from_instance('casestudy', $this->submission->casestudyid, 0, false, MUST_EXIST);

    }

    /**
     * Get submission record.
     *
     * @return stdClass Submission record
     */
    public function get_submission(): stdClass {
        return $this->submission;
    }

    /**
     * Get the case study instance.
     *
     * @return casestudy The case study record
     */
    public function get_casestudy(): casestudy {

        if (empty($this->casestudy)) {
            $this->casestudy = casestudy::instance($this->submission->casestudyid);
        }

        return $this->casestudy;
    }

    /**
     * Get the course module of the case study instance.
     *
     * @return cm_info The course module instance
     */
    public function export_for_template(\renderer_base $output): array {
        global $DB;

        $submission = clone $this->submission;

        if (empty($submission)) {
            throw new \moodle_exception('invalidsubmission', 'mod_casestudy');
        }

        $user = core_user::get_user($submission->userid);

        // Get fields and submission content.
        $content = $DB->get_records('casestudy_content',
            array('submissionid' => $submission->id), '', 'fieldid, content, contentformat');

        $casestudyinfo = [
            'submission' => $submission,
            'user'       => $user,
            'student' => fullname($user),
            'statusclass' => helper::get_status_info($submission->status, 'class'),
            'statuslabel' => get_string('status_' . $submission->status, 'mod_casestudy'),
            'cm' => $this->cm,
            'context' => \context_module::instance($this->cm->id),
            'timecreated' => userdate($submission->timecreated),
            'timemodified' => userdate($submission->timemodified),
            'timesubmitted' => $submission->timesubmitted ? userdate($submission->timesubmitted) : null,
        ];

        // Format fields with content using updated field type methods
        $formattedfields = [];
        $formattedfieldsbyshortname = [];
        $fieldmanager = field_manager::instance($submission->casestudyid);

        foreach ($fieldmanager->get_fields() as $field) {
            $fieldclass = $fieldmanager->get_field_type_class($field->type, $field);

            if (empty($fieldclass)) {
                // Skip unknown field types.
                continue;
            }
            $value = isset($content[$field->id]) ? $content[$field->id]->content : '';
            $hasvalue = !empty(trim($value));

            $value = $fieldclass->render_display($value, $submission->id);

            $fielddata = [
                'shortname' => $field->shortname ?? strtolower(str_replace(' ', '', $field->name)),
                'name' => format_string($field->name),
                'description' => !empty($field->description) ? format_text($field->description) : null,
                'value' => $value ?: format_text($value),
                'hasvalue' => $field->type == 'sectionheading' ? true : $hasvalue,
                'type' => $field->type,
                'id' => $field->id,
            ];

            // For mustache templates (indexed array)
            $formattedfields[] = $fielddata;

            // For custom templates - (associative array by shortname)
            $formattedfieldsbyshortname[$fielddata['shortname']] = $fielddata;
        }

        $templatecontext = [
            'casestudyinfo' => $casestudyinfo,
            'fields' => $formattedfields,
            'fieldsbyshortname' => $formattedfieldsbyshortname,
        ];

        return $templatecontext;
    }
}

