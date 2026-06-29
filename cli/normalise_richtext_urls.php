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

// Build the row set: only content that actually mentions the rich-text area, optionally
// limited to one activity.
$needle = '%submission_richtext%';
$params = ['needle' => $needle];

if (!empty($options['cmid'])) {
    $cm = get_coursemodule_from_id('casestudy', (int) $options['cmid'], 0, false, MUST_EXIST);
    $sql = "SELECT cc.id, cc.content, cc.submissionid
              FROM {casestudy_content} cc
              JOIN {casestudy_submissions} cs ON cs.id = cc.submissionid
             WHERE cs.casestudyid = :instance
               AND " . $DB->sql_like('cc.content', ':needle');
    $params['instance'] = $cm->instance;
} else {
    $sql = "SELECT id, content, submissionid
              FROM {casestudy_content}
             WHERE " . $DB->sql_like('content', ':needle');
}

cli_writeln($apply ? 'Normalising rich-text image URLs...' : '[dry run] Checking rich-text image URLs...');

$scanned = 0;
$changedrows = 0;
$replacements = 0;

$rs = $DB->get_recordset_sql($sql, $params);
foreach ($rs as $row) {
    $scanned++;
    $subid = (int) $row->submissionid;
    if ($subid <= 0 || $row->content === null || $row->content === '') {
        continue;
    }

    // Only rewrite URLs that target this row's own submission rich-text area, so a
    // (rare) reference to another submission's file is never silently retargeted.
    $patterns = [
        '~https?://[^"\'\s<>]+?/pluginfile\.php/\d+/mod_casestudy/submission_richtext/' . $subid . '/~i',
        '~https?://[^"\'\s<>]+?/pluginfile\.php\?file=/\d+/mod_casestudy/submission_richtext/' . $subid . '/~i',
    ];

    $count = 0;
    $updated = preg_replace($patterns, '@@PLUGINFILE@@/', $row->content, -1, $count);

    if ($updated !== null && $count > 0 && $updated !== $row->content) {
        $changedrows++;
        $replacements += $count;
        if ($apply) {
            $DB->set_field('casestudy_content', 'content', $updated, ['id' => $row->id]);
        }
    }
}
$rs->close();

cli_writeln('');
cli_writeln(sprintf('Scanned content rows:   %d', $scanned));
cli_writeln(sprintf('%s %d row(s), %d URL(s)',
    $apply ? 'Rewrote:               ' : 'Would rewrite:         ', $changedrows, $replacements));

if (!$apply) {
    cli_writeln('');
    cli_writeln('Dry run only — no content was modified. Re-run without --dry-run to apply.');
}

exit(0);
