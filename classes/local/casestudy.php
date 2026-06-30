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
 * Case study main domain class.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local;

use cm_info;
use mod_casestudy\local\forms\grading_form;
use stdClass;

defined('MOODLE_INTERNAL') || die();

// Include grade library for grade_item and grade_grade classes.
global $CFG;
require_once($CFG->libdir . '/gradelib.php');

/**
 * Main domain wrapper around a case study activity instance.
 *
 * Caches the cm / context / course records and acts as the entry point for grading,
 * participant lookups and submission status changes that need access to the
 * surrounding course-module context.
 */
class casestudy {
    /** @var int Activity instance id from the {casestudy} table. */
    public int $casestudyid;

    /** @var \context_module Module-level context for this activity. */
    protected $context;

    /** @var \cm_info|\stdClass Course module record. */
    protected $cm;

    /** @var \stdClass Course record. */
    protected $course;

    /** @var \stdClass Raw {casestudy} row. */
    protected $casestudy;

    /** @var \grade_item|null Cached grade item. Loaded lazily on first grading interaction. */
    protected ?\grade_item $gradeitem = null;

    /** @var self[] In-process instance cache keyed by casestudyid. */
    public static $instances = [];

    /**
     * Build the wrapper, resolving missing course module / context from the activity id.
     *
     * @param int $casestudyid Activity instance id.
     * @param \cm_info|\stdClass|null $cm Optional pre-loaded course module record.
     * @param \context_module|null $context Optional pre-loaded module context.
     * @throws \coding_exception When neither $cm nor $context is provided.
     */
    public function __construct($casestudyid, $cm = null, $context = null) {

        $this->casestudyid = $casestudyid;

        $this->casestudy = helper::get_casestudy($casestudyid);

        if (empty($cm)) {
            [$course, $cm] = get_course_and_cm_from_instance($casestudyid, 'casestudy');
            $this->cm = $cm;
            $this->course = $course;
        } else {
            $this->course = get_course($cm->course);
            $this->cm = $cm;
        }

        if (empty($context) && !empty($cm)) {
            $this->context = \context_module::instance($cm->id);
        } else if (!empty($context)) {
            $this->context = $context;
        } else {
            throw new \coding_exception('Either cm or context must be provided to casestudy class constructor');
        }
    }

    /**
     * Get the module-level context for this activity.
     *
     * @return \context_module
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Get the cached course module record.
     *
     * @return \cm_info|\stdClass
     */
    public function get_cm() {
        return $this->cm;
    }

    /**
     * Get the cached course record.
     *
     * @return \stdClass
     */
    public function get_course() {
        return $this->course;
    }

    /**
     * Get the raw {casestudy} row backing this wrapper.
     *
     * @return \stdClass
     */
    public function get_casestudy_record() {
        return $this->casestudy;
    }

    /**
     * Return a cached instance for the given activity id, building one on first call.
     *
     * @param int $casestudyid Activity instance id.
     * @param \cm_info|null $cm Optional pre-loaded course module record.
     * @param \context_module|null $context Optional pre-loaded module context.
     * @return self
     */
    public static function instance(int $casestudyid, ?cm_info $cm = null, $context = null) {

        if (empty(self::$instances[$casestudyid])) {
            self::$instances[$casestudyid] = new self($casestudyid, $cm, $context);
        }

        return self::$instances[$casestudyid];
    }

    /**
     * Get or create a grade record for a user and submission.
     *
     * @param int $userid The user ID
     * @param bool $create Whether to create the record if it doesn't exist
     * @param int $submissionid The submission ID
     * @return stdClass|null The grade record or null if not found and not created
     */
    public function get_user_grade(int $userid, bool $create = false, int $submissionid = 1): ?\stdClass {
        global $DB;

        $params = [
            // 'casestudyid' => $this->casestudyid,
            'userid' => $userid,
            'submissionid' => $submissionid,
        ];

        $grade = $DB->get_record('casestudy_grades', $params);

        if (!$grade && $create) {
            $grade = new \stdClass();
            // $grade->casestudyid = $this->casestudyid;
            $grade->userid = $userid;
            $grade->submissionid = $submissionid;
            $grade->timecreated = time();
            $grade->timemodified = time();
            $grade->grade = null;
            $grade->feedback = null;
            $grade->feedbackformat = null;
            $grade->grader = null;
            $grade->timegraded = null;

            $grade->id = $DB->insert_record('casestudy_grades', $grade);
        }

        return $grade;
    }

