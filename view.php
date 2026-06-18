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
 * Prints a particular instance of casestudy
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once('../../config.php');
require_once(dirname(__FILE__) . '/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID.
$c  = optional_param('c', 0, PARAM_INT);  // Casestudy instance ID.

if ($id) {
    $cm = get_coursemodule_from_id('casestudy', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($c) {
    $casestudy = $DB->get_record('casestudy', ['id' => $c], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $casestudy->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('casestudy', $casestudy->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingparameter', 'error');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);

casestudy_view($casestudy, $course, $cm, $context);

// Print the page header.
$PAGE->set_url('/mod/casestudy/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

// Get renderer.
$renderer = $PAGE->get_renderer('mod_casestudy');

echo $OUTPUT->header();

// Check capabilities.
$canmanage = has_capability('mod/casestudy:managefields', $context);
$canview = has_capability('mod/casestudy:view', $context);
$cansubmit = !is_siteadmin() && has_capability('mod/casestudy:submit', $context);
$cangrade = has_capability('mod/casestudy:grade', $context);

// Initialize field manager.
$fieldmanager = \mod_casestudy\local\field_manager::instance($casestudy->id);

// Check if there are any fields defined.
$fields = $fieldmanager->get_fields();
$hasfields = !empty($fields);

if (!$hasfields) {
    // No fields configured.
    echo $renderer->render_no_fields_message($canmanage);
} else {
    if ($cansubmit) {
        // Show student interface.
        $submissions = $DB->get_records(
            'casestudy_submissions',
            ['casestudyid' => $casestudy->id, 'userid' => $USER->id],
            'timecreated DESC'
        );

        // Count only original/parent submissions (entries) - not resubmissions
        // maxsubmissions controls how many unique case studies can be created
        $sql = "SELECT COUNT(*)
                  FROM {casestudy_submissions}
                 WHERE casestudyid = :casestudyid
                   AND userid = :userid
                   AND (parentid IS NULL OR parentid = 0)";
        $entrycount = $DB->count_records_sql($sql, [
            'casestudyid' => $casestudy->id,
            'userid' => $USER->id,
        ]);

        // Get effective settings including any user overrides.
        $effective = casestudy_get_effective_settings($casestudy, $USER->id);

        // Check if user can submit more.
        $cansubmitmore = true;
        $availabilitymessage = '';
        $availabilitystatus = '';
        $preventaccess = false;

        // Check time limits with overrides first (highest priority).
        $now = time();
        if ($casestudy->timeopen > 0 && $now < $casestudy->timeopen) {
            $cansubmitmore = false;
            $preventaccess = true;
            $availabilitymessage = get_string('notopened', 'mod_casestudy', userdate($casestudy->timeopen));
            $availabilitystatus = 'notopened';
        } else if ($effective->timeclose > 0 && $now > $effective->timeclose) {
            $cansubmitmore = false;
            // Don't prevent access - allow students to view their previous submissions
            $preventaccess = false;
            $availabilitymessage = get_string('closed', 'mod_casestudy', userdate($effective->timeclose));
            $availabilitystatus = 'closed';
        } else if ($effective->maxsubmissions > 0 && $entrycount >= $effective->maxsubmissions) {
            // Only check max submissions if the activity is currently available.
            $cansubmitmore = false;
            $availabilitymessage = get_string('maxsubmissionsreached', 'mod_casestudy', $effective->maxsubmissions);
            $availabilitystatus = 'maxreached';
        }

        // Completion criteria are now stored directly in the casestudy object.
        // Pass the casestudy object instead of separate completion records.
        echo $renderer->student_interface(
            $casestudy,
            $cm,
            $fields,
            $submissions,
            $cansubmitmore,
            $casestudy,
            $availabilitymessage,
            $availabilitystatus,
            $preventaccess
        );
    } else if ($cangrade) {
        // Show grader interface.
        echo $renderer->grader_interface($cm);
    } else if ($canview) {
        // View only interface.
        echo $renderer->view_only_interface();
    }
}

// Finish the page.
echo $OUTPUT->footer();
