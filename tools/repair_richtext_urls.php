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
 * Admin web tool to diagnose and repair rich-text image URLs in submissions.
 *
 * A browser-accessible alternative to the cli/ scripts for sites without shell
 * access. Site administrators only.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use mod_casestudy\local\richtext_repair;

$cmid = optional_param('cmid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

require_login();
$systemcontext = context_system::instance();
require_capability('moodle/site:config', $systemcontext);

$baseurl = new moodle_url('/mod/casestudy/tools/repair_richtext_urls.php');

$PAGE->set_url($baseurl);
$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('repairrichtexttitle', 'mod_casestudy'));
$PAGE->set_heading(get_string('repairrichtexttitle', 'mod_casestudy'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('repairrichtexttitle', 'mod_casestudy'));
echo $OUTPUT->box(get_string('repairrichtextintro', 'mod_casestudy'));

// Scope form (course module id is optional; blank = whole site).
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out(false), 'class' => 'form-inline mb-3']);
echo html_writer::label(get_string('repairrichtextcmid', 'mod_casestudy'), 'cmid', true, ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'name' => 'cmid', 'id' => 'cmid', 'value' => $cmid ?: '',
    'class' => 'form-control mr-2', 'min' => 0, 'placeholder' => get_string('repairrichtextcmidall', 'mod_casestudy'),
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit', 'name' => 'action', 'value' => get_string('repairrichtextdiagnose', 'mod_casestudy'),
    'class' => 'btn btn-secondary mr-2',
]);
echo html_writer::empty_tag('input', [
    'type' => 'submit', 'name' => 'action', 'value' => get_string('repairrichtextpreview', 'mod_casestudy'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_tag('form');

// Diagnose: list each referenced image and whether the file exists. Needs a specific activity.
if ($action === get_string('repairrichtextdiagnose', 'mod_casestudy')) {
    if (!$cmid) {
        echo $OUTPUT->notification(get_string('repairrichtextneedcmid', 'mod_casestudy'), 'notifyproblem');
    } else {
        $report = richtext_repair::diagnose($cmid);
        $present = 0;
        $missing = 0;
        $table = new html_table();
        $table->head = [
            get_string('repairrichtextsubmission', 'mod_casestudy'),
            get_string('repairrichtextfile', 'mod_casestudy'),
            get_string('status', 'core'),
        ];
        foreach ($report as $item) {
            $item->present ? $present++ : $missing++;
            $badge = $item->present
                ? html_writer::span(get_string('repairrichtextok', 'mod_casestudy'), 'badge badge-success')
                : html_writer::span(get_string('repairrichtextmissing', 'mod_casestudy'), 'badge badge-danger');
            $table->data[] = [$item->submissionid, s($item->filename), $badge];
        }
        echo $OUTPUT->heading(get_string('repairrichtextresults', 'mod_casestudy'), 4);
        echo html_writer::div(get_string('repairrichtextcounts', 'mod_casestudy',
            (object) ['present' => $present, 'missing' => $missing]), 'mb-2');
        if ($report) {
            echo html_writer::table($table);
        } else {
            echo $OUTPUT->notification(get_string('repairrichtextnoimages', 'mod_casestudy'), 'notifymessage');
        }
        if ($missing > 0) {
            echo $OUTPUT->notification(get_string('repairrichtextmissinghelp', 'mod_casestudy'), 'notifyproblem');
        }
    }
}

// Preview (dry run): count what an apply would change, and offer the apply button.
if ($action === get_string('repairrichtextpreview', 'mod_casestudy')) {
    $stats = richtext_repair::normalise($cmid, false);
    echo $OUTPUT->heading(get_string('repairrichtextpreviewheading', 'mod_casestudy'), 4);
    echo html_writer::div(get_string('repairrichtextwouldrewrite', 'mod_casestudy',
        (object) ['rows' => $stats->rows, 'urls' => $stats->urls, 'scanned' => $stats->scanned]), 'mb-2');

    if ($stats->rows > 0) {
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $cmid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('repairrichtextapply', 'mod_casestudy'),
            'class' => 'btn btn-primary',
        ]);
        echo html_writer::end_tag('form');
    }
}

// Apply: write the changes. POST + sesskey only.
if ($action === 'apply') {
    require_sesskey();
    $stats = richtext_repair::normalise($cmid, true);
    echo $OUTPUT->notification(get_string('repairrichtextdone', 'mod_casestudy',
        (object) ['rows' => $stats->rows, 'urls' => $stats->urls]), 'notifysuccess');
}

echo $OUTPUT->footer();
