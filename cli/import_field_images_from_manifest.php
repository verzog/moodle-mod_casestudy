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
 * CLI tool to re-attach downloaded case study images from a manifest.
 *
 * Run on the restored target site. Reads the export manifest (CSV) and the folder of images you
 * downloaded read-only from the source, and writes each image back into the right field_<id> area
 * by matching content hash, email, activity name and submission order. Dry-run unless --commit.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_casestudy\local\manifest_image_importer;

list($options, $unrecognised) = cli_get_params(
    [
        'help' => false,
        'manifest' => '',
        'filesdir' => '',
        'courseid' => 0,
        'commit' => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || empty($options['manifest']) || empty($options['filesdir'])) {
    cli_writeln(<<<EOT
Re-attach downloaded case study images to a restored site from a manifest.

Required:
      --manifest=FILE   CSV exported by the manifest query (with a header row).
      --filesdir=DIR    Folder containing the downloaded image files.

Options:
  -h, --help            Show this help.
      --courseid=N      Restrict activity-name matching to one course (recommended).
      --commit          Actually write files. Without it, this is a dry run.

Examples:
  php mod/casestudy/cli/import_field_images_from_manifest.php \\
      --manifest=/data/casestudy_manifest.csv --filesdir=/data/images --courseid=42
  ... then re-run with --commit once the dry-run report looks right.
EOT);
    exit(0);
}

if (!is_readable($options['manifest'])) {
    cli_error("Manifest not readable: {$options['manifest']}");
}
if (!is_dir($options['filesdir'])) {
    cli_error("Files directory not found: {$options['filesdir']}");
}

$commit = !empty($options['commit']);
cli_writeln($commit ? 'Importing (writing files)...' : '[dry run] No files will be written.');

$stats = manifest_image_importer::import(
    $options['manifest'],
    $options['filesdir'],
    ['commit' => $commit, 'courseid' => (int) $options['courseid']],
    function($message) {
        cli_writeln($message);
    }
);

cli_writeln('');
cli_writeln('================ Summary ================');
cli_writeln(sprintf('Manifest rows:          %d', $stats->rows));
cli_writeln(sprintf('Files %s:        %d', $commit ? 'written ' : 'to write', $stats->written));
cli_writeln(sprintf('Already present:        %d', $stats->alreadypresent));
cli_writeln(sprintf('Skipped (no mapping):   %d', $stats->skippednomap));
cli_writeln(sprintf('Content conflicts:      %d', count($stats->conflicts)));
cli_writeln(sprintf('Missing local file:     %d', count($stats->missinglocal)));
cli_writeln(sprintf('Unmatched students:     %d', $stats->unmatchedusercount));
cli_writeln(sprintf('Ambiguous students:     %d', $stats->ambiguoususercount));
cli_writeln(sprintf('Unmatched activities:   %d', $stats->unmatchedactivitycount));
cli_writeln(sprintf('Submission-count mismatches: %d', count($stats->submissionmismatch)));

// Surface a sample of problems so they can be investigated.
foreach (array_slice($stats->submissionmismatch, 0, 10) as $line) {
    cli_writeln('  ! ' . $line);
}
foreach (array_slice($stats->conflicts, 0, 10) as $line) {
    cli_writeln('  ! existing file differs from manifest (left untouched): ' . $line);
}
foreach (array_slice(array_keys($stats->unmatchedusers), 0, 10) as $email) {
    cli_writeln('  ! no target user for email: ' . $email);
}
foreach (array_slice(array_keys($stats->ambiguoususers), 0, 10) as $email) {
    cli_writeln('  ! multiple active accounts share email (skipped): ' . $email);
}
foreach (array_slice(array_keys($stats->unmatchedactivities), 0, 10) as $name) {
    cli_writeln('  ! no unique target activity named: ' . $name);
}
foreach (array_slice($stats->missinglocal, 0, 10) as $miss) {
    cli_writeln('  ! no downloaded file for: ' . $miss);
}

if (!$commit) {
    cli_writeln('');
    cli_writeln('Dry run only — re-run with --commit to apply.');
}

exit(0);
