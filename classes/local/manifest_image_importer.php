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
 * Re-attach case study images from a downloaded manifest into a restored site.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only side-channel image restore.
 *
 * Production is never modified: images are pulled out via SQL + an authenticated browser download
 * (yielding a manifest CSV and a folder of files), and this engine — run on the restored target —
 * writes them back into the right field_<id> areas. It deliberately depends on nothing from the
 * source except the manifest and the downloaded bytes.
 *
 * Matching is built to survive a restore:
 *  - local file  -> manifest row : by content hash (also an integrity check).
 *  - student     -> target user  : by email.
 *  - activity    -> target module: by name (optionally scoped to one course).
 *  - submission  -> target submission: paired by id order within (activity, user), so a restore
 *                  date-offset cannot break the link.
 *  - field       -> target field : by shortname.
 *
 * Dry-run unless $opts['commit'] is true.
 */
class manifest_image_importer {

    /** @var array Required manifest column headers. */
    const REQUIRED_COLUMNS = ['casestudy', 'field', 'old_submissionid', 'email', 'filename', 'contenthash'];

    /**
     * Run the import.
     *
     * @param string $manifestpath Path to the CSV produced by the export query (with a header row)
     * @param string $filesdir Folder containing the downloaded image files (searched recursively)
     * @param array $opts Keys: commit (bool), courseid (int, optional activity-name scope)
     * @param callable|null $log Optional logger: function(string $message): void
     * @return \stdClass Stats (see new_stats())
     */
    public static function import(string $manifestpath, string $filesdir, array $opts, ?callable $log = null): \stdClass {
        global $DB;

        $log = $log ?? function($m) {
        };
        $commit = !empty($opts['commit']);
        $courseid = isset($opts['courseid']) ? (int) $opts['courseid'] : 0;

        $stats = self::new_stats();
        $fs = get_file_storage();

        // 1. Index the downloaded files by content hash (this also verifies integrity at write time).
        $log('Hashing downloaded files (this can take a while)...');
        $byhash = self::index_by_contenthash($filesdir, $log);
        $log(sprintf('Indexed %d local file(s).', count($byhash)));

        // 2. Read the manifest and group rows by (activity name, email).
        $groups = self::read_manifest_grouped($manifestpath);
        $stats->rows = self::count_rows($groups);
        $log(sprintf('Manifest: %d row(s) across %d (activity, student) group(s).', $stats->rows, count($groups)));

        // Per-run caches.
        $usercache = [];
        $activitycache = [];
        $fieldcache = [];

        foreach ($groups as $key => $rows) {
            [$activityname, $email] = explode("\0", $key, 2);

            // Resolve target user by email. Require a unique active account: on sites that allow
            // duplicate emails (allowaccountssameemail) a non-unique match cannot safely identify
            // the owner, and these are private images — flag the ambiguity instead of guessing.
            $userid = self::cache($usercache, $email, function() use ($DB, $email) {
                $matches = $DB->get_records('user', ['email' => $email, 'deleted' => 0], 'id', 'id');
                if (count($matches) === 1) {
                    return (int) reset($matches)->id;
                }
                // 0 = no account, -1 = ambiguous (more than one active account with this email).
                return empty($matches) ? 0 : -1;
            });
            if ($userid === -1) {
                $stats->ambiguoususers[$email] = true;
                continue;
            }
            if (!$userid) {
                $stats->unmatchedusers[$email] = true;
                continue;
            }

            // Resolve target case study by name (optionally within one course).
            $casestudyid = self::cache($activitycache, $activityname, function() use ($DB, $activityname, $courseid) {
                $params = ['name' => $activityname];
                if ($courseid) {
                    $params['course'] = $courseid;
                }
                $matches = $DB->get_records('casestudy', $params, 'id', 'id');
                return count($matches) === 1 ? (int) reset($matches)->id : 0;
            });
            if (!$casestudyid) {
                $stats->unmatchedactivities[$activityname] = true;
                continue;
            }

            $cm = get_coursemodule_from_instance('casestudy', $casestudyid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                $stats->unmatchedactivities[$activityname] = true;
                continue;
            }
            $contextid = \context_module::instance($cm->id)->id;

            // Pair source submissions to target submissions within this (activity, user). Prefer
            // matching by attempt number (stable across restore, no equal-count assumption); fall
            // back to positional id-order pairing for legacy manifests with no attempt column.
            $targetsubs = $DB->get_records(
                'casestudy_submissions',
                ['casestudyid' => $casestudyid, 'userid' => $userid],
                'id ASC',
                'id, attempt'
            );
            $oldtonew = self::pair_submissions(
                $rows['submissions'], $targetsubs, $stats, $activityname, $email
            );
            if ($oldtonew === null) {
                continue;
            }

            // Resolve this activity's file fields by shortname (cached).
            $fields = self::cache($fieldcache, $casestudyid, function() use ($DB, $casestudyid) {
                $map = [];
                foreach ($DB->get_records('casestudy_fields',
                        ['casestudyid' => $casestudyid, 'type' => 'file'], '', 'id, shortname') as $f) {
                    $map[$f->shortname] = (int) $f->id;
                }
                return $map;
            });

            // 3. Place each file.
            foreach ($rows['files'] as $row) {
                $stats->files++;
                $newsubid = $oldtonew[$row['old_submissionid']] ?? 0;
                $fieldid = $fields[$row['field']] ?? 0;
                $local = $byhash[$row['contenthash']] ?? null;

                if (!$newsubid || !$fieldid) {
                    $stats->skippednomap++;
                    continue;
                }
                if ($local === null) {
                    $stats->missinglocal[] = $row['contenthash'] . ' (' . $row['filename'] . ')';
                    continue;
                }

                $filearea = 'field_' . $fieldid;
                $existing = $fs->get_file($contextid, 'mod_casestudy', $filearea, $newsubid, '/', $row['filename']);
                if ($existing) {
                    // Only treat a same-named file as correct when its bytes match the manifest hash.
                    // A differing file (partial run, restore, manual upload) is a conflict, not a skip:
                    // silently leaving it would defeat the content-hash integrity guarantee.
                    if ($existing->get_contenthash() === $row['contenthash']) {
                        $stats->alreadypresent++;
                    } else {
                        $stats->conflicts[] = sprintf('%s/%s @sub %d', $filearea, $row['filename'], $newsubid);
                    }
                    continue;
                }

                if ($commit) {
                    // Make sure the field registers for this submission.
                    if (!$DB->record_exists('casestudy_content', ['submissionid' => $newsubid, 'fieldid' => $fieldid])) {
                        $DB->insert_record('casestudy_content', (object) [
                            'submissionid' => $newsubid,
                            'fieldid' => $fieldid,
                            // File fields store a draft item id here, never an empty string: the
                            // display path treats empty content as "no value" (file_field::render_display
                            // returns '-' and submission.php sets hasvalue=false), which would hide the
                            // imported files. Mirror a real save by storing a non-empty draft item id.
                            'content' => (string) file_get_unused_draft_itemid(),
                            'contentformat' => FORMAT_HTML,
                        ]);
                    }
                    $fs->create_file_from_pathname([
                        'contextid' => $contextid,
                        'component' => 'mod_casestudy',
                        'filearea' => $filearea,
                        'itemid' => $newsubid,
                        'filepath' => '/',
                        'filename' => $row['filename'],
                    ], $local);
                }
                $stats->written++;
            }

            if ((++$stats->groups % 25) === 0) {
                $log(sprintf('  ... %d groups processed, %d files written', $stats->groups, $stats->written));
            }
        }

        $stats->unmatchedusercount = count($stats->unmatchedusers);
        $stats->ambiguoususercount = count($stats->ambiguoususers);
        $stats->unmatchedactivitycount = count($stats->unmatchedactivities);
        return $stats;
    }

