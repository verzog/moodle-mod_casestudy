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
 * This page handles editing and creation of case study overrides
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_casestudy\form\edit_override_form;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/casestudy/lib.php');

$cmid = optional_param('cmid', 0, PARAM_INT);
$overrideid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);

$override = null;
if ($overrideid) {
    $override = $DB->get_record('casestudy_overrides', ['id' => $overrideid], '*', MUST_EXIST);
    list($course, $cm) = get_course_and_cm_from_instance($override->casestudyid, 'casestudy');
    $casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'casestudy');
    $casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);
}

$context = context_module::instance($cm->id);

$url = new moodle_url('/mod/casestudy/overrideedit.php');
if ($action) {
    $url->param('action', $action);
}
if ($overrideid) {
    $url->param('id', $overrideid);
} else {
    $url->param('cmid', $cmid);
}

$PAGE->set_url($url);

// Activate the secondary nav tab.
$PAGE->set_secondary_active_tab("mod_casestudy_useroverrides");

require_login($course, false, $cm);

// Add or edit an override.
require_capability('mod/casestudy:manageoverrides', $context);

if ($overrideid) {
    // Editing an override.
    $data = clone $override;

    // Set checkbox states based on which values are present.
    $data->enabletimeclose = isset($override->timeclose);
    $data->enablemaxattempts = isset($override->maxattempts);
} else {
    // Creating a new override.
    $data = new stdClass();
    $data->enabletimeclose = false;
    $data->enablemaxattempts = false;
}

// If we are duplicating an override, then clear the user/group and override id.
if ($action === 'duplicate') {
    $override->id = null;
    $override->userid = null;
}

$overridelisturl = new moodle_url('/mod/casestudy/overrides.php', ['cmid' => $cm->id]);

// Setup the form.
$mform = new edit_override_form($url, $cm, $casestudy, $context, $override);
$mform->set_data($data);

if ($mform->is_cancelled()) {
    redirect($overridelisturl);

} else if ($fromform = $mform->get_data()) {
    // Process the data.
    $overridedata = new stdClass();

    if (empty($action) && !empty($overrideid)) {
        $overridedata->id = $overrideid;
    }

    $overridedata->casestudyid = $casestudy->id;
    $overridedata->userid = $fromform->userid;

    // Only set values if the checkboxes are enabled.
    if (!empty($fromform->enabletimeclose)) {
        $overridedata->timeclose = $fromform->timeclose;
    } else {
        $overridedata->timeclose = null;
    }

    if (!empty($fromform->enablemaxattempts)) {
        $overridedata->maxattempts = $fromform->maxattempts;
    } else {
        $overridedata->maxattempts = null;
    }

    $overridedata->timemodified = time();

    if (!empty($overridedata->id)) {
        // Update existing override.
        $DB->update_record('casestudy_overrides', $overridedata);
    } else {
        // Create new override.
        $overridedata->timecreated = time();
        $id = $DB->insert_record('casestudy_overrides', $overridedata);
        $overridedata->id = $id;
    }

    // Trigger appropriate events.
    $eventparams = [
        'context' => $context,
        'other' => [
            'casestudyid' => $casestudy->id,
            'userid' => $overridedata->userid
        ],
        'relateduserid' => $overridedata->userid
    ];

    if (empty($overrideid)) {
        $event = \mod_casestudy\event\override_created::create($eventparams);
    } else {
        $eventparams['objectid'] = $overrideid;
        $event = \mod_casestudy\event\override_updated::create($eventparams);
    }
    $event->trigger();

    if (!empty($fromform->submitbutton)) {
        redirect($overridelisturl, get_string('overridesaved', 'casestudy'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // The user pressed the 'again' button, so redirect back to this page.
    $url->remove_params('cmid');
    $url->param('action', 'duplicate');
    $url->param('id', $overridedata->id);
    redirect($url);
}

// Print the form.
$pagetitle = get_string('editoverride', 'casestudy');
$PAGE->navbar->add($pagetitle);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_attrs([
    "title" => format_string($casestudy->name, true, ['context' => $context]),
    "description" => "",
    "hidecompletion" => true
]);
echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();
