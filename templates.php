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
 * Template management interface for case study
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$cmid = optional_param('id', 0, PARAM_INT);
$mode = optional_param('mode', 'singletemplate', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

$url = new moodle_url('/mod/casestudy/templates.php');

if ($cmid) {
    [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'casestudy');
    $casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);
    $url->param('id', $cmid);
} else {
    throw new moodle_exception('missingparameter');
}

$context = context_module::instance($cm->id);

$url->param('mode', $mode);
$PAGE->set_url($url);

require_login($course, false, $cm);
require_capability('mod/casestudy:managetemplates', $context);

$casestudyobj = new \mod_casestudy\local\casestudy($casestudy->id, $cm, $context);

$manager = new \mod_casestudy\template_manager($casestudyobj, $cm, $context);

if ($action == 'resetalltemplates') {
    require_sesskey();
    $manager->reset_all_templates();
    redirect($PAGE->url, get_string('templateresetall', 'mod_casestudy'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_title(format_string($casestudy->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('admin');
$PAGE->force_settings_menu(true);

echo $OUTPUT->header();


if (!$manager->has_fields()) {
    echo $OUTPUT->notification(get_string('nofieldsyet', 'mod_casestudy'), 'warning');
    $addurl = new moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]);
    echo html_writer::link($addurl, get_string('addfields', 'mod_casestudy'), ['class' => 'btn btn-primary']);
    echo $OUTPUT->footer();
    exit;
}

$templateurl = new moodle_url('/mod/casestudy/templates.php', ['id' => $cm->id]);

$tabs = [];
$tabs[] = new tabobject(
    'singletemplate',
    new moodle_url('/mod/casestudy/templates.php', ['id' => $cm->id, 'mode' => 'singletemplate']),
    get_string('singletemplate', 'mod_casestudy')
);
$tabs[] = new tabobject(
    'formtemplate',
    new moodle_url('/mod/casestudy/templates.php', ['id' => $cm->id, 'mode' => 'formtemplate']),
    get_string('formtemplate', 'mod_casestudy')
);
$tabs[] = new tabobject(
    'csstemplate',
    new moodle_url('/mod/casestudy/templates.php', ['id' => $cm->id, 'mode' => 'csstemplate']),
    get_string('csstemplate', 'mod_casestudy')
);

echo $OUTPUT->tabtree($tabs, $mode);
$notificationstr = '';
if (($formdata = data_submitted()) && confirm_sesskey()) {
    if (!empty($formdata->resetall)) {
        // Reset all templates button was clicked.
        $manager->reset_all_templates();
        $notificationstr = get_string('templateresetall', 'mod_casestudy');
    } else if (!empty($formdata->defaultform)) {
        // Reset current template to default.
        $manager->reset_template($mode);
        $notificationstr = get_string('templatereset', 'mod_casestudy');
    } else if (isset($formdata->{$mode})) {
        // Save template changes.
        $manager->update_template($mode, $formdata->{$mode});
        $notificationstr = get_string('templatesaved', 'mod_casestudy');
    }
}

if (!empty($notificationstr)) {
    echo $OUTPUT->notification($notificationstr, 'notifysuccess');
}

$templateeditor = new \mod_casestudy\output\template_editor($manager, $mode);
$renderer = $PAGE->get_renderer('mod_casestudy');
echo $renderer->render($templateeditor);

echo $OUTPUT->footer();
