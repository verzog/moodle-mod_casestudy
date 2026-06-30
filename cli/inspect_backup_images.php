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
 * CLI tool to inspect a Moodle backup (.mbz) for case study image files.
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
 * Read only — it never modifies the backup or the site.
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
Inspect a Moodle backup for case study (mod_casestudy) image files (read only).

Reports, per filearea, how many mod_casestudy files the backup actually contains.
Use it to confirm whether a backup taken on an old site still holds the submission
image bytes (submission_richtext and field_<id> areas) before relying on a restore.

Options:
  -h, --help          Show this help.
      --backup=PATH   Path to a .mbz backup file, OR a directory into which a
                      backup has already been extracted (must contain files.xml).

Examples:
  php mod/casestudy/cli/inspect_backup_images.php --backup=/path/to/backup.mbz
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

// Resolve a readable source for the backup's root files.xml. Accept either an
// already-extracted backup directory or a .mbz archive, which we read in place via
// the phar:// stream wrapper so the (potentially huge) file pool is never extracted.
$filesxmlsource = null;
$tmparchive = null;

if (is_dir($backup)) {
    $candidate = rtrim($backup, '/') . '/files.xml';
    if (!is_readable($candidate)) {
        cli_error("Directory has no readable files.xml: {$candidate}\n"
            . "Point --backup at the folder a backup was extracted into.");
    }
    $filesxmlsource = $candidate;
} else {
    // A .mbz is either a gzip-compressed tar or a zip — Moodle's mbz_packer supports
    // both, depending on the source site's configuration. Detect which by magic bytes
    // and stage the archive with the extension PharData needs to recognise the format,
    // then read files.xml out of it in place (no full extraction).
    $magic = (string) @file_get_contents($backup, false, null, 0, 4);
    $iszip = (strncmp($magic, "PK\x03\x04", 4) === 0) || (strncmp($magic, 'PK', 2) === 0);
    $isgzip = (strncmp($magic, "\x1f\x8b", 2) === 0);
    if (!$iszip && !$isgzip) {
        cli_error("Unrecognised .mbz format (expected gzip-tar or zip): {$backup}\n"
            . "Extract it manually and re-run against the folder it produces:\n"
            . "  php mod/casestudy/cli/inspect_backup_images.php --backup=/path/to/extracted-dir");
    }

    $tmparchive = make_request_directory() . '/backup' . ($iszip ? '.zip' : '.tar.gz');
    if (!@copy($backup, $tmparchive)) {
        cli_error("Could not stage backup archive for reading: {$backup}");
    }
    try {
        // Validate the archive (PharData reads both tar and zip) and confirm files.xml.
        new PharData($tmparchive);
        $candidate = 'phar://' . $tmparchive . '/files.xml';
        if (!@file_exists($candidate)) {
            throw new Exception('files.xml not found inside archive');
        }
        $filesxmlsource = $candidate;
    } catch (Throwable $e) {
        $extracthint = $iszip
            ? '  mkdir /tmp/bk && unzip -q ' . escapeshellarg($backup) . ' -d /tmp/bk'
            : '  mkdir /tmp/bk && tar xzf ' . escapeshellarg($backup) . ' -C /tmp/bk';
        cli_error("Could not read the .mbz archive automatically ({$e->getMessage()}).\n"
            . "Extract it manually and re-run against the folder:\n"
            . $extracthint . "\n"
            . "  php mod/casestudy/cli/inspect_backup_images.php --backup=/tmp/bk");
    }
}

// Stream the root files.xml and tally mod_casestudy files per filearea. Streaming
// keeps memory flat even on large backups with thousands of file records.
$reader = new XMLReader();
if (!@$reader->open($filesxmlsource)) {
    cli_error("Could not open files.xml for reading: {$filesxmlsource}");
}

// filearea => ['count' => int, 'images' => int, 'bytes' => int, 'contexts' => [contextid => true]].
$areas = [];
$othercomponents = 0;

while ($reader->read()) {
    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'file') {
        continue;
    }
    $xml = simplexml_load_string($reader->readOuterXml());
    if ($xml === false) {
        continue;
    }
    if ((string) $xml->component !== 'mod_casestudy') {
        $othercomponents++;
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

// Report.
cli_writeln('');
cli_writeln('mod_casestudy file inventory in this backup');
cli_writeln('-------------------------------------------');

if (empty($areas)) {
    cli_writeln('  No mod_casestudy files of any kind are present in this backup.');
} else {
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
}
cli_writeln('');

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

cli_writeln(sprintf('Rich-text submission images (submission_richtext): %d', $richtext));
cli_writeln(sprintf('File-field uploads (field_<id>):                  %d (%d image)', $fieldfiles, $fieldimages));
if ($legacyfiles > 0) {
    cli_writeln(sprintf('Legacy content-area uploads (migrated on restore): %d (%d image)', $legacyfiles, $legacyimages));
}
cli_writeln('');

if ($recoverablefiles === 0) {
    cli_writeln('VERDICT: This backup contains NO submission file bytes.');
    cli_writeln('');
    cli_writeln('The source site stored these files at runtime, but the mod_casestudy');
    cli_writeln('version it ran did not annotate the submission_richtext / field_<id> areas');
    cli_writeln('for backup, so the files were never written into this .mbz. A restore');
    cli_writeln('therefore has nothing to bring across, and they CANNOT be recovered');
    cli_writeln('from this archive. Recovery requires the original files from the source');
    cli_writeln('site (a fresh backup taken with a current plugin version, or the bytes');
    cli_writeln('pulled directly from the source moodledata / via an authenticated download).');
    exit(2);
}

if ($recoverableimages === 0) {
    cli_writeln('VERDICT: This backup contains submission file bytes, but NONE are images.');
    cli_writeln('');
    cli_writeln(sprintf('It holds %d submission file(s), none of which are image files. If you are', $recoverablefiles));
    cli_writeln('specifically chasing missing images, they are not in this archive — only');
    cli_writeln('non-image attachments are present. Those files will still restore with the');
    cli_writeln('current plugin version (ensure "Include user data" is enabled for the restore).');
    exit(0);
}

cli_writeln('VERDICT: This backup DOES contain submission image bytes.');
cli_writeln('');
cli_writeln('These will restore into place with the current plugin version. If a restore');
cli_writeln('left images missing, check that "Include user data" (enrolled users) was');
cli_writeln('enabled for the restore — without it, submissions and their files are skipped —');
cli_writeln('then restore again. Use cli/verify_field_images.php and');
cli_writeln('cli/diagnose_richtext_images.php on the restored site to confirm.');
exit(0);
