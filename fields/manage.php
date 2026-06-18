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
 * Manage fields for a Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/mod/casestudy/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$action = optional_param('action', '', PARAM_ALPHA);
$fieldid = optional_param('fieldid', 0, PARAM_INT);

// Get course module and related data.
$cm = get_coursemodule_from_id('casestudy', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/casestudy:managefields', $context);

$PAGE->set_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Initialize field manager.
$fieldmanager = \mod_casestudy\local\field_manager::instance($casestudy->id);

// Handle actions.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'delete':
            if ($fieldid) {
                if ($fieldmanager->delete_field($fieldid)) {
                    redirect($PAGE->url, get_string('fielddeleted', 'mod_casestudy'), null, \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    redirect($PAGE->url, get_string('errordeleting', 'mod_casestudy'), null, \core\output\notification::NOTIFY_ERROR);
                }
            }
            break;
        case 'moveup':
            if ($fieldid) {
                $fieldmanager->move_field_up($fieldid);
                redirect($PAGE->url);
            }
            break;
        case 'movedown':
            if ($fieldid) {
                $fieldmanager->move_field_down($fieldid);
                redirect($PAGE->url);
            }
            break;
        case 'clone':
            if ($fieldid) {
                $newfieldid = $fieldmanager->clone_field($fieldid);
                if ($newfieldid) {
                    redirect($PAGE->url, get_string('fieldcloned', 'mod_casestudy'), null, \core\output\notification::NOTIFY_SUCCESS);
                } else {
                    redirect($PAGE->url, get_string('errorcloning', 'mod_casestudy'), null, \core\output\notification::NOTIFY_ERROR);
                }
            }
            break;
    }
}

// Get renderer.
$renderer = $PAGE->get_renderer('mod_casestudy');

echo $OUTPUT->header();

// Page heading.
echo $OUTPUT->heading(get_string('managefields', 'mod_casestudy'));

// Get existing fields and max order.
$fields = $fieldmanager->get_fields();
$maxorder = 0;
if (!empty($fields)) {
    $maxorder = max(array_column($fields, 'sortorder'));
}

// Render the fields management page using templates.
echo $renderer->fields_management_page($cm, $context, $fields, $maxorder);

$PAGE->requires->js_amd_inline("
    require(['core/sortable_list', 'core/ajax', 'core/notification', 'core/toast', 'jquery'], function(SortableList, Ajax, Notification, Toast, $) {
        let origIndex = 0;

        const sortable = new SortableList(
            document.querySelector('.casestudy-fields-table tbody'), {
                isHorizontal: false,
                moveHandlerSelector: '.move',
            }
        );

        $('.casestudy-fields-table tbody tr').on(SortableList.EVENTS.DRAGSTART, function(event, info) {
            console.log(event);
            // Remember position of the element in the beginning of dragging
            var origIndex = info.sourceList.children().index(info.element);

            setTimeout(function() {
                $('.sortable-list-is-dragged').width(info.element.width());
            }, 501);
        });

        $('.casestudy-fields-table tbody tr').on(SortableList.EVENTS.DROP, function(event, info) {

            console.log(info);

            // When a list element was moved send AJAX request to the server
            if (!info.positionChanged) {
                return;
            }

            const newIndex = info.sourceList.children().index(info.element);
            const newPosition = newIndex + 1; // Convert to 1-based position

            // Find field ID from the row data attribute
            const row = info.element;
            const fieldId = row.find('.cell.c0 span').attr('data-field-id');

            if (!fieldId) {
                Notification.addNotification({
                    message: 'Could not find field ID',
                    type: 'error'
                });
                return;
            }

            // Call web service to update field order
            const request = {
                methodname: 'mod_casestudy_update_field_order',
                args: {
                    cmid: " . $cm->id . ",
                    fieldid: parseInt(fieldId),
                    newposition: newPosition
                }
            };

            Ajax.call([request])[0]
                .done(function(response) {
                    if (response.success) {
                        Toast.add(response.message, 'success');
                    }
                })
                .fail(function(ex) {
                    Notification.exception(ex);
                    // Revert the visual change
                    window.location.reload();
                });
        });
    });
");

echo $OUTPUT->footer();
