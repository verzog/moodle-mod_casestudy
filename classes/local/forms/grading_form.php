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
 * Dynamic Grading Form class for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\forms;

use mod_casestudy\local\helper;
use mod_casestudy\local\submission_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Dynamic grading form for case study submissions
 */
class grading_form extends \core_form\dynamic_form {
    protected $casestudyobj;

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        // Hidden fields
        $mform->addElement('hidden', 'submissionid');
        $mform->setType('submissionid', PARAM_INT);

        $mform->addElement('hidden', 'casestudyid');
        $mform->setType('casestudyid', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('hidden', 'id'); // Course module ID
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid'); // User ID.
        $mform->setType('userid', PARAM_INT);

        // Student name (read-only display)
        $submission = $this->get_submission();
        if ($submission) {
            $mform->addElement(
                'static',
                'studentname',
                get_string('student', 'mod_casestudy'),
                fullname($submission)
            );
        }

        // Check if submission is already graded and user has regrade capability.
        $isfinished = $submission && in_array($submission->status, [CASESTUDY_STATUS_SATISFACTORY, CASESTUDY_STATUS_UNSATISFACTORY]);
        $cmid = $this->optional_param('id', 0, PARAM_INT);
        $context = $cmid ? \context_module::instance($cmid) : null;
        $canregrade = $context && has_capability('mod/casestudy:regrade', $context);

        // Show warning if submission is finished and user cannot regrade.
        if ($isfinished && !$canregrade) {
            $mform->addElement(
                'static',
                'regradewarning',
                '',
                '<div class="alert alert-warning">' . get_string('cannotregrade', 'mod_casestudy') . '</div>'
            );
        }

        // Marker comments
        $mform->addElement(
            'editor',
            'feedback_editor',
            get_string('markercomments', 'mod_casestudy'),
            ['rows' => 6],
            $this->get_editor_options()
        );
        $mform->setType('feedback_editor', PARAM_RAW);

        // Notify student checkbox
        $casestudyid = $this->optional_param('casestudyid', 0, PARAM_INT);
        if ($casestudyid) {
            global $DB;
            $casestudy = $DB->get_record('casestudy', ['id' => $casestudyid]);
            if ($casestudy) {
                $mform->addElement('advcheckbox', 'notifystudent', get_string('notifystudent', 'mod_casestudy'));
                $mform->setDefault('notifystudent', $casestudy->notifystudentdefault);
                $mform->addHelpButton('notifystudent', 'notifystudent', 'mod_casestudy');
            }
        }

        // Advanced grading or traditional grade selection
        $isadvancedgrading = $this->add_grading_elements();

        $mform->addElement('hidden', 'submitaction');
        $mform->setType('submitaction', PARAM_ALPHA);

        // Only show action buttons if user can grade (not finished or is admin).
        if (!$isfinished || $canregrade) {
            // Action buttons group
            $actiongroup = [];

            // Save feedback
            $actiongroup[] = $mform->createElement(
                'submit',
                'savefeedback',
                get_string('savefeedback', 'mod_casestudy'),
                ['class' => 'grade-primary']
            );

            // Save and request resubmission
            $actiongroup[] = $mform->createElement(
                'submit',
                'saverequestresubmission',
                get_string('saverequestresubmission', 'mod_casestudy'),
                ['class' => 'grade-warning']
            );

            // Mark as satisfactory
            $actiongroup[] = $mform->createElement(
                'submit',
                'marksatisfactory',
                get_string('marksatisfactory', 'mod_casestudy'),
                ['class' => 'grade-success']
            );

            // Mark as unsatisfactory
            $actiongroup[] = $mform->createElement(
                'submit',
                'markunsatisfactory',
                get_string('markunsatisfactory', 'mod_casestudy'),
                ['class' => 'grade-danger']
            );

            // Cancel
            $actiongroup[] = $mform->createElement(
                'cancel',
                'cancel',
                get_string('cancel'),
                ['class' => 'grade-secondary']
            );

            $mform->addGroup($actiongroup, 'actions', '', ' ', false);
        }
    }

