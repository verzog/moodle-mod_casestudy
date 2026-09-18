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
 * Per-student case study summary page.
 *
 * Shows one student's case study submissions and their progress towards the activity's
 * completion requirements. Staff with viewsubmissions/viewallsubmissions may view any
 * student (subject to group access); a student may view only their own summary.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$userid = required_param('userid', PARAM_INT); // Student to summarise.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'casestudy');
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

// Access control: staff with viewsubmissions can view any student; a learner may only
// view their own summary.
$canviewall = has_capability('mod/casestudy:viewsubmissions', $context)
    || has_capability('mod/casestudy:viewallsubmissions', $context);
if ($userid != $USER->id && !$canviewall) {
    throw new required_capability_exception($context, 'mod/casestudy:viewsubmissions', 'nopermissions', '');
}

// In separate groups mode a staff member without accessallgroups may only view students
// who share one of their groups.
if ($userid != $USER->id && $canviewall) {
    $groupmode = groups_get_activity_groupmode($cm);
    if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context)) {
        $sharesgroup = false;
        $mygroups = groups_get_all_groups($course->id, $USER->id, $cm->groupingid);
        foreach ($mygroups as $group) {
            if (groups_is_member($group->id, $userid)) {
                $sharesgroup = true;
                break;
            }
        }
        if (!$sharesgroup) {
            throw new moodle_exception('nopermissions', 'error', '', get_string('viewsummary', 'mod_casestudy'));
        }
    }
}

$student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

$PAGE->set_url('/mod/casestudy/view_student.php', ['id' => $cm->id, 'userid' => $userid]);
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_casestudy');
echo $renderer->student_summary($cm, $casestudy, $course, $context, $student);

echo $OUTPUT->footer();
