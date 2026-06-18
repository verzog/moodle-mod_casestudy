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
 * View individual case study submission
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$submissionid = required_param('submissionid', PARAM_INT); // Casestudy submission ID.

// Get course module and related data.
$cm = get_coursemodule_from_id('casestudy', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$casestudy = $DB->get_record('casestudy', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

// Get submission.
$submissionobj = new \mod_casestudy\local\submission($submissionid, $cm, $context);
$submission = $submissionobj->get_submission();

if (!$submission || $submission->casestudyid != $casestudy->id) {
    throw new moodle_exception('invalidsubmission', 'mod_casestudy');
}


// Check permissions - users can view their own submissions, graders can view all.
$canview = false;
if ($submission->userid == $USER->id && has_capability('mod/casestudy:submit', $context)) {
    $canview = true;
} else if (has_capability('mod/casestudy:grade', $context)) {
    $canview = true;
} else if (has_capability('mod/casestudy:viewallsubmissions', $context)) {
    $canview = true;
}

if (!$canview) {
    throw new moodle_exception('nopermissions', 'error');
}

$PAGE->set_url('/mod/casestudy/view_casestudy.php', array('id' => $cm->id, 'submissionid' => $submissionid));
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_context($context);

// Check grading permissions.
$cangrade = has_capability('mod/casestudy:grade', $context);

$PAGE->add_body_class(
    $cangrade ? 'casestudy-submission-grading' : 'casestudy-submission-preview');

// Get fields and submission content.
$content = $DB->get_records('casestudy_content', array('submissionid' => $submission->id), '', 'fieldid, content, contentformat');

// Get user info.
$user = $DB->get_record('user', array('id' => $submission->userid));

// Check editing permissions.
$canedit = ($submission->userid == $USER->id &&
    ($submission->status == CASESTUDY_STATUS_DRAFT || $submission->status == CASESTUDY_STATUS_AWAITING_RESUBMISSION));


echo $OUTPUT->header();

// Use renderer to display submission.
$renderer = $PAGE->get_renderer('mod_casestudy');

echo $renderer->view_submission($submissionobj, $user, $content, $cm, $canedit, $cangrade);

echo $OUTPUT->footer();