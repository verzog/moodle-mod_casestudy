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
 * Scheduled task to send weekly submission reports to markers
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\task;

/**
 * Scheduled task to send weekly submission reports
 */
class send_weekly_report extends \core\task\scheduled_task {
    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskweeklyreport', 'mod_casestudy');
    }

    /**
     * Execute the task
     */
    public function execute() {
        global $DB, $CFG;

        // Load constants from locallib.php
        require_once($CFG->dirroot . '/mod/casestudy/lib.php');

        mtrace('Starting weekly case study submission report...');

        // Get the time range for the past week.
        $weekago = time() - (7 * 24 * 60 * 60);
        $now = time();

        // Format dates for display.
        $datefrom = userdate($weekago, get_string('strftimedate'));
        $dateto = userdate($now, get_string('strftimedate'));

        // Get all case study activities.
        $casestudies = $DB->get_records('casestudy', ['notifygraders' => 1]);

        if (empty($casestudies)) {
            mtrace('No case studies with notifications enabled.');
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

            // Get all teachers/markers who can grade.
            $markers = get_users_by_capability($context, 'mod/casestudy:grade', 'u.*', '', '', '', '', '', false);

            if (empty($markers)) {
                mtrace("  No markers found.");
                continue;
            }

            // Check if groups are being used in this course.
            $groupmode = groups_get_activity_groupmode($cm);

            // Send report to each marker about THEIR students' submissions.
            foreach ($markers as $marker) {
                // Get the students this marker can see (based on groups if applicable).
                $studentids = [];

                if ($groupmode == NOGROUPS) {
                    // No groups - marker can see all enrolled students.
                    $enrolledusers = get_enrolled_users($context, 'mod/casestudy:submit', 0, 'u.id');
                    $studentids = array_keys($enrolledusers);
                } else {
                    // Groups are enabled - get students in groups this marker can access.
                    $markergroups = groups_get_all_groups($course->id, $marker->id);
                    if (empty($markergroups)) {
                        if ($groupmode == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $context, $marker)) {
                            continue;
                        }
                        $enrolledusers = get_enrolled_users($context, 'mod/casestudy:submit', 0, 'u.id');
                        $studentids = array_keys($enrolledusers);
                    } else {
                        // Get students from marker's groups.
                        foreach ($markergroups as $group) {
                            $groupmembers = groups_get_members($group->id, 'u.id');
                            foreach ($groupmembers as $member) {
                                if (has_capability('mod/casestudy:submit', $context, $member->id)) {
                                    $studentids[] = $member->id;
                                }
                            }
                        }
                        $studentids = array_unique($studentids);
                    }
                }

                if (empty($studentids)) {
                    mtrace("  Marker " . fullname($marker) . " has no students to report.");
                    continue;
                }

                // Get submissions from this marker's students in the past week.
                [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'user');

                $sql = "SELECT s.*, u.firstname, u.lastname, u.email, u.firstnamephonetic,
                               u.lastnamephonetic, u.middlename, u.alternatename
                        FROM {casestudy_submissions} s
                        JOIN {user} u ON u.id = s.userid
                        WHERE s.casestudyid = :casestudyid
                          AND s.userid $insql
                          AND s.timesubmitted >= :weekago
                          AND s.status IN (:submitted, :inreview, :satisfactory, :unsatisfactory)
                        ORDER BY s.timesubmitted DESC";

                $params = array_merge($inparams, [
                    'casestudyid' => $casestudy->id,
                    'weekago' => $weekago,
                    'now' => $now,
                    'submitted' => CASESTUDY_STATUS_SUBMITTED,
                    'inreview' => CASESTUDY_STATUS_IN_REVIEW,
                    'satisfactory' => CASESTUDY_STATUS_SATISFACTORY,
                    'unsatisfactory' => CASESTUDY_STATUS_UNSATISFACTORY,
                ]);

                $submissions = $DB->get_records_sql($sql, $params);
                if (empty($submissions)) {
                    mtrace("  No submissions for marker " . fullname($marker) . " in the past week.");
                    continue;
                }

                mtrace("  Found " . count($submissions) . " submissions for marker " . fullname($marker));

                // Send report to this marker about their students.
                $sent = $this->send_weekly_report_to_marker($casestudy, $submissions, $marker, $cm, $course, $datefrom, $dateto);
                if ($sent) {
                    $totalreports++;
                    mtrace("  Sent report to: " . fullname($marker));
                }
            }
        }

        mtrace("Weekly report task completed. Sent {$totalreports} reports.");
    }

    /**
     * Send weekly report to a specific marker about their students
     *
     * @param object $casestudy Case study instance
     * @param array $submissions Array of submissions from marker's students
     * @param object $marker Marker/teacher user object
     * @param object $cm Course module
     * @param object $course Course record
     * @param string $datefrom Start date of the report period
     * @param string $dateto End date of the report period
     * @return bool Success
     */
    protected function send_weekly_report_to_marker($casestudy, $submissions, $marker, $cm, $course, $datefrom, $dateto) {
        global $CFG;

        // Build the report content.
        $casestudyurl = new \moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]);

        // Build submission list.
        $submissionlist = [];
        foreach ($submissions as $submission) {
            $submissionurl = new \moodle_url('/mod/casestudy/view_casestudy.php', [
                'id' => $cm->id,
                'submissionid' => $submission->id,
            ]);

            $submissionlist[] = [
                'student' => fullname($submission),
                'status' => get_string('status_' . $submission->status, 'mod_casestudy'),
                'timesubmitted' => userdate($submission->timesubmitted),
                'url' => $submissionurl->out(false),
            ];
        }

        // Prepare message.
        $message = new \core\message\message();
        $message->component = 'mod_casestudy';
        $message->name = 'weeklyreport';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $marker;
        $message->subject = get_string('weeklyreportsubject', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
        ]);

        $messagedata = [
            'marker' => fullname($marker),
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
            'count' => count($submissions),
            'submissions' => $submissionlist,
            'url' => $casestudyurl->out(false),
            'datefrom' => $datefrom,
            'dateto' => $dateto,
        ];

        $message->fullmessage = $this->build_text_report($messagedata);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $this->build_html_report($messagedata);
        $message->smallmessage = get_string('weeklyreportsmall', 'mod_casestudy', [
            'count' => count($submissions),
            'casestudy' => $casestudy->name,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
        ]);
        $message->notification = 1;
        $message->contexturl = $casestudyurl->out(false);
        $message->contexturlname = get_string('viewcasestudy', 'mod_casestudy');
        $message->courseid = $course->id;

        return message_send($message);
    }

    /**
     * Build plain text report
     *
     * @param array $data Message data
     * @return string
     */
    protected function build_text_report($data) {
        $text = get_string('weeklyreporttext', 'mod_casestudy', $data) . "\n\n";

        $text .= "=" . str_repeat("=", 70) . "\n";
        $text .= get_string('submissions', 'mod_casestudy') . " ({$data['count']})\n";
        $text .= "=" . str_repeat("=", 70) . "\n\n";

        foreach ($data['submissions'] as $submission) {
            $text .= sprintf(
                "- %s\n  %s: %s\n  %s: %s\n  %s: %s\n\n",
                $submission['student'],
                get_string('status', 'mod_casestudy'),
                $submission['status'],
                get_string('timesubmitted', 'mod_casestudy'),
                $submission['timesubmitted'],
                get_string('viewsubmission', 'mod_casestudy'),
                $submission['url']
            );
        }

        $text .= "=" . str_repeat("=", 70) . "\n\n";
        $text .= get_string('viewallsubmissions', 'mod_casestudy') . ": " . $data['url'] . "\n";

        return $text;
    }

    /**
     * Build HTML report
     *
     * @param array $data Message data
     * @return string
     */
    protected function build_html_report($data) {
        $html = '<div style="font-family: Arial, sans-serif; color: #333;">';
        $html .= '<p>' . get_string('weeklyreporthtml', 'mod_casestudy', $data) . '</p>';

        $html .= '<h3>' . get_string('submissions', 'mod_casestudy') . ' (' . $data['count'] . ')</h3>';

        $html .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $html .= '<thead><tr style="background-color: #f5f5f5; border-bottom: 2px solid #ddd;">';
        $html .= '<th style="padding: 10px; text-align: left;">' . get_string('student', 'mod_casestudy') . '</th>';
        $html .= '<th style="padding: 10px; text-align: left;">' . get_string('status', 'mod_casestudy') . '</th>';
        $html .= '<th style="padding: 10px; text-align: left;">' . get_string('timesubmitted', 'mod_casestudy') . '</th>';
        $html .= '<th style="padding: 10px; text-align: left;">' . get_string('actions', 'mod_casestudy') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($data['submissions'] as $submission) {
            $html .= '<tr style="border-bottom: 1px solid #eee;">';
            $html .= '<td style="padding: 10px;">' . htmlspecialchars($submission['student']) . '</td>';
            $html .= '<td style="padding: 10px;">' . htmlspecialchars($submission['status']) . '</td>';
            $html .= '<td style="padding: 10px;">' . htmlspecialchars($submission['timesubmitted']) . '</td>';
            $html .= '<td style="padding: 10px;"><a href="' . $submission['url'] . '" style="color: #0066cc;">' .
                     get_string('view', 'mod_casestudy') . '</a></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $html .= '<p><a href="' . $data['url'] . '" style="display: inline-block; padding: 10px 20px; ' .
                 'background-color: #0066cc; color: white; text-decoration: none; border-radius: 4px;">' .
                 get_string('viewallsubmissions', 'mod_casestudy') . '</a></p>';

        $html .= '</div>';

        return $html;
    }
}
