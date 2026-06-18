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
 * Case study submission editor
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once('../../config.php');

require_once(dirname(__FILE__) . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID
$submissionid = optional_param('submissionid', 0, PARAM_INT); // Submission ID for editing
$action = optional_param('action', '', PARAM_ALPHA); // Action to perform.

// Get course module and related data
$cm = get_coursemodule_from_id('casestudy', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Check if user can submit OR manage submissions (for teachers/managers).
if (!has_capability('mod/casestudy:submit', $context) && !has_capability('mod/casestudy:managesubmissions', $context)) {
    throw new required_capability_exception($context, 'mod/casestudy:submit', 'nopermissions', '');
}

$PAGE->set_url('/mod/casestudy/submission.php', ['id' => $cm->id, 'submissionid' => $submissionid]);
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_cm($cm);


// Initialize managers
$fieldmanager = \mod_casestudy\local\field_manager::instance($casestudy->id);
$submissionmanager = \mod_casestudy\local\submission_manager::instance($casestudy->id, $casestudy, $cm);

// Get or create submission
if ($action) {
    switch ($action) {
        case 'delete':
            require_sesskey();
            $submission = $submissionmanager->get_submission_record($submissionid);
            if (!empty($submission) && $submissionmanager->can_delete_submission($submission, $USER->id)) {
                $submissionmanager->delete_submission($submission);
                redirect(
                    new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]),
                    get_string('submissiondeleted', 'mod_casestudy'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                redirect(
                    new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]),
                    get_string('cannotdeletesubmission', 'mod_casestudy'),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
            }
            break;
        case 'reattempt':
            require_sesskey();
            $submission = $submissionmanager->get_submission_record($submissionid);
            if (!empty($submission) && $submissionmanager->can_reattempt_submission($submission, $USER->id)) {
                $submissionmanager->recreate_submission($submission);
                redirect(
                    new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]),
                    get_string('submissionreattempted', 'mod_casestudy'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                redirect(
                    new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]),
                    get_string('cannotreattemptsubmission', 'mod_casestudy'),
                    null,
                    \core\output\notification::NOTIFY_WARNING
                );
            }
            break;
        default:
            throw new moodle_exception('invalidaction', 'mod_casestudy');
    }
}


$submission = $submissionmanager->get_or_create_user_submission($USER->id, $submissionid);

if (!$submission) {
    redirect(
        new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]),
        get_string('submissionlevelreached', 'mod_casestudy'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$editing = !empty($submissionid) && $submission && $submission->id == $submissionid;

// Get fields
$fields = $fieldmanager->get_fields();

if (empty($fields)) {
    redirect(
        new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]),
        get_string('nofields', 'mod_casestudy'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Check if user can edit this submission
if ($editing && !$submissionmanager->can_edit_submission($submission, $USER->id, true)) {
    throw new moodle_exception('cannotedisubmission', 'mod_casestudy');
}

// Get existing submission data for form
$submissiondata = [];
if ($submission && $submission->id) {
    $submissiondata = $submissionmanager->get_submission_form_data($submission->id);
}

// Create form
// Prepare form data
$formdata = [
    'id' => $cm->id,
    'submissionid' => $submission->id,
];

// Add existing submission data

$cmdata = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

$form = new \mod_casestudy\local\forms\submission_edit_form($PAGE->url, [
    'fields' => $fields,
    'fieldmanager' => $fieldmanager,
    'editing' => $editing,
    'cmdata' => $cmdata,
    'isresubmission' => !empty($submission->parentid),
]);

$form->update_formdata_beforeset($submissiondata);

$formdata = array_merge($formdata, $submissiondata);

$form->set_data($formdata);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    // Standard moodleform submission
    $isdraft = $form->is_draft_submission($data) || $form->is_save_and_add_another($data);
    $issubmit = !$isdraft && $form->is_finish_submission($data);
    $currentsubmissionid = $editing ? $submission->id : 0;

    try {
        $submission = $submissionmanager->process_form_submission(
            $USER->id,
            $data,
            $currentsubmissionid,
            $issubmit,
            $form
        );

        $redirecturl = $form->is_save_and_add_another($data)
            ? new moodle_url('/mod/casestudy/submission.php', ['id' => $cm->id])
            : new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]);

        $message = $issubmit
            ? get_string('submissionsubmitted', 'mod_casestudy')
            : get_string('draftsaved', 'mod_casestudy');

        redirect($redirecturl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Exception $e) {
        debugging('Error processing submission: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $form->set_data($data);
        \core\notification::add(get_string('submissionerror', 'mod_casestudy'), \core\output\notification::NOTIFY_ERROR);
    }
}

// Check if form template exists
$casestudyobj = new \mod_casestudy\local\casestudy($casestudy->id, $cm, $context);
$template = new \mod_casestudy\template($casestudyobj, $cm, $context);
$useformtemplate = $template->has_form_template();

// pop up.
$totalunanswered = 0;
$requiresubmit = !empty($casestudy->requiresubmit) ? 1 : 0;
$PAGE->requires->js_call_amd('mod_casestudy/submission_confirmation', 'init', [$totalunanswered, $requiresubmit]);

echo $OUTPUT->header();

// Page heading
if ($editing) {
    echo $OUTPUT->heading(get_string('editcasestudy', 'mod_casestudy'));
} else {
    echo $OUTPUT->heading(get_string('addcasestudy', 'mod_casestudy'));
}

// Check if we should use template-based rendering.
if ($useformtemplate) {
    // Render using custom form template.
    if (!isset($errors)) {
        $errors = [];
    }

    // Get submission data values for template.
    $templatedata = [];

    foreach ($submissiondata as $fieldid => $value) {
        if (is_object($value)) {
            $templatedata[$fieldid] = $value;
        } else {
            $templatedata[$fieldid] = $value;
        }
    }

    $template->set_form($form);

    // The template will handle rendering the form and any errors, so we pass those in as well.
    $form->parse_from_template($template, $fields, $templatedata, $errors, $submission->id ?? null);
    $form->display();
} else {
    // Use standard moodleform rendering
    $form->display();
}

echo $OUTPUT->footer();
