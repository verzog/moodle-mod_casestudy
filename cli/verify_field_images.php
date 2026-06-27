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
 * CLI tool to report how many file-field images a case study activity holds.
 *
 * Run it against a restored site to confirm the case study uploads actually came
 * through, per field. Exit code is non-zero if any checked activity has no images.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params(
    [
        'help' => false,
        'cmid' => 0,
        'all' => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || (empty($options['cmid']) && empty($options['all']))) {
    cli_writeln(<<<EOT
Report the number of file-field images stored by case study activities.

Options:
  -h, --help      Show this help.
      --cmid=N    Check a single case study by course-module id.
      --all       Check every case study activity on the site.

Examples:
  php mod/casestudy/cli/verify_field_images.php --cmid=1383
  php mod/casestudy/cli/verify_field_images.php --all
EOT);
    exit(0);
}

global $DB;
$fs = get_file_storage();

// Resolve the set of case study instance ids to check.
if (!empty($options['all'])) {
    $instanceids = $DB->get_fieldset_select('casestudy', 'id', '');
} else {
    $cm = get_coursemodule_from_id('casestudy', (int) $options['cmid'], 0, false, MUST_EXIST);
    $instanceids = [$cm->instance];
}

$grandimages = 0;
$grandbytes = 0;
$failures = 0;

foreach ($instanceids as $instanceid) {
    $cm = get_coursemodule_from_instance('casestudy', $instanceid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        continue;
    }
    $context = \context_module::instance($cm->id);
    $name = $DB->get_field('casestudy', 'name', ['id' => $instanceid]);
    $fields = $DB->get_records('casestudy_fields', ['casestudyid' => $instanceid, 'type' => 'file'], 'id');

    cli_writeln(sprintf("Case study '%s' (cmid %d)", $name, $cm->id));

    if (!$fields) {
        cli_writeln('  (no file fields)');
        cli_writeln('');
        continue;
    }

    $activityimages = 0;
    $activitybytes = 0;
    foreach ($fields as $field) {
        $files = $fs->get_area_files($context->id, 'mod_casestudy', 'field_' . $field->id, false, 'id', false);
        $count = 0;
        $bytes = 0;
        foreach ($files as $file) {
            if (!$file->is_directory()) {
                $count++;
                $bytes += $file->get_filesize();
            }
        }
        cli_writeln(sprintf('  %-32s %6d image(s)  %s', $field->shortname, $count, display_size($bytes)));
        $activityimages += $count;
        $activitybytes += $bytes;
    }

    cli_writeln(sprintf('  => %d image(s), %s', $activityimages, display_size($activitybytes)));
    if ($activityimages === 0) {
        cli_writeln('  RESULT: FAIL — no images present for this activity.');
        $failures++;
    } else {
        cli_writeln('  RESULT: OK');
    }
    cli_writeln('');

    $grandimages += $activityimages;
    $grandbytes += $activitybytes;
}

cli_writeln(sprintf('TOTAL: %d image(s), %s across checked activities.', $grandimages, display_size($grandbytes)));
exit($failures > 0 ? 1 : 0);
