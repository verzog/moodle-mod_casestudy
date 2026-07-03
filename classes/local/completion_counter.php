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

namespace mod_casestudy\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Single source of truth for counting satisfactory cases toward completion.
 *
 * Both the completion engine (\mod_casestudy\completion\custom_completion) and the tutor-facing
 * dashboards (summary_table, renderer) count "satisfactory cases" against the same total and
 * category rules. Keeping that logic here guarantees the completion state and the progress shown
 * to tutors can never drift apart.
 *
 * Counting is per case, not per submission row: a case (a submission and its resubmission
 * attempts, linked by parentid) is counted at most once, so a case whose two attempts are both
 * satisfactory does not count twice.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
class completion_counter {
    /**
     * SQL expression that collapses a case's attempts to a single identity.
     *
     * A resubmission carries parentid = the original submission id; an original has parentid 0.
     * Grouping by "parent if set, else self" yields one identity per case.
     *
     * @return string SQL fragment referencing alias s (casestudy_submissions).
     */
    private static function case_expr(): string {
        return "CASE WHEN s.parentid > 0 THEN s.parentid ELSE s.id END";
    }

    /**
     * Count distinct satisfactory cases for a user (the "total satisfactory" rule).
     *
     * @param int $casestudyid Case study instance id.
     * @param int $userid User id.
     * @return int Number of distinct satisfactory cases.
     */
    public static function count_total(int $casestudyid, int $userid): int {
        global $DB;

        $expr = self::case_expr();
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT $expr)
               FROM {casestudy_submissions} s
              WHERE s.casestudyid = :casestudyid
                AND s.userid = :userid
                AND s.status = :status",
            [
                'casestudyid' => $casestudyid,
                'userid' => $userid,
                'status' => CASESTUDY_STATUS_SATISFACTORY,
            ]
        );
    }

    /**
     * Count distinct satisfactory cases matching a category rule for a user.
     *
     * @param int $casestudyid Case study instance id.
     * @param int $userid User id.
     * @param int $fieldid Field the category rule targets.
     * @param string|null $value Required option value, or null/'' to match any non-empty answer.
     * @return int Number of distinct satisfactory cases matching the rule.
     */
    public static function count_category(int $casestudyid, int $userid, int $fieldid, ?string $value): int {
        global $DB;

        $params = [
            'casestudyid' => $casestudyid,
            'userid' => $userid,
            'status' => CASESTUDY_STATUS_SATISFACTORY,
            'fieldid' => $fieldid,
        ];

        if ($value !== null && $value !== '') {
            $contentwhere = 'AND c.content = :content';
            $params['content'] = $value;
        } else {
            $contentwhere = "AND c.content IS NOT NULL AND c.content != ''";
        }

        $expr = self::case_expr();
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT $expr)
               FROM {casestudy_submissions} s
               JOIN {casestudy_content} c ON s.id = c.submissionid
              WHERE s.casestudyid = :casestudyid
                AND s.userid = :userid
                AND s.status = :status
                AND c.fieldid = :fieldid
                $contentwhere",
            $params
        );
    }

    /**
     * Resolve a completion rule's stored category value to an actual field option.
     *
     * A category rule stores categoryvalue as a 1-based running index across the options of all
     * category fields (category = 1), ordered by sortorder then the field's param1 option order.
     * This walks that same ordering to recover the option string the index refers to.
     *
     * @param int $casestudyid Case study instance id.
     * @param int $fieldid Field the rule targets.
     * @param int|null $index Stored categoryvalue index (null/0 means "any value").
     * @return string|null The option value, or null when the index resolves to nothing.
     */
    public static function resolve_category_value(int $casestudyid, int $fieldid, ?int $index): ?string {
        global $DB;

        if (empty($index)) {
            return null;
        }

        $fields = $DB->get_records(
            'casestudy_fields',
            ['casestudyid' => $casestudyid, 'category' => 1],
            'sortorder ASC',
            'id, param1'
        );

        $optionindex = 1;
        foreach ($fields as $field) {
            $values = $field->param1 ? json_decode($field->param1, true) : [];
            if (is_array($values)) {
                foreach ($values as $v) {
                    if ($optionindex == $index && $field->id == $fieldid) {
                        return $v;
                    }
                    $optionindex++;
                }
            }
        }

        return null;
    }
}
