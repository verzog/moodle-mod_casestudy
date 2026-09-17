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
 * Per-user submission statistics for the staff Reports view.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local;

/**
 * Compute per-user submission counts, both per attempt and per case (resubmission chain).
 *
 * An "attempt" is a single row in casestudy_submissions. A "case" is a resubmission chain,
 * collapsed to its root via parentid; a case's standing is taken from its latest attempt.
 */
class report_stats {
    /**
     * Compute both attempt-based and case-based counts for one user in one activity.
     *
     * The returned object exposes, for each of submitted/unmarked/marked/satisfactory/
     * unsatisfactory, an "*attempts" and a "*cases" property. By construction
     * submitted = marked + unmarked and marked = satisfactory + unsatisfactory, for both bases.
     *
     * @param int $casestudyid Case study instance id.
     * @param int $userid User id.
     * @return \stdClass Counts keyed as submittedattempts, submittedcases, etc.
     */
    public static function for_user(int $casestudyid, int $userid): \stdClass {
        global $DB;

        $subs = $DB->get_records(
            'casestudy_submissions',
            ['casestudyid' => $casestudyid, 'userid' => $userid],
            '',
            'id, parentid, status, timecreated'
        );

        $notsubmitted = [CASESTUDY_STATUS_NEW, CASESTUDY_STATUS_DRAFT];
        $final = [CASESTUDY_STATUS_SATISFACTORY, CASESTUDY_STATUS_UNSATISFACTORY];

        // Attempt-based counts: one row = one attempt.
        $submittedattempts = 0;
        $satisfactoryattempts = 0;
        $unsatisfactoryattempts = 0;

        // Build the parent map and, per root case, remember the latest attempt.
        $parentmap = [];
        foreach ($subs as $s) {
            $parentmap[(int) $s->id] = (int) $s->parentid;
        }

        $latestbyroot = [];
        foreach ($subs as $s) {
            $status = $s->status;
            if (!in_array($status, $notsubmitted, true)) {
                $submittedattempts++;
            }
            if ($status === CASESTUDY_STATUS_SATISFACTORY) {
                $satisfactoryattempts++;
            } else if ($status === CASESTUDY_STATUS_UNSATISFACTORY) {
                $unsatisfactoryattempts++;
            }

            $root = self::root_id($parentmap, (int) $s->id);
            if (!isset($latestbyroot[$root]) || (int) $s->timecreated >= $latestbyroot[$root]->timecreated) {
                $latestbyroot[$root] = (object) [
                    'timecreated' => (int) $s->timecreated,
                    'status' => $status,
                    'anysubmitted' => false,
                ];
            }
        }

        // A case counts as "submitted" if any attempt in its chain was submitted.
        foreach ($subs as $s) {
            if (!in_array($s->status, $notsubmitted, true)) {
                $root = self::root_id($parentmap, (int) $s->id);
                if (isset($latestbyroot[$root])) {
                    $latestbyroot[$root]->anysubmitted = true;
                }
            }
        }

        $submittedcases = 0;
        $satisfactorycases = 0;
        $unsatisfactorycases = 0;
        foreach ($latestbyroot as $case) {
            if ($case->anysubmitted) {
                $submittedcases++;
            }
            if ($case->status === CASESTUDY_STATUS_SATISFACTORY) {
                $satisfactorycases++;
            } else if ($case->status === CASESTUDY_STATUS_UNSATISFACTORY) {
                $unsatisfactorycases++;
            }
        }

        $markedattempts = $satisfactoryattempts + $unsatisfactoryattempts;
        $markedcases = $satisfactorycases + $unsatisfactorycases;

        return (object) [
            'submittedattempts' => $submittedattempts,
            'unmarkedattempts' => max(0, $submittedattempts - $markedattempts),
            'markedattempts' => $markedattempts,
            'satisfactoryattempts' => $satisfactoryattempts,
            'unsatisfactoryattempts' => $unsatisfactoryattempts,
            'submittedcases' => $submittedcases,
            'unmarkedcases' => max(0, $submittedcases - $markedcases),
            'markedcases' => $markedcases,
            'satisfactorycases' => $satisfactorycases,
            'unsatisfactorycases' => $unsatisfactorycases,
        ];
    }

    /**
     * Resolve a submission id to the root of its resubmission chain.
     *
     * @param int[] $parentmap Submission id => immediate parent id (0 for a root).
     * @param int $id Submission id to resolve.
     * @return int Root submission (case) id.
     */
    private static function root_id(array $parentmap, int $id): int {
        $seen = [];
        $current = $id;
        while (!empty($parentmap[$current]) && empty($seen[$current])) {
            $seen[$current] = true;
            $current = $parentmap[$current];
        }
        return $current;
    }
}
