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
 * List view - Case Study Summaries for Marker, Manager, Admin
 *
 * This view shows the Activity completion status summaries for all students enrolled in a course.
 * This includes the data shown in the List view of Case Studies for Students, however, laid out in
 * columns rather than rows.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$groupid = optional_param('group', 0, PARAM_INT); // Group ID for filtering.
$userid = optional_param('userid', 0, PARAM_INT); // User ID for filtering.

list($course, $cm) = get_course_and_cm_from_cmid($id, 'casestudy');
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

// Check capability - only markers, managers, and admins can view this.
require_capability('mod/casestudy:viewallsubmissions', $context);

// Set up the page.
$PAGE->set_url('/mod/casestudy/summaries.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Trigger the course_module_viewed event.
casestudy_view($casestudy, $course, $cm, $context);

// Output starts here.
echo $OUTPUT->header();

// Get renderer.
$renderer = $PAGE->get_renderer('mod_casestudy');

// Display the summaries interface.
echo $renderer->summaries_interface($cm, $groupid, $userid);

echo $OUTPUT->footer();
