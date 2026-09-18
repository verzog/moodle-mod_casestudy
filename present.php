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
 * Presenter view of a case study submission.
 *
 * A clean, distraction-free rendering of a submission's answers for showing to a class:
 * no marks, grader feedback, history or action controls. A learner may present their own
 * submission; staff may present any (subject to group access).
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$submissionid = required_param('submissionid', PARAM_INT); // Submission to present.

$cm = get_coursemodule_from_id('casestudy', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

$submissionobj = new \mod_casestudy\local\submission($submissionid, $cm, $context);
$submission = $submissionobj->get_submission();

if (!$submission || $submission->casestudyid != $casestudy->id) {
    throw new moodle_exception('invalidsubmission', 'mod_casestudy');
}

// Access control. The owner may present their own submission; staff may present any, subject to
// the separate-groups restriction.
$isown = ($submission->userid == $USER->id && has_capability('mod/casestudy:submit', $context));
$canviewother = has_capability('mod/casestudy:grade', $context)
    || has_capability('mod/casestudy:viewallsubmissions', $context);

if (!$isown && !$canviewother) {
    throw new moodle_exception('nopermissions', 'error');
}

if (
    !$isown && groups_get_activity_groupmode($cm) == SEPARATEGROUPS
    && !has_capability('moodle/site:accessallgroups', $context)
) {
    $sharesgroup = false;
    foreach (groups_get_all_groups($course->id, $USER->id, $cm->groupingid) as $group) {
        if (groups_is_member($group->id, $submission->userid)) {
            $sharesgroup = true;
            break;
        }
    }
    if (!$sharesgroup) {
        throw new moodle_exception('nopermissions', 'error');
    }
}

$PAGE->set_url('/mod/casestudy/present.php', ['id' => $cm->id, 'submissionid' => $submissionid]);
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
// Embedded layout keeps the presentation clean (no blocks or side navigation).
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('casestudy-presenter-page');

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_casestudy');
echo $renderer->presenter_view($submissionobj, format_string($casestudy->name));

echo $OUTPUT->footer();
