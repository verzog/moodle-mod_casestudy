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
 * List view - Case Study Summaries for Marker, Manager, Admin
 *
 * This view shows the Activity completion status summaries for all students enrolled in a course.
 * This includes the data shown in the List view of Case Studies for Students, however, laid out in
 * columns rather than rows.
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
