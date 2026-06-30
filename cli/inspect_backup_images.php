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
 * CLI tool to inspect Moodle backup(s) (.mbz) for case study image files.
 *
 * A Moodle backup only contains files that the source site's backup code
 * annotated. Older mod_casestudy versions stored submission images at runtime
 * (in the submission_richtext and field_<id> areas) but did NOT annotate those
 * areas for backup, so their .mbz archives contain no submission image bytes at
 * all. When such a backup is restored, the data restores correctly but there are
 * simply no image files to bring across, and they cannot be recovered from that
 * archive.
 *
 * This tool answers, without touching the source site and without restoring,
 * the one question that decides what is recoverable: does this backup actually
 * contain the image bytes? It reads the backup's root files.xml and reports, per
 * filearea, how many mod_casestudy files (and how many bytes) the archive holds.
 *
 * --backup may point at a single .mbz file, an already-extracted backup folder,
 * or a directory full of .mbz course backups (e.g. a file-system repository of
 * course backups) — in the last case every .mbz is scanned so you can see which
 * backup holds the case study and whether its images are present.
 *
 * Read only — it never modifies any backup or the site.
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
        'backup' => '',
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || empty($options['backup'])) {
    cli_writeln(<<<EOT
Inspect Moodle backup(s) for case study (mod_casestudy) image files (read only).

Reports, per filearea, how many mod_casestudy files a backup actually contains.
Use it to confirm whether a backup taken on an old site still holds the submission
image bytes (submission_richtext and field_<id> areas) before relying on a restore.

Options:
  -h, --help          Show this help.
      --backup=PATH   One of:
                        * a .mbz backup file;
                        * a directory full of .mbz course backups (each is
                          scanned, so you can find which one holds the case
                          study and whether its images are present);
                        * a directory a single backup was extracted into
                          (it must contain files.xml).

Examples:
  php mod/casestudy/cli/inspect_backup_images.php --backup=/path/to/backup.mbz
  php mod/casestudy/cli/inspect_backup_images.php --backup=/home/yunohost.app/moodle__3/repository/courses
  php mod/casestudy/cli/inspect_backup_images.php --backup=/path/to/extracted-backup-dir

If automatic .mbz reading fails, extract it first and point --backup at the
folder. A .mbz is either a gzip-tar or a zip:
  mkdir /tmp/bk && tar xzf /path/to/backup.mbz -C /tmp/bk   # gzip-tar backups
  mkdir /tmp/bk && unzip -q /path/to/backup.mbz -d /tmp/bk  # zip backups
  php mod/casestudy/cli/inspect_backup_images.php --backup=/tmp/bk
EOT);
    exit(empty($options['backup']) && !$options['help'] ? 1 : 0);
}

$backup = $options['backup'];
if (!file_exists($backup)) {
    cli_error("Backup path not found: {$backup}");
}

// Build the work list: each entry is [label, kind, path] where kind is 'dir'
// (an extracted backup folder containing files.xml) or 'mbz' (an archive file).
$targets = [];
if (is_dir($backup)) {
    if (is_readable(rtrim($backup, '/') . '/files.xml')) {
        // A single already-extracted backup.
        $targets[] = [basename(rtrim($backup, '/')), 'dir', rtrim($backup, '/')];
    } else {
        // A folder of .mbz course backups — scan each one.
        foreach (scandir($backup) ?: [] as $entry) {
            $path = rtrim($backup, '/') . '/' . $entry;
            if (is_file($path) && strtolower((string) pathinfo($entry, PATHINFO_EXTENSION)) === 'mbz') {
                $targets[] = [$entry, 'mbz', $path];
            }
        }
        sort($targets);
        if (empty($targets)) {
            cli_error("No files.xml and no .mbz files found in directory: {$backup}\n"
                . "Point --backup at a .mbz file, a folder of .mbz course backups, or an\n"
                . "extracted backup folder (one that contains files.xml).");
        }
    }
} else {
    $targets[] = [basename($backup), 'mbz', $backup];
}

$multi = count($targets) > 1;
if ($multi) {
    cli_writeln(sprintf('Scanning %d .mbz backup(s) in %s', count($targets), $backup));
}

