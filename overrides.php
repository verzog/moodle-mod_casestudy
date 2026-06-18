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
 * This page handles listing of case study overrides
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/casestudy/lib.php');

$cmid = required_param('cmid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'casestudy');
$casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

// Check the user has the required capabilities to list overrides.
require_capability('mod/casestudy:manageoverrides', $context);

$url = new moodle_url('/mod/casestudy/overrides.php', ['cmid' => $cm->id]);

$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('overridesfor', 'casestudy', format_string($casestudy->name)));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->disable();

// Activate the secondary nav tab.
$PAGE->set_secondary_active_tab("mod_casestudy_useroverrides");

// Fetch all overrides for this case study.
$userfieldsapi = \core_user\fields::for_name();
$userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

$overrides = $DB->get_records_sql("
    SELECT o.*, {$userfields}
      FROM {casestudy_overrides} o
      JOIN {user} u ON o.userid = u.id
     WHERE o.casestudyid = :casestudyid
  ORDER BY u.lastname, u.firstname
", ['casestudyid' => $casestudy->id]);

// Initialize table.
$table = new html_table();
$table->head = [
    get_string('student', 'casestudy'),
    get_string('overridesettings', 'casestudy'),
    '',
    get_string('action'),
];
$table->colclasses = ['colname', 'colsetting', 'colvalue', 'colaction'];
$table->attributes['class'] = 'generaltable overridetable';

$userurl = new moodle_url('/user/view.php', []);
$overridedeleteurl = new moodle_url('/mod/casestudy/overridedelete.php');
$overrideediturl = new moodle_url('/mod/casestudy/overrideedit.php');

foreach ($overrides as $override) {
    // Prepare the information about which settings are overridden.
    $fields = [];
    $values = [];

    // Format timeclose.
    if (isset($override->timeclose)) {
        $fields[] = get_string('casestudycloses', 'casestudy');
        $values[] = $override->timeclose > 0 ?
                userdate($override->timeclose) : get_string('noclose', 'casestudy');
    }

    // Format max attempts.
    if (isset($override->maxattempts)) {
        $fields[] = get_string('totalattempts', 'casestudy');
        $values[] = $override->maxattempts;
    }

    // Prepare the information about who this override applies to.
    $usercell = new html_table_cell();
    $usercell->rowspan = count($fields);
    $usercell->text = html_writer::link(
        new moodle_url($userurl, ['id' => $override->userid]),
        fullname($override)
    );

    // Prepare the actions.
    $iconstr = '';

    // Edit.
    $editurlstr = $overrideediturl->out(true, ['id' => $override->id]);
    $iconstr = '<a title="' . get_string('edit') . '" href="' . $editurlstr . '">' .
            $OUTPUT->pix_icon('t/edit', get_string('edit')) . '</a> ';

    // Duplicate.
    $copyurlstr = $overrideediturl->out(
        true,
        ['id' => $override->id, 'action' => 'duplicate']
    );
    $iconstr .= '<a title="' . get_string('copy') . '" href="' . $copyurlstr . '">' .
            $OUTPUT->pix_icon('t/copy', get_string('copy')) . '</a> ';

    // Delete.
    $deleteurlstr = $overridedeleteurl->out(
        true,
        ['id' => $override->id, 'sesskey' => sesskey()]
    );
    $iconstr .= '<a title="' . get_string('delete') . '" href="' . $deleteurlstr . '">' .
            $OUTPUT->pix_icon('t/delete', get_string('delete')) . '</a> ';

    $actioncell = new html_table_cell();
    $actioncell->rowspan = count($fields);
    $actioncell->text = $iconstr;

    // Add the data to the table.
    for ($i = 0; $i < count($fields); ++$i) {
        $row = new html_table_row();

        // Add user cell only on first row.
        if ($i == 0) {
            $row->cells[] = $usercell;
        }

        // Add setting label cell.
        $labelcell = new html_table_cell();
        $labelcell->text = $fields[$i];
        $row->cells[] = $labelcell;

        // Add setting value cell.
        $valuecell = new html_table_cell();
        $valuecell->text = $values[$i];
        $row->cells[] = $valuecell;

        // Add action cell only on first row.
        if ($i == 0) {
            $row->cells[] = $actioncell;
        }

        $table->data[] = $row;
    }
}

// Determine if we can add new overrides.
$addenabled = true;
$warningmessage = '';

// See if there are any students who can submit case studies.
$users = get_enrolled_users($context, 'mod/casestudy:submit', 0, 'u.id', null, 0, 0, true);

if (empty($users)) {
    $warningmessage = get_string('usersnone', 'casestudy');
    $addenabled = false;
} else {
    // Check if all users already have overrides.
    $existingoverridecount = $DB->count_records('casestudy_overrides', ['casestudyid' => $casestudy->id]);
    if ($existingoverridecount >= count($users)) {
        $warningmessage = get_string('usersnone', 'casestudy');
        $addenabled = false;
    }
}

// Output the page.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('useroverrides', 'casestudy'));

// Add override button.
if ($addenabled) {
    $addurl = new moodle_url('/mod/casestudy/overrideedit.php', [
        'cmid' => $cm->id,
        'action' => 'adduser',
    ]);
    echo $OUTPUT->single_button($addurl, get_string('addoverride', 'casestudy'), 'get');
}

// Output the table.
echo html_writer::start_tag('div', ['id' => 'casestudyoverrides']);
if (count($table->data)) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nooverrides', 'casestudy'), 'info', false);
}

if ($warningmessage) {
    echo $OUTPUT->notification($warningmessage, 'warning');
}

echo html_writer::end_tag('div');

// Finish the page.
echo $OUTPUT->footer();
