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
 * Scheduled task to send learner progress reports
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to send learner progress reports weekly
 */
class send_learner_reports extends \core\task\scheduled_task {
    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('tasklearnerreport', 'mod_casestudy');
    }

    /**
     * Execute the task
     */
    public function execute() {
        global $DB, $CFG;

        // Load constants from lib.php
        require_once($CFG->dirroot . '/mod/casestudy/lib.php');

        mtrace('Starting weekly learner progress reports...');

        // Get all case study activities that have completion criteria enabled.
        $sql = "SELECT DISTINCT c.*
                  FROM {casestudy} c
                  JOIN {casestudy_completion_rules} cr ON cr.casestudyid = c.id
                 WHERE cr.enabled = 1";

        $casestudies = $DB->get_records_sql($sql);

        if (empty($casestudies)) {
            mtrace('No case studies with completion criteria found.');
            return;
        }

        $totalreports = 0;

        foreach ($casestudies as $casestudy) {
            mtrace("Processing case study: {$casestudy->name} (ID: {$casestudy->id})");

            // Get course module and course.
            $cm = get_coursemodule_from_instance('casestudy', $casestudy->id);
            if (!$cm) {
                mtrace("  ERROR: Course module not found.");
                continue;
            }

            $course = $DB->get_record('course', ['id' => $cm->course]);
            if (!$course) {
                mtrace("  ERROR: Course not found.");
                continue;
            }

            $context = \context_module::instance($cm->id);

            // Get all enrolled students (users with submit capability).
            $students = get_users_by_capability($context, 'mod/casestudy:submit', 'u.*', 'u.lastname, u.firstname', '', '', '', '', false);

            if (empty($students)) {
                mtrace("  No students found.");
                continue;
            }

            mtrace("  Found " . count($students) . " student(s).");

            $sent = 0;
            $failed = 0;

            foreach ($students as $student) {
                // Send learner report.
                try {
                    $result = \mod_casestudy\notification_helper::send_learner_report($casestudy, $student, $cm, $course);

                    if ($result) {
                        $sent++;
                    } else {
                        $failed++;
                        mtrace("    FAILED: Could not send report to {$student->firstname} {$student->lastname} ({$student->email})");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    mtrace("    ERROR: {$e->getMessage()} for {$student->firstname} {$student->lastname}");
                }
            }

            mtrace("  Sent: {$sent}, Failed: {$failed}");
            $totalreports += $sent;
        }

        mtrace("Finished. Total learner reports sent: {$totalreports}");
    }
}
