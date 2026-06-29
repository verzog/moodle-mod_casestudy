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
 * CLI tool to diagnose missing rich-text images in case study submissions.
 *
 * For each rich-text submission content row it lists every image the content
 * references and reports whether the corresponding file actually exists in the
 * submission's rich-text file area. This distinguishes a URL problem (file
 * present, link stale) from a missing-file problem (e.g. the course was restored
 * from a backup that predates rich-text image support, so the files were never
 * included and cannot be recovered without a fresh backup).
 *
 * Read only — it never modifies anything.
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
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

if ($options['help'] || empty($options['cmid'])) {
    cli_writeln(<<<EOT
Diagnose rich-text images in case study submissions (read only).

Lists, per submission, each image the rich-text content references and whether
the file exists in storage.

Options:
  -h, --help        Show this help.
      --cmid=N      Case study activity (course module id). Required.

Example:
  php mod/casestudy/cli/diagnose_richtext_images.php --cmid=12936
EOT);
    exit(empty($options['cmid']) && !$options['help'] ? 1 : 0);
}

if (!is_numeric($options['cmid']) || (int) $options['cmid'] <= 0) {
    cli_error('--cmid must be a positive integer.');
}

$report = \mod_casestudy\local\richtext_repair::diagnose((int) $options['cmid']);

$totalpresent = 0;
$totalmissing = 0;
$bysubmission = [];
foreach ($report as $item) {
    $bysubmission[$item->submissionid][] = $item;
    $item->present ? $totalpresent++ : $totalmissing++;
}

cli_writeln(sprintf('%d submission(s) reference rich-text images.', count($bysubmission)));

foreach ($bysubmission as $submissionid => $items) {
    cli_writeln('');
    cli_writeln(sprintf('Submission %d:', $submissionid));
    foreach ($items as $item) {
        cli_writeln(sprintf('  [%s] %s', $item->present ? ' OK  ' : 'MISS', $item->filename));
    }
}

cli_writeln('');
cli_writeln(sprintf('Files present: %d   Files missing: %d', $totalpresent, $totalmissing));
if ($totalmissing > 0) {
    cli_writeln('');
    cli_writeln('MISS = the image file is not in storage for that submission. If these came');
    cli_writeln('from a restore, the backup likely predates rich-text image support — re-create');
    cli_writeln('the backup with the current plugin and restore again. Present-but-not-showing');
    cli_writeln('files are a URL issue, fixed by normalise_richtext_urls.php / the display fix.');
}

exit(0);
