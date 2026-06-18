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
 * Attempt Manager class for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\local;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages case study submissions and attempts
 */
class submission_manager {

    /** @var int Case study ID */
    private $casestudyid;

    /** @var object Case study instance */
    private $casestudy;

    /** @var object Course module */
    private $cm;

    /** @var object Context */
    private $context;

    protected $casestudyman;

    /**
     * Constructor
     *
     * @param int $casestudyid Case study ID
     * @param object $casestudy Case study instance (optional)
     * @param object $cm Course module (optional)
     */
    public function __construct($casestudyid, $casestudy = null, $cm = null) {
        global $DB;

        $this->casestudyid = $casestudyid;

        if ($casestudy) {
            $this->casestudy = $casestudy;
        } else {
            $this->casestudy = $DB->get_record('casestudy', ['id' => $casestudyid], '*', MUST_EXIST);
        }

        if ($cm) {
            $this->cm = $cm;
            $this->context = \context_module::instance($cm->id);
        }

        $this->casestudyman = new casestudy($this->casestudyid, $this->casestudy, $this->cm);
    }

    /**
     * Create a new submission
     *
     * @param int $userid User ID
     * @param int $groupid Group ID (default 0)
     * @param int $parentid Parent submission ID for resubmissions (default 0)
     * @return object New submission record
     */
    public function create_submission($userid, $groupid = 0, int $parentid = 0) {
        global $DB;

        // Get next attempt number
        $attempt = $this->get_next_attempt_number($userid);

        $submission = new \stdClass();
        $submission->casestudyid = $this->casestudyid;
        $submission->userid = $userid;
        $submission->groupid = $groupid;
        $submission->status = CASESTUDY_STATUS_NEW;
        $submission->attempt = $attempt;
        $submission->parentid = $parentid;
        $submission->timecreated = time();
        $submission->timemodified = time();
        $submission->timesubmitted = 0;

        $submission->id = $DB->insert_record('casestudy_submissions', $submission);

        // Trigger submission created event
        if ($this->cm) {
            $event = \mod_casestudy\event\submission_created::create_from_submission(
                $this->casestudy,
                $this->cm,
                $submission
            );
            $event->trigger();
        }

        return $submission;
    }

    /**
     * Get user's submission by status
     *
     * @param int $userid User ID
     * @param string $status Submission status (optional)
     * @return object|false Submission record or false
     */
    public function get_user_submission($userid, $status = null) {
        global $DB;

        $params = [
            'casestudyid' => $this->casestudyid,
            'userid' => $userid
        ];

        $sql = "SELECT * FROM {casestudy_submissions} WHERE casestudyid = :casestudyid AND userid = :userid";

        if ($status) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }

        $sql .= " ORDER BY attempt DESC";

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get submission record by ID
     *
     * @param int $submissionid Submission ID
     * @return object|false Submission record or false
     */
    public function get_submission_record($submissionid): stdClass|false {
        global $DB;

        return $DB->get_record('casestudy_submissions', ['id' => $submissionid, 'casestudyid' => $this->casestudyid]);
    }

    /**
     * Update submission
     *
     * @param object $submission Submission data
     * @return bool Success
     */
    public function update_submission($submission, $triggerupdate = true) {
        global $DB;

        $submission->timemodified = time();

        $result = $DB->update_record('casestudy_submissions', $submission);

        // Trigger submission updated event
        if ($result && $triggerupdate && $this->cm) {
            $event = \mod_casestudy\event\submission_updated::create_from_submission(
                $this->casestudy,
                $this->cm,
                $submission
            );
            $event->trigger();

            // Send notifications when submission is submitted (not just updated).
            if ($submission->status == CASESTUDY_STATUS_SUBMITTED || $submission->status == CASESTUDY_STATUS_RESUBMITTED) {
                $course = get_course($this->cm->course);

                // Send web notification to markers.
                \mod_casestudy\notification_helper::send_submission_notification(
                    $this->casestudy,
                    $submission,
                    $this->cm,
                    $course
                );

                // Send email confirmation to learner.
                \mod_casestudy\notification_helper::send_submission_confirmation(
                    $this->casestudy,
                    $submission,
                    $this->cm,
                    $course
                );
            }
        }

        return $result;
    }

