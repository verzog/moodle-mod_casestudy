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
 * CLI tool to normalise absolute rich-text image URLs in existing submissions.
 *
 * Rich-text submission content can hold absolute pluginfile URLs that embed the
 * context and submission id (for example images inserted as file links). Those
 * URLs survive a course backup unchanged and 404 in the restored course. This
 * tool rewrites them in place to the @@PLUGINFILE@@ placeholder, which is
 * context/itemid independent, so the affected submissions become portable.
 *
 * Only URLs that point at each row's own submission rich-text area are touched,
 * so it is precise and safe to re-run.
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
        'dry-run' => false,
        'cmid' => 0,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln(<<<EOT
Normalise absolute rich-text image URLs in existing case study submissions.

Rewrites absolute pluginfile URLs that point at a submission's own rich-text
area to the @@PLUGINFILE@@ placeholder so the images survive course
backup/restore. Idempotent and safe to re-run.

Options:
  -h, --help        Show this help.
      --dry-run     Report what would change without modifying any content.
      --cmid=N      Limit to a single case study activity (course module id).

Examples:
  php mod/casestudy/cli/normalise_richtext_urls.php --dry-run
  php mod/casestudy/cli/normalise_richtext_urls.php
  php mod/casestudy/cli/normalise_richtext_urls.php --cmid=12936
EOT);
    exit(0);
}

$apply = empty($options['dry-run']);

cli_writeln($apply ? 'Normalising rich-text image URLs...' : '[dry run] Checking rich-text image URLs...');

$stats = \mod_casestudy\local\richtext_repair::normalise((int) $options['cmid'], $apply);

cli_writeln('');
cli_writeln(sprintf('Scanned content rows:   %d', $stats->scanned));
cli_writeln(sprintf('%s %d row(s), %d URL(s)',
    $apply ? 'Rewrote:               ' : 'Would rewrite:         ', $stats->rows, $stats->urls));

if (!$apply) {
    cli_writeln('');
    cli_writeln('Dry run only — no content was modified. Re-run without --dry-run to apply.');
}

exit(0);
