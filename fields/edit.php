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
 * Edit field for a Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/mod/casestudy/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID
$fieldid = optional_param('fieldid', 0, PARAM_INT);
$fieldtype = optional_param('type', 'text', PARAM_ALPHA);

// Get course module and related data
$cm = get_coursemodule_from_id('casestudy', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/casestudy:managefields', $context);

$PAGE->set_url('/mod/casestudy/fields/edit.php', ['id' => $cm->id, 'fieldid' => $fieldid]);
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Initialize field manager.
$fieldmanager = \mod_casestudy\local\field_manager::instance($casestudy->id);

// Determine if we're editing or creating
$editing = !empty($fieldid);

// Get the field type class
$fieldclass = $fieldmanager->get_field_type_class($fieldtype);
if (!$fieldclass) {
    throw new moodle_exception('invalidfieldtype', 'mod_casestudy');
}

// Create form.
$form = $fieldclass->get_edit_form($fieldmanager, $editing, $fieldid);

// Handle form submission
if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    // Get field data from form
    $fielddata = $form->get_field_data($data);

    if ($editing) {
        $fielddata->id = $fieldid;
        if ($fieldmanager->update_field($fieldid, $fielddata)) {
            redirect(
                new moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]),
                get_string('fieldupdated', 'mod_casestudy'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            redirect(
                new moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]),
                get_string('errorupdating', 'mod_casestudy'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    } else {
        if ($fieldmanager->create_field($fielddata->type, $fielddata)) {
            redirect(
                new moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]),
                get_string('fieldcreated', 'mod_casestudy'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            redirect(
                new moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]),
                get_string('errorcreating', 'mod_casestudy'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }
} else {
    if ($editing) {
        // Load existing field data into form
        $field = $fieldmanager->get_field($fieldid);
        if (!$field) {
            throw new \moodle_exception('invalidfield', 'mod_casestudy');
        }
        $fieldtype = $field->type;
        $formdata = clone $field;
    } else {
        $formdata = new stdClass();
    }

    // Set form data.
    $formdata->id = $cm->id;
    $formdata->fieldid = $fieldid;
    $formdata->type = $fieldtype;
    $form->set_field_defaults($formdata);
}

echo $OUTPUT->header();

// Page heading
if ($editing) {
    echo $OUTPUT->heading(get_string('editfield', 'mod_casestudy') . ': ' . format_string($field->name));
} else {
    echo $OUTPUT->heading(get_string('addfield', 'mod_casestudy') . ': ' . get_string('fieldtype_' . $fieldtype, 'mod_casestudy'));
}

// Display form
$form->display();

// Back link
echo '<div class="mt-3">';
echo '<a href="fields.php?id=' . $cm->id . '" class="btn btn-secondary">' . get_string('back') . '</a>';
echo '</div>';

echo $OUTPUT->footer();
