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
 * CLI tool to optimise images already stored in case study file fields.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_casestudy\local\image_optimizer;

list($options, $unrecognised) = cli_get_params(
    [
        'help' => false,
        'dry-run' => false,
        'maxedge' => 0,
        'quality' => 0,
        'queue' => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln(<<<EOT
Optimise images stored in case study file fields (downscale + re-encode).

Only oversized images are changed, and only once, so it is safe to re-run.

Options:
  -h, --help        Show this help.
      --dry-run     Report what would change without modifying any file.
      --maxedge=N   Longest-edge cap in pixels (default: site setting).
      --quality=N   JPEG quality 1-100 (default: site setting).
      --queue       Queue the adhoc task to run via cron instead of running now.

Examples:
  php mod/casestudy/cli/optimize_images.php --dry-run
  php mod/casestudy/cli/optimize_images.php --maxedge=2560 --quality=85
  php mod/casestudy/cli/optimize_images.php --queue
EOT);
    exit(0);
}

$maxedge = ((int) $options['maxedge']) > 0 ? (int) $options['maxedge'] : image_optimizer::get_max_edge();
$quality = ((int) $options['quality']) > 0 ? (int) $options['quality'] : image_optimizer::get_quality();

// Hand off to the batched adhoc task when asked, so cron does the heavy lifting in the background.
if ($options['queue']) {
    $task = new \mod_casestudy\task\optimize_existing_images();
    $task->set_custom_data((object) ['maxedge' => $maxedge, 'quality' => $quality]);
    \core\task\manager::queue_adhoc_task($task);
    cli_writeln('Queued image optimisation task. It will run on the next cron.');
    exit(0);
}

$apply = empty($options['dry-run']);
$fs = get_file_storage();
$fileids = image_optimizer::get_field_file_ids();

cli_writeln(sprintf(
    '%s %d candidate image file(s) at max edge %dpx, quality %d...',
    $apply ? 'Optimising' : '[dry run] Checking',
    count($fileids),
    $maxedge,
    $quality
));

$files = [];
foreach ($fileids as $fileid) {
    if ($stored = $fs->get_file_by_id($fileid)) {
        $files[] = $stored;
    }
}

$progress = function($stats, $file, $changed) {
    if ($changed && ($stats->optimized % 50 === 0)) {
        cli_writeln(sprintf('  ... %d optimised so far', $stats->optimized));
    }
};

$stats = image_optimizer::optimize_files($fs, $files, $maxedge, $quality, $apply, $progress);

$saved = max(0, $stats->bytesbefore - $stats->bytesafter);
$percent = $stats->bytesbefore > 0 ? round($saved / $stats->bytesbefore * 100, 1) : 0;

cli_writeln('');
cli_writeln(sprintf('Processed:        %d image(s)', $stats->processed));
cli_writeln(sprintf('%s %d image(s)', $apply ? 'Optimised:       ' : 'Would optimise:  ', $stats->optimized));
cli_writeln(sprintf('Size before:      %s', display_size($stats->bytesbefore)));
cli_writeln(sprintf('Size after:       %s', display_size($stats->bytesafter)));
cli_writeln(sprintf('%s %s (%s%%)', $apply ? 'Saved:           ' : 'Would save:      ', display_size($saved), $percent));

if (!$apply) {
    cli_writeln('');
    cli_writeln('Dry run only — no files were modified. Re-run without --dry-run to apply.');
}

exit(0);
