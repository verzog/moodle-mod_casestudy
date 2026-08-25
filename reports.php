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
 * Staff report view for a Case Study activity.
 *
 * Shows one line per enrolled user with counts of submitted, unmarked and marked
 * cases, plus totals of satisfactory and unsatisfactory cases and the user's groups.
 * Visible only to staff with the mod/casestudy:viewreports capability.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$groupid = optional_param('group', 0, PARAM_INT); // Group ID for filtering.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'casestudy');
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);

// Staff-only report.
require_capability('mod/casestudy:viewreports', $context);

// Set up the page.
$PAGE->set_url('/mod/casestudy/reports.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Trigger the course_module_viewed event.
casestudy_view($casestudy, $course, $cm, $context);

// Output starts here.
echo $OUTPUT->header();

// Get renderer and display the report.
$renderer = $PAGE->get_renderer('mod_casestudy');
echo $renderer->reports_interface($cm, $groupid);

echo $OUTPUT->footer();
