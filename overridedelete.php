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
 * This page handles deleting case study overrides
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/casestudy/lib.php');

$overrideid = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', false, PARAM_BOOL);

$override = $DB->get_record('casestudy_overrides', ['id' => $overrideid], '*', MUST_EXIST);
list($course, $cm) = get_course_and_cm_from_instance($override->casestudyid, 'casestudy');
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_sesskey();

// Check the user has the required capability to delete overrides.
require_capability('mod/casestudy:manageoverrides', $context);

$url = new moodle_url('/mod/casestudy/overridedelete.php', ['id' => $override->id]);
$confirmurl = new moodle_url($url, ['id' => $override->id, 'sesskey' => sesskey(), 'confirm' => 1]);
$cancelurl = new moodle_url('/mod/casestudy/overrides.php', ['cmid' => $cm->id]);

if ($confirm) {
    // Delete the override.
    $DB->delete_records('casestudy_overrides', ['id' => $override->id]);

    // Trigger event.
    $event = \mod_casestudy\event\override_deleted::create([
        'objectid' => $override->id,
        'context' => $context,
        'other' => [
            'casestudyid' => $casestudy->id,
            'userid' => $override->userid
        ],
        'relateduserid' => $override->userid
    ]);
    $event->trigger();

    redirect($cancelurl, get_string('overridedeleted', 'casestudy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('deleteoverride', 'casestudy'));
$PAGE->set_heading($course->fullname);

// Activate the secondary nav tab.
$PAGE->set_secondary_active_tab("mod_casestudy_useroverrides");

$PAGE->activityheader->set_attrs([
    "title" => format_string($casestudy->name, true, ['context' => $context]),
    "description" => "",
    "hidecompletion" => true
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deleteoverride', 'casestudy'));

// Get user details.
$user = $DB->get_record('user', ['id' => $override->userid], '*', MUST_EXIST);

$message = get_string('confirmdeleteoverride', 'casestudy');
$message .= '<br><br><strong>' . fullname($user) . '</strong>';

echo $OUTPUT->confirm($message, $confirmurl, $cancelurl);

echo $OUTPUT->footer();
