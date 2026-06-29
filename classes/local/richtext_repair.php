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
 * Helpers to diagnose and repair rich-text image URLs in submission content.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Diagnose and repair absolute rich-text pluginfile URLs in stored submissions.
 *
 * Rich-text content can embed absolute pluginfile URLs (e.g. images inserted as
 * file links, or content carried through a course restore) that 404 because they
 * keep the original context/submission id. These helpers rewrite such URLs to the
 * @@PLUGINFILE@@ placeholder and report whether the referenced files exist.
 */
class richtext_repair {

    /**
     * Matches an absolute submission_richtext pluginfile URL prefix, capturing the context id.
     *
     * Handles the slash form and the ?file= form with raw or %2F-encoded separators.
     */
    const URL_PATTERN = '~https?://[^"\'\s<>]+?/pluginfile\.php(?:\?file=)?(?:/|%2F)(\d+)'
        . '(?:/|%2F)mod_casestudy(?:/|%2F)submission_richtext(?:/|%2F)\d+(?:/|%2F)~i';

    /**
     * Open a recordset of rich-text content rows in scope. Caller must close it.
     *
     * @param int $cmid Course module id, or 0 for the whole site.
     * @return \moodle_recordset Rows with id, content, submissionid, casestudyid.
     */
    protected static function get_recordset(int $cmid): \moodle_recordset {
        global $DB;

        $like = '(' . $DB->sql_like('cc.content', ':needle1') . ' OR ' . $DB->sql_like('cc.content', ':needle2') . ')';
        $params = ['needle1' => '%submission_richtext%', 'needle2' => '%@@PLUGINFILE@@%'];

        $sql = "SELECT cc.id, cc.content, cc.submissionid, cs.casestudyid
                  FROM {casestudy_content} cc
                  JOIN {casestudy_submissions} cs ON cs.id = cc.submissionid";
        if ($cmid) {
            $cm = get_coursemodule_from_id('casestudy', $cmid, 0, false, MUST_EXIST);
            $sql .= " WHERE cs.casestudyid = :instance AND $like";
            $params['instance'] = $cm->instance;
        } else {
            $sql .= " WHERE $like";
        }

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Resolve (and cache) the current module context id for a case study instance.
     *
     * @param int $casestudyid Case study instance id.
     * @param array $cache Context cache keyed by instance id (by reference).
     * @return int|null Context id, or null when the module can't be resolved.
     */
    protected static function context_id_for_instance(int $casestudyid, array &$cache): ?int {
        if (array_key_exists($casestudyid, $cache)) {
            return $cache[$casestudyid];
        }
        $cm = get_coursemodule_from_instance('casestudy', $casestudyid);
        $cache[$casestudyid] = $cm ? (int) \context_module::instance($cm->id)->id : null;
        return $cache[$casestudyid];
    }

    /**
     * Rewrite STALE absolute submission_richtext URLs (those carrying a different context to the
     * current module) to @@PLUGINFILE@@. URLs already in the current context are left untouched,
     * preserving legitimate links to other submissions/attempts in the same activity.
     *
     * @param string $content Stored content.
     * @param int $currentcontextid The submission's current module context id.
     * @param int $count Number of replacements made (by reference).
     * @return string Rewritten content.
     */
    protected static function retarget_stale_urls(string $content, int $currentcontextid, int &$count): string {
        $changed = 0;
        $result = preg_replace_callback(
            self::URL_PATTERN,
            function ($matches) use ($currentcontextid, &$changed) {
                if ((int) $matches[1] === $currentcontextid) {
                    // Current context — a legitimate link, leave it untouched.
                    return $matches[0];
                }
                $changed++;
                return '@@PLUGINFILE@@/';
            },
            $content
        );
        $count = $changed;
        return $result;
    }

    /**
     * Rewrite stale absolute submission_richtext URLs in stored content to @@PLUGINFILE@@.
     *
     * Streams rows so a whole-site run does not load every submission into memory. Idempotent
     * and safe to re-run.
     *
     * @param int $cmid Course module id, or 0 for the whole site.
     * @param bool $apply False to report only; true to write the changes.
     * @return \stdClass Counts: ->scanned, ->rows, ->urls.
     */
    public static function normalise(int $cmid, bool $apply): \stdClass {
        global $DB;

        $result = (object) ['scanned' => 0, 'rows' => 0, 'urls' => 0];
        $contextcache = [];

        $rs = self::get_recordset($cmid);
        foreach ($rs as $row) {
            $result->scanned++;
            if ($row->content === null || $row->content === '') {
                continue;
            }
            $currentcontextid = self::context_id_for_instance((int) $row->casestudyid, $contextcache);
            if ($currentcontextid === null) {
                continue;
            }

            $count = 0;
            $updated = self::retarget_stale_urls($row->content, $currentcontextid, $count);
            if ($updated !== null && $count > 0 && $updated !== $row->content) {
                $result->rows++;
                $result->urls += $count;
                if ($apply) {
                    $DB->set_field('casestudy_content', 'content', $updated, ['id' => $row->id]);
                }
            }
        }
        $rs->close();

        return $result;
    }

    /**
     * List each referenced image and whether its file exists in storage.
     *
     * File presence is checked against the submission's current id/context, so a stale itemid
     * still embedded in the URL does not matter here.
     *
     * @param int $cmid Course module id (required).
     * @return array List of objects: ->submissionid, ->filename, ->present.
     */
    public static function diagnose(int $cmid): array {
        $cm = get_coursemodule_from_id('casestudy', $cmid, 0, false, MUST_EXIST);
        $contextid = (int) \context_module::instance($cm->id)->id;
        $fs = get_file_storage();

        $report = [];
        $rs = self::get_recordset($cmid);
        foreach ($rs as $row) {
            // Filenames after .../submission_richtext/<id>/ (slash or %2F) or after @@PLUGINFILE@@/.
            preg_match_all(
                '~(?:submission_richtext(?:/|%2F)\d+(?:/|%2F)|@@PLUGINFILE@@/)([^"\'\s<>?]+)~i',
                $row->content,
                $matches
            );
            foreach (array_unique($matches[1] ?? []) as $raw) {
                $filename = rawurldecode($raw);
                $report[] = (object) [
                    'submissionid' => (int) $row->submissionid,
                    'filename' => $filename,
                    'present' => $fs->file_exists($contextid, 'mod_casestudy', 'submission_richtext',
                        (int) $row->submissionid, '/', $filename),
                ];
            }
        }
        $rs->close();

        return $report;
    }
}