// Inspect every target. Track the best outcome so a directory scan can exit with a
// code that reflects whether ANY backup is recoverable.
$worstoutcome = 'none';   // none < files < images, where 'images' is the best result.
$summaries = [];

foreach ($targets as [$label, $kind, $path]) {
    cli_writeln('');
    cli_writeln('=== ' . $label . ' ===');

    $filesxmlsource = casestudy_resolve_filesxml($kind, $path, $multi);
    if ($filesxmlsource === null) {
        // Unreadable archive in a multi scan: reported inline, keep going.
        $summaries[] = [$label, 'unreadable', 0, 0];
        continue;
    }

    $areas = casestudy_tally_areas($filesxmlsource);
    if ($areas === null) {
        $summaries[] = [$label, 'unreadable', 0, 0];
        continue;
    }

    [$outcome, $files, $images] = casestudy_report_areas($areas);
    $summaries[] = [$label, $outcome, $files, $images];
    if (casestudy_outcome_rank($outcome) > casestudy_outcome_rank($worstoutcome)) {
        $worstoutcome = $outcome;
    }
}

// Directory scan: print a roll-up so the relevant backup is easy to spot.
if ($multi) {
    cli_writeln('');
    cli_writeln('Summary');
    cli_writeln('-------');
    foreach ($summaries as [$label, $outcome, $files, $images]) {
        cli_writeln(sprintf('  %-40s %s', $label, casestudy_outcome_label($outcome, $files, $images)));
    }
    cli_writeln('');

    // Count archives that could not be read so the closing verdict can be scoped to the
    // backups actually inspected rather than implying the whole directory was covered.
    $skipped = 0;
    foreach ($summaries as [, $outcome]) {
        if ($outcome === 'unreadable') {
            $skipped++;
        }
    }
    $scope = $skipped > 0 ? 'No readable backup here' : 'No backup here';

    if ($worstoutcome === 'images') {
        cli_writeln('At least one backup contains case study image bytes (marked IMAGES above).');
    } else if ($worstoutcome === 'files') {
        cli_writeln('Submission files were found, but none are images. ' . $scope . ' holds case study images.');
    } else {
        cli_writeln($scope . ' contains any case study submission file bytes.');
    }
    if ($skipped > 0) {
        cli_writeln(sprintf('NOTE: %d archive(s) could not be read and were skipped (listed above); this '
            . 'verdict covers only the backups that were inspected.', $skipped));
    }
}

exit(casestudy_exit_code($worstoutcome));

/**
 * Resolve a readable files.xml source for one target (an extracted folder or a .mbz).
 *
 * For a .mbz the archive is read in place via the phar:// stream wrapper so the
 * (potentially huge) file pool is never extracted.
 *
 * @param string $kind 'dir' for an extracted backup folder, 'mbz' for an archive file.
 * @param string $path Filesystem path to the target.
 * @param bool $lenient When true (directory scan), report problems inline and return
 *                      null instead of aborting the whole run.
 * @return string|null A path/stream readable by XMLReader, or null when unreadable in lenient mode.
 */
function casestudy_resolve_filesxml(string $kind, string $path, bool $lenient): ?string {
    if ($kind === 'dir') {
        return rtrim($path, '/') . '/files.xml';
    }

    // A .mbz is either a gzip-compressed tar or a zip — Moodle's mbz_packer supports
    // both, depending on the source site's configuration. Detect which by magic bytes
    // and stage the archive with the extension PharData needs to recognise the format.
    $magic = (string) @file_get_contents($path, false, null, 0, 4);
    $iszip = (strncmp($magic, "PK\x03\x04", 4) === 0) || (strncmp($magic, 'PK', 2) === 0);
    $isgzip = (strncmp($magic, "\x1f\x8b", 2) === 0);
    if (!$iszip && !$isgzip) {
        return casestudy_unreadable($path, $lenient, 'unrecognised format (expected gzip-tar or zip)', false);
    }

    // Stage under a filename unique to this call. The phar:// wrapper caches archive
    // contents by path, so reusing one pathname across archives in a directory scan
    // could otherwise return an earlier archive's files.xml.
    static $seq = 0;
    $seq++;
    $tmparchive = make_request_directory() . '/backup' . $seq . ($iszip ? '.zip' : '.tar.gz');
    if (!@copy($path, $tmparchive)) {
        return casestudy_unreadable($path, $lenient, 'could not stage archive for reading', $iszip);
    }
    try {
        // Validate the archive (PharData reads both tar and zip) and confirm files.xml.
        new PharData($tmparchive);
        $source = 'phar://' . $tmparchive . '/files.xml';
        if (!@file_exists($source)) {
            throw new Exception('files.xml not found inside archive');
        }
        return $source;
    } catch (Throwable $e) {
        return casestudy_unreadable($path, $lenient, $e->getMessage(), $iszip);
    }
}