    /**
     * Inject the activity's grading elements (point grade / advanced grading instance) into a form.
     *
     * @param \MoodleQuickForm $mform Form being built (modified in place).
     * @param int $submissionid Submission being graded.
     * @param \stdClass|null $data Optional default form data.
     * @param bool $gradingdisabled True when the form should be read-only (final grade already issued).
     */
    public function add_grading_elements(
        \MoodleQuickForm &$mform,
        int $submissionid,
        ?\stdClass $data = null,
        bool $gradingdisabled = false
    ) {

        $userid = helper::get_submission($submissionid)->userid;

        // Add the grading elements to the form
        if (!$userid) {
            return ['mode' => 'none', 'scaleitems' => []];
        }

        $grade = $this->get_user_grade($userid, true, $submissionid);
        $gradinginstance = $this->get_grading_instance($userid, $grade, false);

        $mode = 'none';
        $scaleitems = [];
        if ($gradinginstance) {
            $mform->addElement(
                'grading',
                'advancedgrading',
                get_string('grade', 'mod_casestudy'),
                ['gradinginstance' => $gradinginstance]
            );
            $mform->setType('advancedgrading', PARAM_RAW);
            $mode = 'advanced';
        } else {
            // Fallback to traditional grading if advanced grading is not available.
            // Use simple direct grading.
            if ($this->casestudy->grade > 0) {
                $name = get_string('gradeoutof', 'assign', $this->casestudy->grade);
                if (!$gradingdisabled) {
                    $gradingelement = $mform->addElement('text', 'grade', $name);
                    $mform->addHelpButton('grade', 'gradeoutofhelp', 'assign');
                    $mform->setType('grade', PARAM_RAW);
                } else {
                    $strgradelocked = get_string('gradelocked', 'assign');
                    $mform->addElement('static', 'gradedisabled', $name, $strgradelocked);
                    $mform->addHelpButton('gradedisabled', 'gradeoutofhelp', 'assign');
                }
                $mode = 'point';
            } else if ($this->casestudy->grade < 0) {
                // Scale-based grading: the scale items become buttons in the form,
                // so we don't add a select element here. Just capture the items.
                $scaleitems = make_grades_menu($this->casestudy->grade);
                if (!empty($scaleitems)) {
                    // Hidden input holds the chosen scale value when a scale button is clicked.
                    $mform->addElement('hidden', 'grade');
                    $mform->setType('grade', PARAM_INT);
                    $mode = 'scale';
                }
            }
        }

        return ['mode' => $mode, 'scaleitems' => $scaleitems];
    }

