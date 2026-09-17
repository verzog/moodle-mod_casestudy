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
     * Map of id => parentid for every submission by a user in an activity.
     *
     * Used to resolve each submission to the root of its resubmission chain. parentid points at
     * the immediate parent (a chain can be several rounds deep when maxattempts allows it), so the
     * root is found by walking, not by reading parentid directly.
     *
     * @param int $casestudyid Case study instance id.
     * @param int $userid User id.
     * @return int[] Submission id => immediate parent id (0 for a root).
     */
    private static function parent_map(int $casestudyid, int $userid): array {
        global $DB;

        $map = $DB->get_records_menu(
            'casestudy_submissions',
            ['casestudyid' => $casestudyid, 'userid' => $userid],
            '',
            'id, parentid'
        );

        $result = [];
        foreach ($map as $id => $parentid) {
            $result[(int) $id] = (int) $parentid;
        }
        return $result;
    }

    /**
     * Resolve a submission id to the root of its resubmission chain.
     *
     * @param int[] $parentmap Submission id => immediate parent id.
     * @param int $id Submission id to resolve.
     * @return int Root submission (case) id.
     */
    private static function root_id(array $parentmap, int $id): int {
        $seen = [];
        $current = $id;
        // Walk up to the top of the chain; $seen guards against any accidental cycle.
        while (!empty($parentmap[$current]) && empty($seen[$current])) {
            $seen[$current] = true;
            $current = $parentmap[$current];
        }
        return $current;
    }

    /**
     * Count the distinct root cases the given submission ids belong to.
     *
     * @param int[] $parentmap Submission id => immediate parent id.
     * @param int[] $ids Submission ids to collapse to their root cases.
     * @return int Number of distinct root cases.
     */
    private static function distinct_roots(array $parentmap, array $ids): int {
        $roots = [];
        foreach ($ids as $id) {
            $roots[self::root_id($parentmap, (int) $id)] = true;
        }
        return count($roots);
    }

    /**
     * Count distinct satisfactory cases for a user (the "total satisfactory" rule).
     *
     * @param int $casestudyid Case study instance id.
     * @param int $userid User id.
     * @return int Number of distinct satisfactory cases.
     */
    public static function count_total($casestudyid, $userid): int {
        global $DB;

        // Moodle's DB layer returns record fields as strings; cast so strict_types callers
        // (e.g. custom_completion) can pass $record->id straight through without a TypeError.
        $casestudyid = (int) $casestudyid;
        $userid = (int) $userid;

        // Fetch every submission once: build the parent map and collect satisfactory ids together.
        $subs = $DB->get_records(
            'casestudy_submissions',
            ['casestudyid' => $casestudyid, 'userid' => $userid],
            '',
            'id, parentid, status'
        );

        $parentmap = [];
        $satisfactory = [];
        foreach ($subs as $s) {
            $parentmap[(int) $s->id] = (int) $s->parentid;
            if ($s->status === CASESTUDY_STATUS_SATISFACTORY) {
                $satisfactory[] = (int) $s->id;
            }
        }

        return self::distinct_roots($parentmap, $satisfactory);
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
    public static function count_category($casestudyid, $userid, $fieldid, ?string $value): int {
        global $DB;

        // See count_total(): DB ids arrive as strings; cast for strict_types callers.
        $casestudyid = (int) $casestudyid;
        $userid = (int) $userid;
        $fieldid = (int) $fieldid;

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

        $ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT s.id
               FROM {casestudy_submissions} s
               JOIN {casestudy_content} c ON s.id = c.submissionid
              WHERE s.casestudyid = :casestudyid
                AND s.userid = :userid
                AND s.status = :status
                AND c.fieldid = :fieldid
                $contentwhere",
            $params
        );

        if (empty($ids)) {
            return 0;
        }

        return self::distinct_roots(self::parent_map($casestudyid, $userid), $ids);
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
    public static function resolve_category_value($casestudyid, $fieldid, $index): ?string {
        global $DB;

        // See count_total(): DB ids arrive as strings; cast for strict_types callers.
        $casestudyid = (int) $casestudyid;
        $fieldid = (int) $fieldid;
        $index = (int) $index;

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
