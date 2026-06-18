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

declare(strict_types=1);

namespace mod_casestudy\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the casestudy activity.
 *
 * Class for defining mod_casestudy's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given casestudy instance and a user.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $userid = $this->userid;
        $casestudyid = $this->cm->instance;

        if (!$casestudy = $DB->get_record('casestudy', ['id' => $casestudyid])) {
            throw new \moodle_exception('Unable to find casestudy with id ' . $casestudyid);
        }

        // Handle total satisfactory rule.
        if ($rule == 'completionsatisfactory') {
            $totalrule = $DB->get_record('casestudy_completion_rules',
                ['casestudyid' => $casestudy->id, 'enabled' => 1, 'ruletype' => CASESTUDY_COMPLETION_TOTAL]);

            if (!$totalrule) {
                return COMPLETION_INCOMPLETE;
            }

            // Count total satisfactory submissions for this user.
            $count = $DB->count_records_sql("
                SELECT COUNT(DISTINCT s.id)
                FROM {casestudy_submissions} s
                WHERE s.casestudyid = :casestudyid
                  AND s.userid = :userid
                  AND s.status = :status",
                [
                    'casestudyid' => $casestudy->id,
                    'userid' => $userid,
                    'status' => CASESTUDY_STATUS_SATISFACTORY
                ]
            );

            return ($count >= $totalrule->count) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        // Handle category completion - check all enabled category rules with aggregation.
        if ($rule == 'completioncategory') {
            // Get all category completion rules.
            $categoryrules = $DB->get_records('casestudy_completion_rules',
                ['casestudyid' => $casestudy->id, 'enabled' => 1, 'ruletype' => CASESTUDY_COMPLETION_CATEGORY],
                'sortorder ASC');

            if (empty($categoryrules)) {
                return COMPLETION_INCOMPLETE;
            }

            // Determine aggregation mode (ALL or ANY).
            $aggregation = isset($casestudy->completionaggr) ? $casestudy->completionaggr : CASESTUDY_COMPLETION_ALL;

            $results = [];

            foreach ($categoryrules as $completionrule) {
                $fieldid = $completionrule->fieldid;
                $categoryvalueindex = $completionrule->categoryvalue;
                $requiredcount = $completionrule->count;

                $actualvalue = null;
                if (!empty($categoryvalueindex)) {
                    $fields = $DB->get_records('casestudy_fields',
                        ['casestudyid' => $casestudy->id, 'category' => 1], 'sortorder ASC', 'id, param1');

                    $optionindex = 1;
                    foreach ($fields as $field) {
                        $values = $field->param1 ? json_decode($field->param1, true) : [];
                        if (is_array($values)) {
                            foreach ($values as $v) {
                                if ($optionindex == $categoryvalueindex && $field->id == $fieldid) {
                                    $actualvalue = $v;
                                    break 2;
                                }
                                $optionindex++;
                            }
                        }
                    }
                }

                if (!empty($actualvalue)) {
                    $contentwhere = 'AND c.content = :content';
                    $params = [
                        'casestudyid' => $casestudy->id,
                        'userid' => $userid,
                        'status' => CASESTUDY_STATUS_SATISFACTORY,
                        'fieldid' => $fieldid,
                        'content' => $actualvalue,
                    ];
                } else {
                    $contentwhere = 'AND c.content IS NOT NULL AND c.content != \'\'';
                    $params = [
                        'casestudyid' => $casestudy->id,
                        'userid' => $userid,
                        'status' => CASESTUDY_STATUS_SATISFACTORY,
                        'fieldid' => $fieldid,
                    ];
                }

                // Count satisfactory submissions matching this category rule.
                $count = $DB->count_records_sql("
                    SELECT COUNT(DISTINCT s.id)
                    FROM {casestudy_submissions} s
                    JOIN {casestudy_content} c ON s.id = c.submissionid
                    WHERE s.casestudyid = :casestudyid
                      AND s.userid = :userid
                      AND s.status = :status
                      AND c.fieldid = :fieldid
                      $contentwhere",
                    $params
                );
                // Check if this specific rule is met.
                $results[] = ($count >= $requiredcount);
            }

            // Apply aggregation logic.
            if ($aggregation == CASESTUDY_COMPLETION_ALL) {
                return !in_array(false, $results, true) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
            } else {
                return in_array(true, $results, true) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
            }
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsatisfactory', 'completioncategory'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $casestudyid = $this->cm->instance;
        $descriptions = [];

        // Get completion rules from the new table.
        $rules = $DB->get_records('casestudy_completion_rules',
            ['casestudyid' => $casestudyid, 'enabled' => 1], 'sortorder ASC');

        if (empty($rules)) {
            return $descriptions;
        }

        // Get aggregation mode first
        $casestudy = $DB->get_record('casestudy', ['id' => $casestudyid]);
        $aggregation = isset($casestudy->completionaggr) ? $casestudy->completionaggr : CASESTUDY_COMPLETION_ALL;

        // Build description for total satisfactory rule only if it exists and is enabled.
        foreach ($rules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_TOTAL) {
                $descriptions['completionsatisfactory'] = get_string(
                    'completiondetail:satisfactorysubmissions',
                    'mod_casestudy',
                    $rule->count
                );
                break;
            }
        }

        // Count category rules and add simple indication if any exist.
        $categorycount = 0;
        foreach ($rules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY) {
                $categorycount++;
            }
        }
        if ($categorycount > 0) {
            $descriptions['completioncategory'] = get_string(
                'completiondetail:categoryrules',
                'mod_casestudy',
                $categorycount
            );
        }

        return $descriptions;
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionsatisfactory',
            'completioncategory',
        ];
    }
}
