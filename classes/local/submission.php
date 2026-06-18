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
 * Case Study submission class
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local;

use core_user;
use mod_casestudy\local\field_manager;
use mod_casestudy\local\helper;
use stdClass;

/**
 * Renderable domain wrapper around a {casestudy_submissions} row.
 *
 * Exposes the submission to mustache via {@see export_for_template} and caches per-id instances
 * for the duration of a request to keep template rendering cheap.
 */
class submission implements \renderable, \templatable {
    /** @var self[] In-process instance cache keyed by submission id. */
    public static $instance;

    /** @var casestudy Activity wrapper this submission belongs to. */
    protected casestudy $casestudy;

    /** @var \stdClass Raw {casestudy_submissions} row. */
    protected $submission;

    /** @var \cm_info|\stdClass Course module record. */
    protected $cm;

    /**
     * Return a cached submission wrapper for the given id, building one on first call.
     *
     * @param int $submissionid Submission id.
     * @return self
     */
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
        $content = $DB->get_records(
            'casestudy_content',
            ['submissionid' => $submission->id],
            '',
            'fieldid, content, contentformat'
        );

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