    /**
     * Submit a case study (change status from draft to submitted)
     *
     * @param int $submissionid Submission ID
     * @return bool Success
     */
    public function submit_casestudy($submissionid) {
        global $DB;

        $submission = $this->get_submission_record($submissionid);
        if (!$submission || $submission->status !== CASESTUDY_STATUS_DRAFT) {
            return false;
        }

        $submission->status = CASESTUDY_STATUS_SUBMITTED;
        $submission->timesubmitted = time();
        $submission->timemodified = time();

        return $this->update_submission($submission);
    }

    /**
     * Get next attempt number for user
     *
     * @param int $userid User ID
     * @return int Next attempt number
     */
    public function get_next_attempt_number($userid) {
        global $DB;

        $sql = "SELECT MAX(attempt) FROM {casestudy_submissions} WHERE casestudyid = ? AND userid = ?";
        $maxattempt = $DB->get_field_sql($sql, [$this->casestudyid, $userid]);

        return ($maxattempt ? $maxattempt + 1 : 1);
    }

    /**
     * Get all submissions for grading navigation
     *
     * @param int $userid User ID (optional, for specific user)
     * @param int $groupid Group ID (optional, for group filtering)
     * @return array Array of submissions
     */
    public function get_submissions_for_grading($userid = null, $groupid = null) {
        global $DB;

        $params = ['casestudyid' => $this->casestudyid];
        $sql = "SELECT s.*, u.firstname, u.lastname, u.email
                FROM {casestudy_submissions} s
                JOIN {user} u ON u.id = s.userid
                WHERE s.casestudyid = :casestudyid
                AND s.status IN (:submitted, :in_review, :resubmitted, :resubmission_in_review)";

        $params['submitted'] = CASESTUDY_STATUS_SUBMITTED;
        $params['in_review'] = CASESTUDY_STATUS_IN_REVIEW;
        $params['resubmitted'] = CASESTUDY_STATUS_RESUBMITTED;
        $params['resubmission_in_review'] = 'resubmission_in_review';

        if ($userid) {
            $sql .= " AND s.userid = :userid";
            $params['userid'] = $userid;
        }

        if ($groupid) {
            $sql .= " AND s.groupid = :groupid";
            $params['groupid'] = $groupid;
        }

        $sql .= " ORDER BY s.timesubmitted ASC, u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get next submission for grading navigation
     *
     * @param int $currentsubmissionid Current submission ID
     * @param int $groupid Group ID filter (optional)
     * @return object|null Next submission or null
     */
    public function get_next_submission_for_grading($currentsubmissionid, $groupid = null) {
        $submissions = $this->get_submissions_for_grading(null, $groupid);
        $submissionids = array_keys($submissions);

        $currentindex = array_search($currentsubmissionid, $submissionids);
        if ($currentindex === false) {
            return null;
        }

        $nextindex = $currentindex + 1;
        if (isset($submissionids[$nextindex])) {
            return $submissions[$submissionids[$nextindex]];
        }

        return null;
    }

    /**
     * Get previous submission for grading navigation
     *
     * @param int $currentsubmissionid Current submission ID
     * @param int $groupid Group ID filter (optional)
     * @return object|null Previous submission or null
     */
    public function get_previous_submission_for_grading($currentsubmissionid, $groupid = null) {
        $submissions = $this->get_submissions_for_grading(null, $groupid);
        $submissionids = array_keys($submissions);

        $currentindex = array_search($currentsubmissionid, $submissionids);
        if ($currentindex === false) {
            return null;
        }

        $previousindex = $currentindex - 1;
        if (isset($submissionids[$previousindex])) {
            return $submissions[$submissionids[$previousindex]];
        }

        return null;
    }

    /**
     * Save submission content
     *
     * @param int $submissionid Submission ID
     * @param array $content Field content data
     * @return bool Success
     */
    public function save_submission_content($submissionid, $content) {
        global $DB;

        $success = true;

        foreach ($content as $fieldid => $fieldcontent) {
            // Check if content already exists
            $existing = $DB->get_record('casestudy_content', [
                'submissionid' => $submissionid,
                'fieldid' => $fieldid
            ]);

            $fieldcontent = (array) $fieldcontent;

            if ($existing) {
                // Update existing content
                $existing->content = $fieldcontent['content'] ?? '';
                $existing->contentformat = $fieldcontent['contentformat'] ?? FORMAT_PLAIN;
                $existing->content1 = $fieldcontent['content1'] ?? null;
                $existing->content2 = $fieldcontent['content2'] ?? null;
                $existing->content3 = $fieldcontent['content3'] ?? null;
                $existing->content4 = $fieldcontent['content4'] ?? null;

                $success = $DB->update_record('casestudy_content', $existing);
            } else {
                // Create new content record
                $contentrecord = new \stdClass();
                $contentrecord->submissionid = $submissionid;
                $contentrecord->fieldid = $fieldid;
                $contentrecord->content = $fieldcontent['content'] ?? '';
                $contentrecord->contentformat = $fieldcontent['contentformat'] ?? FORMAT_PLAIN;
                $contentrecord->content1 = $fieldcontent['content1'] ?? null;
                $contentrecord->content2 = $fieldcontent['content2'] ?? null;
                $contentrecord->content3 = $fieldcontent['content3'] ?? null;
                $contentrecord->content4 = $fieldcontent['content4'] ?? null;

                $success = $DB->insert_record('casestudy_content', $contentrecord);
            }
        }

        return (bool) $success;
    }

    /**
     * Get submission content
     *
     * @param int $submissionid Submission ID
     * @return array Content indexed by field ID
     */
    public function get_submission_content($submissionid) {
        global $DB;

        $records = $DB->get_records('casestudy_content', ['submissionid' => $submissionid]);

        $content = [];
        foreach ($records as $record) {
            $content[$record->fieldid] = $record;
        }

        return $content;
    }

    /**
     * Create resubmission based on previous attempt
     *
     * @param int $userid User ID
     * @param int $previoussubmissionid Previous submission ID
     * @return object|false New submission or false on failure
     */
    public function create_resubmission($userid, $previoussubmissionid) {
        global $DB;

        $previoussubmission = $this->get_submission_record($previoussubmissionid);
        if (!$previoussubmission) {
            return false;
        }

        // Create new submission
        $newsubmission = $this->create_submission($userid, $previoussubmission->groupid);

        // Copy content from previous submission if resubmission is based on previous attempt
        if ($this->casestudy->resubmissionbased) {
            $previouscontent = $this->get_submission_content($previoussubmissionid);
            if (!empty($previouscontent)) {
                $this->save_submission_content($newsubmission->id, $previouscontent);
            }
        }

        return $newsubmission;
    }

    /**
     * Check if user can submit more case studies
     *
     * @param int $userid User ID
     * @return bool Can submit
     */
    public function can_user_submit($userid) {
        global $DB;

        // Get effective settings including any user overrides
        $effective = casestudy_get_effective_settings($this->casestudy, $userid);

        // Check maximum submissions (entries) limit
        if ($effective->maxsubmissions > 0) {

            $sql = "SELECT COUNT(*)
                      FROM {casestudy_submissions}
                     WHERE casestudyid = :casestudyid
                       AND userid = :userid
                       AND (parentid IS NULL OR parentid = 0)";
            $entrycount = $DB->count_records_sql($sql, [
                'casestudyid' => $this->casestudyid,
                'userid' => $userid
            ]);

            if ($entrycount >= $effective->maxsubmissions) {
                return false;
            }
        }

        // Check time limits
        $now = time();
        if ($this->casestudy->timeopen > 0 && $now < $this->casestudy->timeopen) {
            return false;
        }

        if ($effective->timeclose > 0 && $now > $effective->timeclose) {
            return false;
        }

        return true;
    }

    /**
     * Create or update submission from form data
     *
     * @param int $userid User ID
     * @param stdClass $formdata Form data
     * @param int $submissionid Submission ID for update (0 for new)
     * @param bool $issubmit True if submitting, false if saving draft
     * @return object Submission record
     */
    public function save_submission_from_form($userid, $formdata, $submissionid = 0, $issubmit = false) {
        global $DB;

        $status = $issubmit ? CASESTUDY_STATUS_SUBMITTED : CASESTUDY_STATUS_DRAFT;

        if ($submissionid) {
            // Update existing submission
            $submission = $this->get_submission_record($submissionid);

            if (!empty($submission->parentid)) {
                $status = $issubmit ? CASESTUDY_STATUS_RESUBMITTED : CASESTUDY_STATUS_DRAFT;
            }

            if (!$submission || $submission->userid != $userid) {
                throw new \moodle_exception('invalidsubmission', 'mod_casestudy');
            }

            $submission->status = $status;
            $submission->timemodified = time();

            if ($issubmit && $submission->timesubmitted == 0) {
                $submission->timesubmitted = time();
            }

            $this->update_submission($submission);

        } else {
            // Create new submission
            $submission = $this->create_submission($userid);
            $submission->status = $status;

            if (!empty($submission->parentid)) {
                $status = $issubmit ? CASESTUDY_STATUS_RESUBMITTED : CASESTUDY_STATUS_DRAFT;
            }

            if ($issubmit) {
                $submission->timesubmitted = time();
            }

            $this->update_submission($submission);
        }

        return $submission;
    }

    /**
     * Save field content from form data
     *
     * @param int $submissionid Submission ID
     * @param array $fielddata Field data from form
     * @return bool Success
     */
    public function save_field_content_from_form(int $submissionid, $fielddata) {
        global $DB;

        $success = true;

        foreach ($fielddata as $fieldid => $value) {
            // Check if content already exists
            $existing = $DB->get_record('casestudy_content', [
                'submissionid' => $submissionid,
                'fieldid' => $fieldid
            ]);

            if ($existing) {
                // Update existing content
                $existing->contentformat = FORMAT_PLAIN;
                $contentdata = $value->to_record();
                $existing = (object) array_merge((array) $existing, (array) $contentdata);

                $success = $success && $DB->update_record('casestudy_content', $existing);
            } else {
                // Create new content
                $content = new \stdClass();
                $content->submissionid = $submissionid;
                $content->fieldid = $fieldid;
                $content->contentformat = FORMAT_PLAIN;
                $contentdata = $value->to_record();
                $content = (object) array_merge((array) $content, (array) $contentdata);

                $success = $success && $DB->insert_record('casestudy_content', $content);
            }
        }

        return $success;
    }

    /**
     * Get submission data for form
     *
     * @param int $submissionid Submission ID
     * @return array Form data
     */
    public function get_submission_form_data($submissionid) {
        global $DB;

        $formdata = [];

        // Get existing submission content
        $contentrecords = $DB->get_records('casestudy_content', ['submissionid' => $submissionid]);
        foreach ($contentrecords as $content) {
            $formdata[$content->fieldid] = $content;
        }

        return $formdata;
    }

    /**
     * Get user's submission for editing or create new oneget_or_create_user_submission
     *
     * @param int $userid User ID
     * @param int $submissionid Submission ID (optional)
     * @return object|null Submission record or null
     */
    public function get_or_create_user_submission($userid, $submissionid = 0) {
        global $DB;

        if ($submissionid) {
            // Get specific submission
            $submission = $DB->get_record('casestudy_submissions', [
                'id' => $submissionid, 'userid' => $userid, 'casestudyid' => $this->casestudyid
            ]);

            return $submission;
        }

        // Check for existing draft
        $submission = $this->get_user_submission($userid, CASESTUDY_STATUS_NEW);

        if (!$submission) {
            // Check if user can submit more
            if ($this->can_user_submit($userid)) {
                $submission = $this->create_submission($userid);
                return $submission;
            }
        }

        return $submission;
    }

    /**
     * Check if submission can be edited
     *
     * @param object $submission Submission record
     * @param int $userid User ID
     * @param bool $createnotification True to create notification if cannot edit
     *
     * @return bool Can edit
     */
    public function can_edit_submission($submission, $userid, $createnotification = false) {
        // Must be the owner
        if ($submission->userid != $userid) {
            return false;
        }

        // Can only edit draft or awaiting resubmission
        $result = in_array($submission->status, [
            CASESTUDY_STATUS_DRAFT,
            CASESTUDY_STATUS_NEW,
            CASESTUDY_STATUS_AWAITING_RESUBMISSION
        ]);

        if (!$result && $createnotification && $submission->status == CASESTUDY_STATUS_SUBMITTED) {
            \core\notification::add(get_string('cannoteditsubmitted', 'mod_casestudy'), \core\output\notification::NOTIFY_ERROR);
            redirect(new \moodle_url('/mod/casestudy/view.php', ['id' => $this->cm->id]));
        }

        return $result;
    }

    /**
     * Process complete form submission
     *
     * @param int $userid User ID
     * @param object $formdata Complete form data
     * @param int $submissionid Submission ID for editing (0 for new)
     * @param bool $issubmit True if submitting, false if saving draft
     * @param object $form Form instance for file processing
     * @return object Submission record
     */
    public function process_form_submission($userid, $formdata, $submissionid = 0, $issubmit = false, $form = null) {
        global $DB;

        // Start transaction
        $transaction = $DB->start_delegated_transaction();

        try {
            // Save submission
            $submission = $this->save_submission_from_form($userid, $formdata, $submissionid, $issubmit);

            // Get processed field data from form
            if ($form && method_exists($form, 'get_submission_data')) {
                $fielddata = $form->get_submission_data($formdata);
            } else {
                // Fallback: extract field data directly and process through field types
                $fielddata = [];
                $fieldmanager = field_manager::instance($this->casestudyid);
                $fields = $fieldmanager->get_fields();

                foreach ($fields as $field) {
                    $fieldname = 'field_' . $field->id;
                    if (isset($formdata->$fieldname)) {
                        $value = $formdata->$fieldname;
                        // Process the value through the field type
                        $fieldclass = $fieldmanager->get_field_type_class($field->type, $field);
                        if ($fieldclass) {
                            $fielddata[$field->id] = $fieldclass->process_input($value, $formdata);
                        } else {
                            // If no field class, create a basic field_data object
                            $fielddata[$field->id] = field_data::create((object)['content' => $value]);
                        }
                    }
                }
            }

            // Save field content
            if (!empty($fielddata)) {
                $this->save_field_content_from_form($submission->id, $fielddata);
            }

            // Save files if form provided
            if ($form && method_exists($form, 'save_area_files')) {
                $form->save_area_files($formdata, $submission->id);
            }

            // Commit transaction
            $transaction->allow_commit();

            return $submission;

        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Check if user can delete submission
     *
     * @param object $submission Submission record
     * @param int $userid User ID
     * @return bool Can delete
     */
    public function can_delete_submission($submission, $userid) {
        // Must be the owner
        if ($submission->userid != $userid && !has_capability('mod/casestudy:managesubmissions', $this->context)) {
            return false;
        }

        // Can only delete draft submissions
        return in_array($submission->status, [CASESTUDY_STATUS_DRAFT, CASESTUDY_STATUS_NEW]) || has_capability('mod/casestudy:managesubmissions', $this->context);
    }

    /**
     * Check if user can reattempt submission
     *
     * @param object $submission Submission record
     * @param int $userid User ID
     * @return bool Can reattempt
     */
    public function can_reattempt_submission($submission, $userid) {
        global $DB;

        // Must be the owner
        if ($submission->userid != $userid) {
            return false;
        }

        // Must be in awaiting resubmission status
        if ($submission->status != CASESTUDY_STATUS_AWAITING_RESUBMISSION) {
            return false;
        }
        $effective = casestudy_get_effective_settings($this->casestudy, $userid);

        // If maxattempts is 0, unlimited attempts are allowed
        if ($effective->maxattempts == 0) {
            return true;
        }

        $rootsubmissionid = $this->get_root_submission_id($submission);
        $count = $this->count_submission_chain($rootsubmissionid);

        // Allow reattempt if count is less than max attempts for this case study
        return $count < $effective->maxattempts;
    }

    /**
     * Get the root submission ID (the original submission in a chain)
     *
     * @param object $submission Current submission
     * @return int Root submission ID
     */
    private function get_root_submission_id($submission) {
        global $DB;

        // If this submission has no parent, it's the root
        if (empty($submission->parentid)) {
            return $submission->id;
        }

        $current = $submission;
        $maxiterations = 100;
        $iterations = 0;

        while (!empty($current->parentid) && $iterations < $maxiterations) {
            $current = $DB->get_record('casestudy_submissions', ['id' => $current->parentid]);
            if (!$current) {
                return $submission->id;
            }
            $iterations++;
        }

        return $current->id;
    }

    /**
     * Count all submissions in a chain (root + all resubmissions)
     *
     * @param int $rootsubmissionid The root submission ID
     * @return int Count of submissions in the chain
     */
    private function count_submission_chain($rootsubmissionid) {
        global $DB;

        $count = 1;

        $children = $DB->get_records('casestudy_submissions', [
            'parentid' => $rootsubmissionid,
            'casestudyid' => $this->casestudyid
        ]);

        // Recursively count children and their descendants
        foreach ($children as $child) {
            $count += $this->count_submission_descendants($child->id);
        }

        return $count;
    }

    /**
     * Recursively count all descendants of a submission
     *
     * @param int $submissionid Parent submission ID
     * @return int Count of descendants
     */
    private function count_submission_descendants($submissionid) {
        global $DB;

        $count = 1;

        $children = $DB->get_records('casestudy_submissions', [
            'parentid' => $submissionid,
            'casestudyid' => $this->casestudyid
        ]);

        // Recursively count their descendants
        foreach ($children as $child) {
            $count += $this->count_submission_descendants($child->id);
        }

        return $count;
    }


    public function delete_submission($submission) {
        global $DB;

        if (empty($submission)) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();

        // Find the root submission to delete the entire chain
        $rootid = $this->get_root_submission_id($submission);

        // Get all submission IDs in this chain (root + all descendants)
        $submissionids = $this->get_all_chain_submission_ids($rootid);

        // Delete content and grades for all submissions in the chain
        foreach ($submissionids as $submissionid) {
            $DB->delete_records('casestudy_content', ['submissionid' => $submissionid]);
            $DB->delete_records('casestudy_grades', ['submissionid' => $submissionid]);
        }

        // Delete all submissions in the chain
        list($insql, $params) = $DB->get_in_or_equal($submissionids);
        $DB->delete_records_select('casestudy_submissions', "id $insql", $params);

        // Update user grade
        $this->casestudyman->remove_usergrade($submission->userid);

        $transaction->allow_commit();

        return true;
    }

    /**
     * Get all submission IDs in a chain (root + all descendants)
     *
     * @param int $rootid Root submission ID
     * @return array Array of submission IDs
     */
    private function get_all_chain_submission_ids($rootid) {
        global $DB;

        $ids = [$rootid];

        // Get all children recursively
        $children = $DB->get_records('casestudy_submissions', ['parentid' => $rootid], '', 'id');
        foreach ($children as $child) {
            $ids = array_merge($ids, $this->get_all_chain_submission_ids($child->id));
        }

        return $ids;
    }

    /**
     * Recreate submission for reattempt
     *
     * @param object $submission Previous submission
     * @return object|false New submission or false on failure
     */
    public function recreate_submission($submission) {
        global $DB;

        if (empty($submission)) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();

        // Create new submission
        $newsubmission = $this->create_submission($submission->userid, $submission->groupid, $submission->id);

        // Copy content from previous submission if resubmission is based on previous attempt
        if ($this->casestudy->resubmissionbased) {
            $previouscontent = $this->get_submission_content($submission->id);
            if (!empty($previouscontent)) {
                $this->save_submission_content($newsubmission->id, $previouscontent);
            }
        }

        $transaction->allow_commit();

        return $newsubmission;
    }


    /**
     * Static method to create instance
     *
     * @param int $casestudyid Case study ID
     * @param object $casestudy Case study instance (optional)
     * @param object $cm Course module (optional)
     * @return submission_manager
     */
    public static function instance($casestudyid, $casestudy = null, $cm = null) {
        return new self($casestudyid, $casestudy, $cm);
    }

    /**
     * Get list of submissions for grading dropdown
     *
     * @return array List of submissions with user full names and first field content
     */
    public function get_casestudy_submission_forgrading() {
        global $DB;

        $firstfield = $DB->get_records('casestudy_fields', ['casestudyid' => $this->casestudyid], 'sortorder ASC', 'id', 0, 1);
        $firstfield = reset($firstfield);

        $sql = 'SELECT s.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, c.content
                FROM {casestudy_submissions} s
                JOIN {user} u ON u.id = s.userid
                JOIN {casestudy_content} c ON c.submissionid = s.id AND c.fieldid = :firstfieldid
                WHERE s.casestudyid = :casestudyid1
                AND s.id NOT IN (SELECT parentid FROM {casestudy_submissions} WHERE casestudyid = :casestudyid AND parentid IS NOT NULL)
                AND s.status <> :draft
                ORDER BY s.timesubmitted ASC';

        $list = $DB->get_records_sql($sql, ['casestudyid' => $this->casestudyid, 'casestudyid1' => $this->casestudyid, 'firstfieldid' => $firstfield->id, 'draft' => CASESTUDY_STATUS_DRAFT]);

        $list = array_map(function($submission) use ($firstfield) {
            $submission = fullname($submission) . ' (' . $submission->content . ')';
            return $submission;
        }, $list);

        return $list;
    }

    /**
     * Get submission history for a specific submission (including all previous attempts)
     *
     * @param int $submissionid Current submission ID
     * @return array Array of submission history records with feedback
     */
    public function get_submission_history($submissionid) {
        global $DB;

        $history = [];
        $currentsubmission = $DB->get_record('casestudy_submissions', ['id' => $submissionid], '*', MUST_EXIST);

        // Build the history chain by following parentid backwards
        $submissions = [];
        $current = $currentsubmission;

        // Add current submission first
        $submissions[] = $current;

        // Follow the chain of parent submissions
        while (!empty($current->parentid)) {
            $parent = $DB->get_record('casestudy_submissions', ['id' => $current->parentid]);
            if ($parent) {
                $submissions[] = $parent;
                $current = $parent;
            } else {
                break;
            }
        }

        // Reverse to get chronological order (oldest first) for numbering
        $submissions = array_reverse($submissions);
        $totalsubmissions = count($submissions);

        // Get feedback for each submission
        foreach ($submissions as $index => $submission) {
            $grade = $DB->get_record('casestudy_grades', ['submissionid' => $submission->id], '*', IGNORE_MISSING);

            $submissionnumber = $index + 1;
            $history[] = (object)[
                'submission' => $submission,
                'grade' => $grade,
                'attempt' => $submission->attempt,
                'submissionnumber' => $submissionnumber,
                'islatest' => ($submission->id == $submissionid),
            ];
        }

        // Reverse back so latest is first in display
        return array_reverse($history);
    }

}