    /**
     * Add user selector with autocomplete and navigation buttons
     */
    protected function add_user_selector() {
        global $DB, $OUTPUT;
        $mform = $this->_form;

        $cmid = $this->optional_param('id', 0, PARAM_INT);
        $currentsubmissionid = $this->optional_param('submissionid', 0, PARAM_INT);

        if (!$cmid || !$currentsubmissionid) {
            return;
        }

        $cm = get_coursemodule_from_id('casestudy', $cmid);
        if (!$cm) {
            return;
        }

        $context = \context_module::instance($cm->id);
        $casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

        // Get current submission details
        $currentsubmission = $DB->get_record('casestudy_submissions', ['id' => $currentsubmissionid]);
        if (!$currentsubmission) {
            return;
        }

        // Get all submissions for this case study
        $submissions = $this->get_all_submissions_for_selector($casestudy->id, $cm, $context);

        if (empty($submissions)) {
            return;
        }

        // Calculate current index
        $currentindex = 0;
        $totalcount = count($submissions);
        $submissionids = array_keys($submissions);

        foreach ($submissions as $index => $submission) {
            if ($submission->id == $currentsubmissionid) {
                $currentindex = $index + 1;
                break;
            }
        }

        // Create the HTML for the user navigation component
        $larrow = $OUTPUT->larrow();
        $rarrow = $OUTPUT->rarrow();

        $navhtml = '
        <div class="casestudy-user-navigation-wrapper" data-region="user-selector">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <a href="#" data-action="previous-user" class="btn btn-secondary"
                   aria-label="' . get_string('previous') . '"
                   title="' . get_string('previous') . '"
                   ' . ($currentindex <= 1 ? 'disabled' : '') . '>' . $larrow . '</a>

                <div class="flex-grow-1">
                    <label for="id_change_user_select" class="sr-only">' . get_string('selectuser', 'mod_casestudy') . '</label>
                    <select id="id_change_user_select"
                            name="change_user_select"
                            data-action="change-user"
                            data-currentsubmissionid="' . $currentsubmissionid . '"
                            data-casestudyid="' . $casestudy->id . '"
                            data-cmid="' . $cmid . '"
                            class="form-control">
                    </select>
                    <small class="form-text text-muted">
                        <span data-region="user-count-summary">' .
                            get_string('xofy', 'mod_casestudy', ['x' => $currentindex, 'y' => $totalcount]) .
                        '</span>
                    </small>
                </div>

                <a href="#" data-action="next-user" class="btn btn-secondary"
                   aria-label="' . get_string('next') . '"
                   title="' . get_string('next') . '"
                   ' . ($currentindex >= $totalcount ? 'disabled' : '') . '>' . $rarrow . '</a>
            </div>
        </div>';

        $mform->addElement('html', $navhtml);
    }

    /**
     * Get all submissions for user selector dropdown
     *
     * @param int $casestudyid
     * @param object $cm
     * @param object $context
     * @return array
     */
    protected function get_all_submissions_for_selector($casestudyid, $cm, $context) {
        global $DB;

        // Check for group filtering
        $groupid = groups_get_activity_group($cm, true);

        // Only show submissions that have been submitted (exclude new and draft statuses).
        $submittedstatuses = [
            CASESTUDY_STATUS_SUBMITTED,
            CASESTUDY_STATUS_IN_REVIEW,
            CASESTUDY_STATUS_AWAITING_RESUBMISSION,
            CASESTUDY_STATUS_RESUBMITTED,
            CASESTUDY_STATUS_RESUBMITTED_INREVIEW,
            CASESTUDY_STATUS_SATISFACTORY,
            CASESTUDY_STATUS_UNSATISFACTORY,
        ];
        [$statusinsql, $statusparams] = $DB->get_in_or_equal($submittedstatuses, SQL_PARAMS_NAMED, 'status');

        $sql = "SELECT s.id, s.userid, s.attempt, s.status, s.timesubmitted,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email
                  FROM {casestudy_submissions} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.casestudyid = :casestudyid
                   AND s.status $statusinsql";

        $params = array_merge(['casestudyid' => $casestudyid], $statusparams);

        // Add group filter if needed
        if ($groupid) {
            $groupmembers = groups_get_members($groupid, 'u.id');
            if (!empty($groupmembers)) {
                [$insql, $inparams] = $DB->get_in_or_equal(array_keys($groupmembers), SQL_PARAMS_NAMED);
                $sql .= " AND s.userid $insql";
                $params = array_merge($params, $inparams);
            } else {
                // No members in group, return empty
                return [];
            }
        }

        $sql .= " ORDER BY u.lastname ASC, u.firstname ASC, s.attempt DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Add grading elements (advanced grading or traditional grade selection)
     */
    protected function add_grading_elements() {
        $mform = $this->_form;

        // Get the casestudy instance and context
        $cmid = $this->optional_param('id', 0, PARAM_INT);
        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);

        if (empty($submissionid)) {
            $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);
        }

