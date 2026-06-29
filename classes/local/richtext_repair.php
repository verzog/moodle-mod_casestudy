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
     * Regex matching an absolute submission_richtext pluginfile URL prefix for one submission.
     *
     * Handles the slash form and the ?file= form with raw or %2F-encoded separators, and is
     * scoped to a specific submission id so a (rare) link to another submission is never touched.
     *
     * @param int $submissionid Submission id the URL must target.
     * @return string PCRE pattern.
     */
    protected static function url_pattern(int $submissionid): string {
        return '~https?://[^"\'\s<>]+?/pluginfile\.php(?:\?file=)?(?:/|%2F)\d+'
            . '(?:/|%2F)mod_casestudy(?:/|%2F)submission_richtext(?:/|%2F)' . $submissionid . '(?:/|%2F)~i';
    }

    /**
     * Fetch the rich-text content rows in scope.
     *
     * @param int $cmid Course module id, or 0 for the whole site.
     * @return array Records with id, content, submissionid.
     */
    protected static function get_rows(int $cmid): array {
        global $DB;

        $like = '(' . $DB->sql_like('content', ':needle1') . ' OR ' . $DB->sql_like('content', ':needle2') . ')';
        $params = ['needle1' => '%submission_richtext%', 'needle2' => '%@@PLUGINFILE@@%'];

        if ($cmid) {
            $cm = get_coursemodule_from_id('casestudy', $cmid, 0, false, MUST_EXIST);
            $like = '(' . $DB->sql_like('cc.content', ':needle1') . ' OR ' . $DB->sql_like('cc.content', ':needle2') . ')';
            $sql = "SELECT cc.id, cc.content, cc.submissionid
                      FROM {casestudy_content} cc
                      JOIN {casestudy_submissions} cs ON cs.id = cc.submissionid
                     WHERE cs.casestudyid = :instance AND $like";
            $params['instance'] = $cm->instance;
        } else {
            $sql = "SELECT id, content, submissionid FROM {casestudy_content} WHERE $like";
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Rewrite absolute submission_richtext URLs in stored content to @@PLUGINFILE@@.
     *
     * Idempotent and safe to re-run.
     *
     * @param int $cmid Course module id, or 0 for the whole site.
     * @param bool $apply False to report only; true to write the changes.
     * @return \stdClass Counts: ->scanned, ->rows, ->urls.
     */
    public static function normalise(int $cmid, bool $apply): \stdClass {
        global $DB;

        $result = (object) ['scanned' => 0, 'rows' => 0, 'urls' => 0];

        foreach (self::get_rows($cmid) as $row) {
            $result->scanned++;
            $subid = (int) $row->submissionid;
            if ($subid <= 0 || $row->content === null || $row->content === '') {
                continue;
            }

            $count = 0;
            $updated = preg_replace(self::url_pattern($subid), '@@PLUGINFILE@@/', $row->content, -1, $count);
            if ($updated !== null && $count > 0 && $updated !== $row->content) {
                $result->rows++;
                $result->urls += $count;
                if ($apply) {
                    $DB->set_field('casestudy_content', 'content', $updated, ['id' => $row->id]);
                }
            }
        }

        return $result;
    }

    /**
     * List each referenced image and whether its file exists in storage.
     *
     * @param int $cmid Course module id (required).
     * @return array List of objects: ->submissionid, ->filename, ->present.
     */
    public static function diagnose(int $cmid): array {
        $cm = get_coursemodule_from_id('casestudy', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();

        $report = [];
        foreach (self::get_rows($cmid) as $row) {
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
                    'present' => $fs->file_exists($context->id, 'mod_casestudy', 'submission_richtext',
                        (int) $row->submissionid, '/', $filename),
                ];
            }
        }

        return $report;
    }
}