    /**
     * Build a content-hash => absolute-path index of a directory tree.
     *
     * @param string $dir Directory to scan
     * @param callable $log Logger
     * @return array sha1 => path
     */
    protected static function index_by_contenthash(string $dir, callable $log): array {
        $index = [];
        if (!is_dir($dir)) {
            return $index;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        $count = 0;
        foreach ($iterator as $fileinfo) {
            if (!$fileinfo->isFile()) {
                continue;
            }
            $hash = sha1_file($fileinfo->getPathname());
            if ($hash !== false) {
                // Keep the first path seen for a given hash; duplicates are identical content.
                $index[$hash] = $index[$hash] ?? $fileinfo->getPathname();
            }
            if ((++$count % 1000) === 0) {
                $log(sprintf('  ... hashed %d files', $count));
            }
        }
        return $index;
    }

    /**
     * Map source submission ids to target submission ids for one (activity, student).
     *
     * Preferred: match by attempt number. The attempt survives backup/restore unchanged, so this
     * needs no equal-count assumption — a source submission whose attempt is absent from the target
     * is left unmapped (its files are reported as skippednomap), and image-less submissions that
     * never appear in the manifest are simply irrelevant. Used whenever the manifest carries an
     * attempt for every source submission in the group and the target attempts are unique.
     *
     * Fallback (legacy manifests with no attempt column): pair by id order, which requires equal
     * counts; a mismatch is recorded and the group skipped so files are never placed on a guessed
     * pairing.
     *
     * @param array $oldsubs [old submission id => attempt int|null] from the manifest.
     * @param array $targetsubs [target id => record with ->id, ->attempt] ordered by id.
     * @param \stdClass $stats Import stats (mutated to record any mismatch).
     * @param string $activityname For mismatch messages.
     * @param string $email For mismatch messages.
     * @return array|null [old id => new id], or null if the group cannot be paired safely.
     */
    protected static function pair_submissions(array $oldsubs, array $targetsubs, \stdClass $stats,
            string $activityname, string $email): ?array {
        // Attempt-based matching, when the manifest carries an attempt for every source submission
        // and the target attempts are unique (they always are within one activity+user chain).
        $haveattempts = $oldsubs !== [] && !in_array(null, $oldsubs, true);
        $byattempt = [];
        $targetunique = true;
        foreach ($targetsubs as $t) {
            $att = (int) $t->attempt;
            if (isset($byattempt[$att])) {
                $targetunique = false;
                break;
            }
            $byattempt[$att] = (int) $t->id;
        }

        if ($haveattempts && $targetunique) {
            $map = [];
            foreach ($oldsubs as $oldid => $attempt) {
                if (isset($byattempt[(int) $attempt])) {
                    $map[$oldid] = $byattempt[(int) $attempt];
                }
                // No target with this attempt: leave unmapped; the file loop records skippednomap.
            }
            return $map;
        }

        // Positional fallback: only safe when the counts match exactly.
        $oldids = array_keys($oldsubs);
        sort($oldids, SORT_NUMERIC);
        $targetids = array_keys($targetsubs);
        if (count($oldids) !== count($targetids)) {
            $stats->submissionmismatch[] = sprintf(
                '%s / %s: source %d vs target %d submissions (no attempt data to disambiguate)',
                $activityname, $email, count($oldids), count($targetids)
            );
            return null;
        }
        $map = [];
        foreach ($oldids as $i => $oldid) {
            $map[$oldid] = $targetids[$i];
        }
        return $map;
    }

    /**
     * Read the manifest CSV and group rows by (activity name, email).
     *
     * @param string $path CSV path (first row is the header)
     * @return array key "name\0email" => ['files' => rows[], 'submissions' => [oldsubid => attempt|null]]
     */
    protected static function read_manifest_grouped(string $path): array {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \moodle_exception('error', 'mod_casestudy', '', null, "Cannot open manifest: {$path}");
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new \moodle_exception('error', 'mod_casestudy', '', null, 'Manifest is empty.');
        }
        $col = array_flip(array_map('trim', $header));
        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!isset($col[$required])) {
                fclose($handle);
                throw new \moodle_exception('error', 'mod_casestudy', '', null,
                    "Manifest is missing required column '{$required}'.");
            }
        }

        $groups = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < count($header)) {
                continue;
            }
            $row = [
                'casestudy' => $data[$col['casestudy']],
                'field' => trim($data[$col['field']]),
                'old_submissionid' => (int) $data[$col['old_submissionid']],
                'email' => trim($data[$col['email']]),
                'filename' => $data[$col['filename']],
                'contenthash' => trim($data[$col['contenthash']]),
            ];
            // Capture the source attempt when the manifest provides it, so import can match
            // submissions by attempt (robust) rather than positionally. Absent/blank => null,
            // which makes pair_submissions() fall back to positional matching for this group.
            $attempt = null;
            if (isset($col['old_attempt'], $data[$col['old_attempt']])
                    && trim($data[$col['old_attempt']]) !== '') {
                $attempt = (int) $data[$col['old_attempt']];
            }

            $key = $row['casestudy'] . "\0" . $row['email'];
            $groups[$key]['files'][] = $row;
            // Keep a non-null attempt if any row for this submission carries one.
            $oldsubid = $row['old_submissionid'];
            if (!array_key_exists($oldsubid, $groups[$key]['submissions'])
                    || $groups[$key]['submissions'][$oldsubid] === null) {
                $groups[$key]['submissions'][$oldsubid] = $attempt;
            }
        }
        fclose($handle);
        return $groups;
    }

    /**
     * Total file rows across all groups.
     *
     * @param array $groups Grouped manifest
     * @return int
     */
    protected static function count_rows(array $groups): int {
        $total = 0;
        foreach ($groups as $group) {
            $total += count($group['files']);
        }
        return $total;
    }

    /**
     * Tiny memoisation helper.
     *
     * @param array $cache In/out cache
     * @param string $key Cache key
     * @param callable $resolver Producer called on miss
     * @return mixed
     */
    protected static function cache(array &$cache, string $key, callable $resolver) {
        if (!array_key_exists($key, $cache)) {
            $cache[$key] = $resolver();
        }
        return $cache[$key];
    }

    /**
     * Build an empty stats object.
     *
     * @return \stdClass
     */
    protected static function new_stats(): \stdClass {
        return (object) [
            'rows' => 0,
            'groups' => 0,
            'files' => 0,
            'written' => 0,
            'alreadypresent' => 0,
            'skippednomap' => 0,
            'missinglocal' => [],
            'submissionmismatch' => [],
            'conflicts' => [],
            'unmatchedusers' => [],
            'ambiguoususers' => [],
            'unmatchedactivities' => [],
            'unmatchedusercount' => 0,
            'ambiguoususercount' => 0,
            'unmatchedactivitycount' => 0,
        ];
    }
}