        $cm = get_coursemodule_from_id('casestudy', $cmid);
        if (!$cm) {
            return;
        }
        $context = \context_module::instance($cm->id);

        $this->casestudyobj = new \mod_casestudy\local\casestudy($cm->instance, $cm, $context);
        return $this->casestudyobj->add_grading_elements($mform, $submissionid);
    }

    /**
     * Get editor options
     *
     * @return array Editor options
     */
    public function get_editor_options() {
        global $CFG;

        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'trusttext' => false,
            'subdirs' => true,
            'context' => $this->get_context_for_dynamic_submission(),
        ];
    }

    /**
     * Get submission data
     *
     * @return object|null Submission with user data
     */
    protected function get_submission() {
        global $DB;

        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);
        if (!$submissionid) {
            return null;
        }

        $sql = "SELECT
                    s.*, u.firstname, u.lastname, u.email, u.firstnamephonetic,
                    u.lastnamephonetic, u.middlename, u.alternatename
                FROM {casestudy_submissions} s
                JOIN {user} u ON u.id = s.userid
                WHERE s.id = ?";

        return $DB->get_record_sql($sql, [$submissionid]);
    }

    /**
     * Check access for dynamic submission
     *
     * @return void
     * @throws \moodle_exception
     */
    protected function check_access_for_dynamic_submission(): void {

        $casestudyid = $this->optional_param('casestudyid', 0, PARAM_INT);
        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);

        if (!$casestudyid || !$submissionid) {
            throw new \moodle_exception('invalidparameters');
        }

        // Get course module
        $cm = get_coursemodule_from_instance('casestudy', $casestudyid);
        if (!$cm) {
            throw new \moodle_exception('invalidcoursemodule');
        }

        $context = \context_module::instance($cm->id);
        require_capability('mod/casestudy:grade', $context);
    }

    /**
     * Get context for dynamic submission
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $casestudyid = $this->optional_param('id', 0, PARAM_INT);
        $cm = get_coursemodule_from_id('casestudy', $casestudyid);

        return \context_module::instance($cm->id);
    }

    /**
     * Get page URL for dynamic submission
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        $casestudyid = $this->optional_param('casestudyid', 0, PARAM_INT);
        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);

        $cm = get_coursemodule_from_instance('casestudy', $casestudyid);
        return new \moodle_url('/mod/casestudy/view_casestudy.php', [
            'id' => $cm->id,
            'submissionid' => $submissionid,
        ]);
    }

    /**
     * Process dynamic submission
     *
     * @return \stdClass
     */
    public function process_dynamic_submission() {
        global $DB;
        $data = $this->get_data();
        return $this->casestudyobj->process_grade_submission($data, $this);
    }

    /**
     * Set data for the form
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);
        if (!$submissionid) {
            return;
        }

        $submission = helper::get_submission($submissionid);
        $grade = $this->casestudyobj->get_user_grade($submission->userid, false, $submissionid);

        // Get existing feedback if any.
        $feedback = $DB->get_record('casestudy_grades', ['submissionid' => $submissionid]);

        $data = [];
        $data['userid'] = $submission->userid;
        $data['submissionid'] = $submissionid;
        $data['casestudyid'] = $submission->casestudyid;
        $data['id'] = $this->optional_param('id', 0, PARAM_INT);

        if ($grade) {
            $data['grade'] = $grade->grade;
        }

        if ($feedback) {
            $data['feedback_editor'] = [
                'text' => $feedback->feedback,
                'format' => $feedback->feedbackformat ?? FORMAT_HTML,
                'itemid' => 0,
            ];
            $data['grade'] = $feedback->grade;
            $data['requestresubmission'] = $feedback->requestresubmission;
        } else {
            // Initialize empty editor data to prevent validation errors
            $data['feedback_editor'] = [
                'text' => '',
                'format' => FORMAT_HTML,
                'itemid' => 0,
            ];
        }

        // Always set data, even if there's no feedback yet
        $this->set_data($data);
    }
}
