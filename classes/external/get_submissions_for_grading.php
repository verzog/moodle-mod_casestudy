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
 * External function for getting submissions for grading navigation
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_module;

/**
 * External function for getting submissions for grading navigation
 */
class get_submissions_for_grading extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'casestudyid' => new external_value(PARAM_INT, 'Case study instance id'),
            'cmid' => new external_value(PARAM_INT, 'Course module id')
        ]);
    }

    /**
     * Get submissions for grading navigation
     *
     * @param int $casestudyid Case study ID
     * @param int $cmid Course module ID
     * @return array Array of submissions
     */
    public static function execute($casestudyid, $cmid) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'casestudyid' => $casestudyid,
            'cmid' => $cmid
        ]);

        // Get context and validate permissions
        $cm = get_coursemodule_from_id('casestudy', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/casestudy:grade', $context);

        // Check for group filtering
        $groupid = groups_get_activity_group($cm, true);

        // Only show submissions that have been submitted (exclude new and draft statuses).
        $submittedstatuses = [
            CASESTUDY_STATUS_SUBMITTED,
            CASESTUDY_STATUS_IN_REVIEW,
            CASESTUDY_STATUS_AWAITING_RESUBMISSION,
            CASESTUDY_STATUS_RESUBMITTED,
            CASESTUDY_STATUS_RESUBMITTED_INREVIEW,
            CASESTUDY_STATUS_SATISFACTORY,
            CASESTUDY_STATUS_UNSATISFACTORY,
        ];
        list($statusinsql, $statusparams) = $DB->get_in_or_equal($submittedstatuses, SQL_PARAMS_NAMED, 'status');

        $sql = "SELECT s.id, s.userid, s.attempt, s.status, s.timesubmitted,
                       u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email
                  FROM {casestudy_submissions} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.casestudyid = :casestudyid
                   AND s.status $statusinsql";

        $sqlparams = array_merge(['casestudyid' => $params['casestudyid']], $statusparams);

        // Add group filter if needed
        if ($groupid) {
            $groupmembers = groups_get_members($groupid, 'u.id');
            if (!empty($groupmembers)) {
                list($insql, $inparams) = $DB->get_in_or_equal(array_keys($groupmembers), SQL_PARAMS_NAMED);
                $sql .= " AND s.userid $insql";
                $sqlparams = array_merge($sqlparams, $inparams);
            } else {
                return [];
            }
        }

        $sql .= " ORDER BY u.lastname ASC, u.firstname ASC, s.attempt DESC";

        $submissions = $DB->get_records_sql($sql, $sqlparams);

        $result = [];
        foreach ($submissions as $submission) {
            $result[] = [
                'id' => (int)$submission->id,
                'userid' => (int)$submission->userid,
                'fullname' => fullname($submission),
                'attempt' => (int)$submission->attempt,
                'status' => (int)$submission->status,
                'timesubmitted' => $submission->timesubmitted ? (int)$submission->timesubmitted : 0
            ];
        }

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Submission ID'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                'attempt' => new external_value(PARAM_INT, 'Attempt number'),
                'status' => new external_value(PARAM_INT, 'Submission status'),
                'timesubmitted' => new external_value(PARAM_INT, 'Time submitted')
            ])
        );
    }
}
