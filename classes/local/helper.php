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
 * Helper class for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
namespace mod_casestudy\local;

/**
 * Static utility helpers used across the plugin (record fetches, status maps).
 */
class helper {
    /**
     * Fetch a {casestudy} row with a small request-scoped cache.
     *
     * @param int $casestudyid Activity instance id, or 0 to use the current $PAGE->cm.
     * @return \stdClass
     */
    public static function get_casestudy(int $casestudyid) {
        global $DB, $PAGE;
        static $casestudy = null;

        if ($casestudy && $casestudy->id == $casestudyid) {
            return $casestudy;
        }

        if ($casestudyid) {
            $casestudy = $DB->get_record('casestudy', ['id' => $casestudyid], '*', MUST_EXIST);
        } else if ($PAGE->cm && $PAGE->cm->modname === 'casestudy') {
            $casestudy = $DB->get_record('casestudy', ['id' => $PAGE->cm->instance], '*', MUST_EXIST);
        } else {
            throw new \coding_exception('casestudyid must be provided if not on a casestudy activity page');
        }

        return $casestudy;
    }

    /**
     * Fetch a {casestudy_submissions} row by id.
     *
     * @param int $submissionid Submission id.
     * @return \stdClass|false
     */
    public static function get_submission(int $submissionid) {
        global $DB;
        static $submission = null;

        if ($submission && $submission->id == $submissionid) {
            return $submission;
        }

        if ($submissionid) {
            return $DB->get_record('casestudy_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        } else {
            throw new \coding_exception('submissionid must be provided to get_submission');
        }
    }

    /**
     * Get CSS class for submission status
     *
     * @param string $status Status string
     * @return string CSS class
     */
    public static function get_status_info($status, $type = '') {

        switch ($status) {
            case CASESTUDY_STATUS_NEW:
                $result = ['class' => 'secondary', 'statusclass' => 'badge-secondary', 'iconclass' => 'fa-plus' ];
                break;
            case CASESTUDY_STATUS_DRAFT:
                $result = ['class' => 'secondary', 'statusclass' => 'badge-secondary', 'iconclass' => 'fa-edit' ];
                break;
            case CASESTUDY_STATUS_SUBMITTED:
                $result = ['class' => 'primary', 'statusclass' => 'badge-primary', 'iconclass' => 'fa-paper-plane'];
                break;
            case CASESTUDY_STATUS_IN_REVIEW:
                $result = ['class' => 'info', 'statusclass' => 'badge-warning', 'iconclass' => 'fa-search'];
                break;
            case CASESTUDY_STATUS_AWAITING_RESUBMISSION:
                $result = ['class' => 'warning', 'statusclass' => 'badge-info', 'iconclass' => 'fa-clock'];
                break;
            case CASESTUDY_STATUS_RESUBMITTED:
                $result = ['class' => 'primary', 'statusclass' => 'badge-primary', 'iconclass' => 'fa-redo'];
                break;
            case CASESTUDY_STATUS_RESUBMITTED_INREVIEW:
                $result = ['class' => 'info', 'statusclass' => 'badge-warning', 'iconclass' => 'fa-search'];
                break;
            case CASESTUDY_STATUS_SATISFACTORY:
                $result = ['class' => 'success', 'statusclass' => 'badge-success', 'iconclass' => 'fa-check'];
                break;
            case CASESTUDY_STATUS_UNSATISFACTORY:
                $result = ['class' => 'danger', 'statusclass' => 'badge-danger', 'iconclass' => 'fa-times'];
                break;
            default:
                $result = ['class' => 'secondary', 'statusclass' => 'badge-light', 'iconclass' => 'fa-question'];
        }

        return $type ? $result[$type] : $result;
    }

    /**
     * Return the full map of submission statuses to localised display labels.
     *
     * @return array Map of CASESTUDY_STATUS_* constant => label.
     */
    public static function get_status_list() {
        return [
            CASESTUDY_STATUS_NEW => get_string('status_new', 'mod_casestudy'),
            CASESTUDY_STATUS_DRAFT => get_string('status_draft', 'mod_casestudy'),
            CASESTUDY_STATUS_SUBMITTED => get_string('status_submitted', 'mod_casestudy'),
            CASESTUDY_STATUS_IN_REVIEW => get_string('status_in_review', 'mod_casestudy'),
            CASESTUDY_STATUS_AWAITING_RESUBMISSION => get_string('status_awaiting_resubmission', 'mod_casestudy'),
            CASESTUDY_STATUS_RESUBMITTED => get_string('status_resubmitted', 'mod_casestudy'),
            CASESTUDY_STATUS_RESUBMITTED_INREVIEW => get_string('status_resubmitted_inreview', 'mod_casestudy'),
            CASESTUDY_STATUS_SATISFACTORY => get_string('status_satisfactory', 'mod_casestudy'),
            CASESTUDY_STATUS_UNSATISFACTORY => get_string('status_unsatisfactory', 'mod_casestudy'),
        ];
    }

    /**
     * Format grader feedback for display, resolving embedded image URLs.
     *
     * Grader feedback is an editor field whose inline images live in the 'feedback' filearea
     * keyed by the grade id. The stored HTML should keep @@PLUGINFILE@@ placeholders, but:
     *  - feedback saved before save_feedback() tokenised the text kept the editor's draft URLs
     *    (draftfile.php/.../user/draft/<id>/), which die once the session draft is cleaned up
     *    and are never backed up; and
     *  - a course restore can leave absolute pluginfile URLs that carry the source site's host
     *    and/or context id, which 404 against this site.
     * In every case the image fails to render without rewriting. This mirrors
     * richtext_field::display(): retarget any stale feedback URL (draft, or a pluginfile URL on a
     * different site/context) to @@PLUGINFILE@@, then rewrite the placeholders to real pluginfile
     * URLs in this context before formatting. Used by both the submission-history renderer and the
     * single-view template's [[feedback]] tag so every feedback display path behaves the same.
     *
     * @param \stdClass $grade Grade record (provides ->id, ->feedback, ->feedbackformat).
     * @param \context|null $context Module context; when absent, feedback is formatted as-is.
     * @return string Formatted feedback HTML.
     */
    public static function format_feedback_text($grade, $context) {
        global $CFG;

        $text = (string) ($grade->feedback ?? '');
        $format = $grade->feedbackformat ?? FORMAT_HTML;

        if ($context === null || $text === '') {
            return format_text($text, $format);
        }

        $contextid = (int) $context->id;

        // Editor draft URLs are always stale in stored content, so retarget every one to the
        // @@PLUGINFILE@@ placeholder — the trailing filename is kept and resolves against the
        // feedback files below. Both the slash form and the ?file= form (raw or %2F-encoded)
        // are handled.
        $text = preg_replace(
            '~https?://[^"\'\s<>]+?/draftfile\.php(?:\?file=)?(?:/|%2F)\d+(?:/|%2F)user'
                . '(?:/|%2F)draft(?:/|%2F)\d+(?:/|%2F)~i',
            '@@PLUGINFILE@@/',
            $text
        );

        // Retarget stale absolute feedback pluginfile URLs to @@PLUGINFILE@@. A URL is left
        // untouched only when it is a genuine link to this submission's feedback on this site:
        // it must sit under this site's wwwroot AND carry this module's current context id.
        // Anything else — a different host, or a different (e.g. pre-restore) context id that may
        // collide numerically with this one — is stale and retargeted so file_rewrite_pluginfile_urls()
        // can resolve it against the restored files.
        $wwwroot = rtrim($CFG->wwwroot, '/');
        $text = preg_replace_callback(
            '~https?://[^"\'\s<>]+?/pluginfile\.php(?:\?file=)?(?:/|%2F)(\d+)'
                . '(?:/|%2F)mod_casestudy(?:/|%2F)feedback(?:/|%2F)\d+(?:/|%2F)~i',
            function ($matches) use ($contextid, $wwwroot) {
                $samesite = strpos($matches[0], $wwwroot . '/') === 0;
                return ($samesite && (int) $matches[1] === $contextid) ? $matches[0] : '@@PLUGINFILE@@/';
            },
            $text
        );

        $text = file_rewrite_pluginfile_urls(
            $text,
            'pluginfile.php',
            $contextid,
            'mod_casestudy',
            'feedback',
            $grade->id
        );

        return format_text($text, $format, ['context' => $context]);
    }
}