/**
 * Handle an unreadable archive: warn (lenient) or abort with extract hints (strict).
 *
 * @param string $path Archive path.
 * @param bool $lenient When true, print a warning and return null; otherwise cli_error.
 * @param string $reason Short human-readable reason.
 * @param bool $iszip Whether the archive looked like a zip (selects the extract hint).
 * @return null Always null (only reached in lenient mode; strict mode exits).
 */
function casestudy_unreadable(string $path, bool $lenient, string $reason, bool $iszip) {
    if ($lenient) {
        cli_writeln("  Skipped — could not read archive ({$reason}).");
        return null;
    }
    $extracthint = $iszip
        ? '  mkdir /tmp/bk && unzip -q ' . escapeshellarg($path) . ' -d /tmp/bk'
        : '  mkdir /tmp/bk && tar xzf ' . escapeshellarg($path) . ' -C /tmp/bk';
    cli_error("Could not read the .mbz archive ({$reason}): {$path}\n"
        . "Extract it manually and re-run against the folder:\n"
        . $extracthint . "\n"
        . "  php mod/casestudy/cli/inspect_backup_images.php --backup=/tmp/bk");
    return null;
}

/**
 * Stream a backup's root files.xml and tally mod_casestudy files per filearea.
 *
 * Streaming keeps memory flat even on large backups with thousands of file records.
 *
 * @param string $filesxmlsource Path or stream readable by XMLReader.
 * @return array<string,array{count:int,images:int,bytes:int,contexts:array}>|null
 *         Per-filearea tallies, or null if files.xml could not be opened.
 */
function casestudy_tally_areas(string $filesxmlsource): ?array {
    $reader = new XMLReader();
    if (!@$reader->open($filesxmlsource)) {
        cli_writeln('  Skipped — could not open files.xml.');
        return null;
    }

    $areas = [];
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'file') {
            continue;
        }
        $xml = simplexml_load_string($reader->readOuterXml());
        if ($xml === false) {
            continue;
        }
        if ((string) $xml->component !== 'mod_casestudy') {
            continue;
        }
        $filename = (string) $xml->filename;
        // Skip directory records (Moodle stores a '.' entry per folder).
        if ($filename === '' || $filename === '.') {
            continue;
        }
        $filearea = (string) $xml->filearea;
        if ($filearea === '') {
            $filearea = '(none)';
        }
        // A file field accepts any file type (the default accepted-types list is ['*']), so
        // not every stored file is an image. Classify by the recorded mimetype, falling back
        // to the filename extension, so the verdict can speak to images specifically rather
        // than counting PDFs or other attachments as image bytes.
        $mimetype = (string) $xml->mimetype;
        $isimage = (strncmp($mimetype, 'image/', 6) === 0)
            || (bool) preg_match('/\.(png|jpe?g|gif|webp|bmp|svg|svgz|tiff?|heic|avif)$/i', $filename);
        if (!isset($areas[$filearea])) {
            $areas[$filearea] = ['count' => 0, 'images' => 0, 'bytes' => 0, 'contexts' => []];
        }
        $areas[$filearea]['count']++;
        $areas[$filearea]['images'] += $isimage ? 1 : 0;
        $areas[$filearea]['bytes'] += (int) $xml->filesize;
        $areas[$filearea]['contexts'][(string) $xml->contextid] = true;
    }
    $reader->close();

    return $areas;
}