    /**
     * Get an instance of a grading form if advanced grading is enabled.
     * This is specific to the assignment, marker and student.
     *
     * @param int $userid - The student userid
     * @param stdClass|false $grade - The grade record
     * @param bool $gradingdisabled
     * @return mixed gradingform_instance|null $gradinginstance
     */
    protected function get_grading_instance($userid, $grade, $gradingdisabled) {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/grade/grading/lib.php');

        $grademenu = make_grades_menu($this->casestudy->grade);
        $allowgradedecimals = $this->casestudy->grade > 0;

        $advancedgradingwarning = false;
        $gradingmanager = get_grading_manager($this->context, 'mod_casestudy', 'submissions');
        $gradinginstance = null;
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);
            if ($controller->is_form_available()) {
                $itemid = null;
                if ($grade) {
                    $itemid = $grade->id;
                }
                if ($gradingdisabled && $itemid) {
                    $gradinginstance = $controller->get_current_instance($USER->id, $itemid);
                } else if (!$gradingdisabled) {
                    $instanceid = optional_param('advancedgradinginstanceid', 0, PARAM_INT);
                    $gradinginstance = $controller->get_or_create_instance(
                        $instanceid,
                        $USER->id,
                        $itemid
                    );
                }
            } else {
                $advancedgradingwarning = $controller->form_unavailable_notification();
            }
        }
        if ($gradinginstance) {
            $gradinginstance->get_controller()->set_grade_range($grademenu, $allowgradedecimals);
        }
        return $gradinginstance;
    }


    /**
     * Process grade submission from grading form
     *
     * @param \stdClass $data Form data
     * @param grading_form|null $form The grading form instance
     * @return \stdClass Result object with success, message, and redirect
     * @throws \moodle_exception If submission not found
     */
    public function process_grade_submission(\stdClass $data, ?grading_form $form = null) {

        $submissionid = $data->submissionid;
        $casestudyid = $data->casestudyid;

        // Get the submission.
        $manager = submission_manager::instance($casestudyid, $this->casestudy, $this->cm);
        $submission = $manager->get_submission_record($submissionid);

        if (!$submission) {
            throw new \moodle_exception('submissionnotfound', 'mod_casestudy');
        }

        // Check if submission is already graded (satisfactory or unsatisfactory).
        // Only users with regrade capability can modify finished assessments.
        $finishedstatuses = [CASESTUDY_STATUS_SATISFACTORY, CASESTUDY_STATUS_UNSATISFACTORY];
        if (in_array($submission->status, $finishedstatuses) && !has_capability('mod/casestudy:regrade', $this->context)) {
            $result = new \stdClass();
            $result->success = false;
            $result->message = get_string('cannotregrade', 'mod_casestudy');
            $result->redirect = null;
            return $result;
        }

        // Process grading actions
        $feedback = $this->process_feedback_data($data);
        $grade = $this->process_advanced_grading($data, $casestudyid, $submissionid);

        $result = new \stdClass();
        $result->success = true;
        $result->message = '';
        $result->redirect = null;

        // Just save feedback without changing grade/status.
        $this->save_feedback($submission, $feedback, $grade, $data->saverequestresubmission, $form);

        // Scale-button submissions arrive as submitaction='markscale' with the
        // chosen scale item id in $data->grade. The highest scale item maps to
        // satisfactory; everything else maps to unsatisfactory.
        $scalestatus = null;
        if (($data->submitaction ?? '') === 'markscale' && $this->casestudy->grade < 0) {
            $scaleitems = make_grades_menu($this->casestudy->grade);
            if (!empty($scaleitems)) {
                $maxkey = max(array_keys($scaleitems));
                $chosen = isset($data->grade) ? (int)$data->grade : 0;
                $scalestatus = ($chosen === $maxkey)
                    ? CASESTUDY_STATUS_SATISFACTORY
                    : CASESTUDY_STATUS_UNSATISFACTORY;
            }
        }

        $status = match ($data->submitaction) {
            'savefeedback' => !empty($submission->parentid) ? CASESTUDY_STATUS_RESUBMITTED_INREVIEW : CASESTUDY_STATUS_IN_REVIEW,
            'saverequestresubmission' => CASESTUDY_STATUS_AWAITING_RESUBMISSION,
            'markscale' => $scalestatus ?? CASESTUDY_STATUS_IN_REVIEW,
            default => CASESTUDY_STATUS_IN_REVIEW,
        };

        // Get notifystudent value from form data (default true for backward compatibility)
        $notifystudent = isset($data->notifystudent) ? (bool)$data->notifystudent : true;

        $this->update_submission_status($submission, $status, $notifystudent);

        $result->message = get_string('feedbacksaved', 'mod_casestudy');

        return $result;
    }

    /**
     * Process advanced grading data
     *
     * @param object $data Form data
     * @param int $casestudyid Case study ID
     * @param int $submissionid Submission ID
     * @return mixed Grade value or null
     */
    protected function process_advanced_grading($data, $casestudyid, $submissionid) {
        global $USER;

        // Check for advanced grading data
        if (!isset($data->advancedgrading) || empty($data->advancedgrading)) {
            // Fall back to traditional grading
            return isset($data->grade) && $data->grade !== '' ? (int)$data->grade : null;
        }

        $cm = $this->cm ?: get_coursemodule_from_instance('casestudy', $casestudyid);
        if (!$cm) {
            return null;
        }

        $grade = $this->get_user_grade($data->userid, true, $submissionid);

        $context = \context_module::instance($cm->id);
        $gradingmanager = get_grading_manager($context, 'mod_casestudy', 'submissions');

        if ($controller = $gradingmanager->get_active_controller()) {
            // Get or create grading instance
            $gradinginstance = $controller->get_or_create_instance($submissionid, $USER->id, $grade->id ?? 0);

            // Process the advanced grading form
            if ($gradinginstance) {
                // Update the grading instance with form data
                $gradinginstance->submit_and_get_grade($data->advancedgrading, $grade->id ?? 0);

                // Get the calculated grade
                $grade = $gradinginstance->get_grade();

                // Convert to satisfactory/unsatisfactory scale (0/1)
                // This depends on your grading scale setup
                return $grade;
            }
        }

        return null;
    }

    /**
     * Process feedback data from editor
     *
     * @param object $data Form data
     * @return array Processed feedback data
     */
    protected function process_feedback_data($data) {

        return [
            'text' => $data->feedback_editor['text'] ?? '',
            'format' => $data->feedback_editor['format'] ?? FORMAT_HTML,
            'files' => $data->feedback_editor['itemid'] ?? 0,
        ];
    }

    /**
     * Save feedback for submission
     *
     * @param object $submission Submission record
     * @param array $feedback Feedback data
     * @param int|null $grade Grade (0=unsatisfactory, 1=satisfactory, null=no grade)
     * @param bool $requestresubmission Request resubmission
     */
    protected function save_feedback(stdClass $submission, $feedback, $grade = null, $requestresubmission = false, $form = null) {
        global $DB, $USER;

        // Check if feedback already exists
        $existingfeedback = $DB->get_record('casestudy_grades', [
            'submissionid' => $submission->id,
        ], '*', IGNORE_MULTIPLE);

        $graderecord = null;
        if (!empty($existingfeedback)) {
            // Update existing feedback
            $existingfeedback->feedback = $feedback['text'];
            $existingfeedback->feedbackformat = $feedback['format'];
            if ($grade !== null) {
                $existingfeedback->grade = $grade;
            }

            if ($existingfeedback->graderid == 0) {
                $existingfeedback->graderid = $USER->id;
            }
            $existingfeedback->requestresubmission = $requestresubmission ? 1 : 0;
            $existingfeedback->timemodified = time();

            $DB->update_record('casestudy_grades', $existingfeedback);

            $feedbackid = $existingfeedback->id;
            $graderecord = $existingfeedback;
        } else {
            // Create new feedback record
            $feedbackrecord = new \stdClass();
            $feedbackrecord->submissionid = $submission->id;
            $feedbackrecord->graderid = $USER->id;
            $feedbackrecord->feedback = $feedback['text'];
            $feedbackrecord->feedbackformat = $feedback['format'];
            $feedbackrecord->grade = $grade;
            $feedbackrecord->requestresubmission = $requestresubmission ? 1 : 0;
            $feedbackrecord->timecreated = time();
            $feedbackrecord->timemodified = time();

            $feedbackid = $DB->insert_record('casestudy_grades', $feedbackrecord);
            $feedbackrecord->id = $feedbackid;
            $graderecord = $feedbackrecord;
        }

        // Handle file uploads if any.
        if ($feedback['files']) {
            $context = $this->context;
            // Pass the feedback text through so the editor's draft URLs (draftfile.php/.../user/
            // draft/<id>/) are tokenised to @@PLUGINFILE@@ and the rewritten text is persisted.
            // Without this the stored HTML keeps per-session draft URLs that break as soon as the
            // draft is cleaned up — and are meaningless after a course backup/restore — even though
            // the image bytes were saved correctly to the 'feedback' area.
            $rewritten = file_save_draft_area_files(
                $feedback['files'],
                $context->id,
                'mod_casestudy',
                'feedback',
                $feedbackid,
                $form->get_editor_options(),
                $feedback['text']
            );
            if ($rewritten !== null && $rewritten !== $feedback['text']) {
                $DB->set_field('casestudy_grades', 'feedback', $rewritten, ['id' => $feedbackid]);
                $graderecord->feedback = $rewritten;
            }
        }

        // Trigger submission graded event
        if ($graderecord && $this->cm) {
            $event = \mod_casestudy\event\submission_graded::create_from_grade(
                $this->casestudy,
                $this->cm,
                $submission,
                $graderecord
            );
            $event->trigger();
        }
    }

    /**
     * Update submission status
     *
     * @param object $submission Submission record
     * @param string $newstatus New status
     * @param bool $notifystudent Whether to notify student (default true)
     */
    protected function update_submission_status($submission, $newstatus, $notifystudent = true) {
        global $DB;

        $oldstatus = $submission->status;
        $submission->status = $newstatus;
        $submission->timemodified = time();

        $DB->update_record('casestudy_submissions', $submission);

        // Trigger grade event if applicable
        if (in_array($newstatus, [CASESTUDY_STATUS_SATISFACTORY, CASESTUDY_STATUS_UNSATISFACTORY])) {
            $this->trigger_grade_event($submission);
        }

        // Send notification to learner if status changed to a notifiable state.
        // Only send when graded or status changed, NOT for comments.
        if (\mod_casestudy\notification_helper::should_notify_status_change($oldstatus, $newstatus)) {
            $grade = $DB->get_record('casestudy_grades', ['submissionid' => $submission->id], '*', IGNORE_MULTIPLE);
            if ($grade) {
                \mod_casestudy\notification_helper::send_grade_notification(
                    $this->casestudy,
                    $submission,
                    $grade,
                    $this->cm,
                    $this->course,
                    $oldstatus,
                    $notifystudent
                );
            }
        }

        // Update completion state when submission status changes.
        // Only update if automatic completion is enabled.
        if ($this->cm && $this->course) {
            $completion = new \completion_info($this->course);
            if ($completion->is_enabled($this->cm)) {
                // Only update completion for automatic tracking with custom rules
                if ($this->cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
                    // Trigger recalculation based on custom completion rules
                    $completion->update_state($this->cm, COMPLETION_COMPLETE, $submission->userid);
                }
            }
        }
    }

    /**
     * Trigger grade event
     *
     * @param object $submission Submission record
     */
    protected function trigger_grade_event($submission) {
        // Get casestudy record
        global $DB;

        $casestudy = $DB->get_record('casestudy', ['id' => $submission->casestudyid]);
        if ($casestudy) {
            casestudy_update_grades($casestudy, $submission->userid);
        }
    }


    /**
     * Get the primary grade item for this assign instance.
     *
     * @return grade_item The grade_item record
     */
    public function get_grade_item() {

        if ($this->gradeitem) {
            return $this->gradeitem;
        }

        $instance = $this->casestudy;

        $params = ['itemtype' => 'mod',
                        'itemmodule' => 'casestudy',
                        'iteminstance' => $instance->id,
                        'courseid' => $instance->course,
                        'itemnumber' => 0];

        $this->gradeitem = \grade_item::fetch($params);

        if (!$this->gradeitem) {
            throw new coding_exception('Improper use of the assignment class. ' .
                                       'Cannot load the grade item.');
        }
        return $this->gradeitem;
    }

    /**
     * Remove the grade for a specific user.
     *
     * @param int $userid The ID of the user whose grade should be removed.
     */
    public function remove_usergrade($userid) {
        global $DB;

        $gradeitem = $this->get_grade_item();
        if (!$gradeitem) {
            return;
        }

        $grade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
        if (!$grade) {
            return;
        }

        $grade->delete();
    }

    /**
     * Get the maximum number of submissions allowed per user.
     *
     * @param int $casestudyid The ID of the casestudy instance.
     * @return int The maximum number of submissions allowed (0 for unlimited).
     */
    public static function get_max_submissions(int $casestudyid): int {
        global $DB;

        $casestudy = $DB->get_record('casestudy', ['id' => $casestudyid], '*', MUST_EXIST);
        return (int)$casestudy->maxsubmissions;
    }


    /**
     * Resolve the participants visible to the current user given a group + status filter.
     *
     * Honours the activity's group mode and the calling user's access-all-groups capability.
     *
     * @param int $currentgroup Group id, or 0 for "all groups".
     * @param array|bool $tablesort Sort spec accepted by {@see list_participants()}.
     * @return array Participant records.
     */
    public function list_participants_with_filter_status_and_group($currentgroup, $tablesort) {
        $participants = $this->list_participants($currentgroup, false, $tablesort);
        return $participants;
    }

    /**
     * Load a list of users enrolled in the current course with the specified permission and group.
     * 0 for no group.
     * Apply any current sort filters from the grading table.
     *
     * @param int $currentgroup
     * @param bool $idsonly
     * @param bool $tablesort
     * @return array List of user records
     */
    public function list_participants($currentgroup, $idsonly, $tablesort = false) {
        global $DB, $USER;

        // Get the last known sort order for the grading table.

        if (empty($currentgroup)) {
            $currentgroup = 0;
        }

        $key = $this->context->id . '-' . $currentgroup;

        [$esql, $params] = get_enrolled_sql($this->context, 'mod/casestudy:submit', $currentgroup, true);

        $fields = 'u.*';
        $orderby = 'u.lastname, u.firstname, u.id';

        $additionaljoins = '';
        $additionalfilters = '';

        // Exclude suspended users from the list of participants.
        $additionalfilters .= " AND u.suspended = 0 AND u.auth <> 'nologin'";

        $sql = "SELECT $fields
                    FROM {user} u
                    JOIN ($esql) je ON je.id = u.id
                        $additionaljoins
                    WHERE u.deleted = 0
                        $additionalfilters
                ORDER BY $orderby";

        $users = $DB->get_records_sql($sql, $params);

        foreach ($users as $userid => $user) {
            $users[$userid]->fullname = fullname($user);
        }

        return $users;
    }


    /**
     * Build the status-filter list for the submissions table dropdown.
     *
     * @return array Map of status constant => display label.
     */
    public function get_status_filters() {
        $statuslist = helper::get_status_list();

        $current = get_user_preferences('casestudy_status_filter', '');

        $statusfilters = [
            [
                'name' => get_string('all'),
                'key' => 'none',
                'active' => $current === '' ? true : false,
            ],
        ];
        foreach ($statuslist as $key => $status) {
            $statusfilters[] = [
                'name' => $status,
                'key' => $key,
                'active' => $current == $key ? true : false,
            ];
        }

        return $statusfilters;
    }
}