/**
 * Print one backup's inventory and verdict, and return its outcome.
 *
 * @param array $areas Per-filearea tallies from casestudy_tally_areas().
 * @return array{0:string,1:int,2:int} [outcome, recoverablefiles, recoverableimages]
 *         where outcome is 'none', 'files' or 'images'.
 */
function casestudy_report_areas(array $areas): array {
    if (empty($areas)) {
        cli_writeln('  No mod_casestudy files are present in this backup.');
        return ['none', 0, 0];
    }

    ksort($areas);
    foreach ($areas as $name => $info) {
        cli_writeln(sprintf(
            '  %-22s %6d file(s) (%d image)  %10s   across %d context(s)',
            $name,
            $info['count'],
            $info['images'],
            display_size($info['bytes']),
            count($info['contexts'])
        ));
    }

    // Areas that hold student-supplied submission files, and whether each carries images.
    // submission_richtext = embedded rich-text images; field_<id> = file-field uploads (any
    // type); content = the legacy upload area that restore migrates into field_<id> (see
    // legacyfilecontents in restore_casestudy_stepslib.php), so it counts as recoverable too.
    $richtext = $areas['submission_richtext']['count'] ?? 0;
    $richtextimages = $areas['submission_richtext']['images'] ?? 0;
    $fieldfiles = 0;
    $fieldimages = 0;
    foreach ($areas as $name => $info) {
        if (strpos($name, 'field_') === 0) {
            $fieldfiles += $info['count'];
            $fieldimages += $info['images'];
        }
    }
    $legacyfiles = $areas['content']['count'] ?? 0;
    $legacyimages = $areas['content']['images'] ?? 0;

    $recoverablefiles = $richtext + $fieldfiles + $legacyfiles;
    $recoverableimages = $richtextimages + $fieldimages + $legacyimages;

    cli_writeln(sprintf('  -> rich-text images: %d   file-field uploads: %d (%d image)%s',
        $richtext,
        $fieldfiles,
        $fieldimages,
        $legacyfiles > 0 ? sprintf('   legacy content: %d (%d image)', $legacyfiles, $legacyimages) : ''
    ));

    if ($recoverablefiles === 0) {
        cli_writeln('  VERDICT: NO submission file bytes — images cannot be recovered from this backup.');
        return ['none', 0, 0];
    }
    if ($recoverableimages === 0) {
        cli_writeln('  VERDICT: submission files present, but NONE are images.');
        return ['files', $recoverablefiles, 0];
    }
    cli_writeln('  VERDICT: contains submission image bytes — these will restore with the current plugin.');
    return ['images', $recoverablefiles, $recoverableimages];
}

/**
 * Rank an outcome so a directory scan can keep the best result seen.
 *
 * @param string $outcome 'none', 'files' or 'images'.
 * @return int 0, 1 or 2.
 */
function casestudy_outcome_rank(string $outcome): int {
    return ['none' => 0, 'files' => 1, 'images' => 2][$outcome] ?? 0;
}

/**
 * One-line summary label for the directory-scan roll-up.
 *
 * @param string $outcome Outcome keyword.
 * @param int $files Recoverable file count.
 * @param int $images Recoverable image count.
 * @return string
 */
function casestudy_outcome_label(string $outcome, int $files, int $images): string {
    switch ($outcome) {
        case 'images':
            return sprintf('IMAGES  (%d image file(s) of %d submission file(s))', $images, $files);
        case 'files':
            return sprintf('files only  (%d non-image submission file(s), no images)', $files);
        case 'unreadable':
            return 'unreadable archive (skipped)';
        default:
            return 'no case study submission files';
    }
}

/**
 * Process exit code reflecting the best outcome across all scanned backups.
 *
 * 0 = image bytes present somewhere; 1 = submission files but no images; 2 = nothing.
 *
 * @param string $worstoutcome Best outcome keyword seen.
 * @return int
 */
function casestudy_exit_code(string $worstoutcome): int {
    if ($worstoutcome === 'images') {
        return 0;
    }
    return $worstoutcome === 'files' ? 1 : 2;
}
