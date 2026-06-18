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
 * Notification helper for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy;

/**
 * Helper class for sending notifications
 */
class notification_helper {
    /**
     * Send web notification to markers when a learner submits
     *
     * @param object $casestudy Case study instance
     * @param object $submission Submission record
     * @param object $cm Course module
     * @param object $course Course record
     * @return bool Success
     */
    public static function send_submission_notification($casestudy, $submission, $cm, $course) {
        global $DB, $CFG;

        // Check if notifications are enabled.
        if (empty($casestudy->notifygraders)) {
            return false;
        }

        // Get the student who submitted.
        $student = $DB->get_record('user', ['id' => $submission->userid]);
        if (!$student) {
            return false;
        }

        $context = \context_module::instance($cm->id);

        // Get all users with grading capability.
        $graders = get_users_by_capability($context, 'mod/casestudy:grade', 'u.*', '', '', '', '', '', false);

        if (empty($graders)) {
            return false;
        }

        // Filter graders by group access when groups are in use.
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode != NOGROUPS) {
            $filteredgraders = [];
            foreach ($graders as $graderid => $grader) {
                // Graders with site-wide access-all-groups always get notified.
                if ($context instanceof \context && has_capability('moodle/site:accessallgroups', $context, $grader)) {
                    $filteredgraders[$graderid] = $grader;
                    continue;
                }
                $gradergroups = groups_get_all_groups($course->id, $grader->id);
                if (empty($gradergroups)) {
                    // In separate groups mode a grader with no groups cannot see this student.
                    if ($groupmode == SEPARATEGROUPS) {
                        continue;
                    }
                    // Visible groups: graders without a group can still see everyone.
                    $filteredgraders[$graderid] = $grader;
                } else {
                    // Only notify if the submitting student shares at least one group with this grader.
                    foreach ($gradergroups as $group) {
                        if (groups_is_member($group->id, $student->id)) {
                            $filteredgraders[$graderid] = $grader;
                            break;
                        }
                    }
                }
            }
            $graders = $filteredgraders;

            if (empty($graders)) {
                return false;
            }
        }

        // Prepare the message.
        $submissionurl = new \moodle_url('/mod/casestudy/view_casestudy.php', [
            'id' => $cm->id,
            'submissionid' => $submission->id,
        ]);

        $message = new \core\message\message();
        $message->component = 'mod_casestudy';
        $message->name = 'submission';
        $message->userfrom = $student;
        $message->subject = get_string('submissionnotificationsubject', 'mod_casestudy', [
            'student' => fullname($student),
            'casestudy' => $casestudy->name,
        ]);
        $message->fullmessage = get_string('submissionnotificationtext', 'mod_casestudy', [
            'student' => fullname($student),
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
            'url' => $submissionurl->out(false),
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('submissionnotificationhtml', 'mod_casestudy', [
            'student' => fullname($student),
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
            'url' => $submissionurl->out(false),
        ]);
        $message->smallmessage = get_string('submissionnotificationsmall', 'mod_casestudy', [
            'student' => fullname($student),
            'casestudy' => $casestudy->name,
        ]);
        $message->notification = 1;
        $message->contexturl = $submissionurl->out(false);
        $message->contexturlname = get_string('viewsubmission', 'mod_casestudy');
        $message->courseid = $course->id;

        // Send to all graders.
        $success = true;
        foreach ($graders as $grader) {
            $message->userto = $grader;
            if (!message_send($message)) {
                $success = false;
            }
        }

        // Send to additional email addresses if specified.
        if (!empty($casestudy->notifyemail)) {
            self::send_to_additional_emails($casestudy->notifyemail, $message, $student);
        }

        return $success;
    }

    /**
     * Send email confirmation to learner when they submit
     *
     * @param object $casestudy Case study instance
     * @param object $submission Submission record
     * @param object $cm Course module
     * @param object $course Course record
     * @return bool Success
     */
    public static function send_submission_confirmation($casestudy, $submission, $cm, $course) {
        global $DB;

        // Get the student.
        $student = $DB->get_record('user', ['id' => $submission->userid]);
        if (!$student) {
            return false;
        }

        // Prepare the message.
        $submissionurl = new \moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]);

        $message = new \core\message\message();
        $message->component = 'mod_casestudy';
        $message->name = 'submissionconfirmation';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $student;
        $message->subject = get_string('submissionconfirmationsubject', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
        ]);
        $message->fullmessage = get_string('submissionconfirmationtext', 'mod_casestudy', [
            'student' => fullname($student),
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
            'timesubmitted' => userdate($submission->timesubmitted),
            'url' => $submissionurl->out(false),
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('submissionconfirmationhtml', 'mod_casestudy', [
            'student' => fullname($student),
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
            'timesubmitted' => userdate($submission->timesubmitted),
            'url' => $submissionurl->out(false),
        ]);
        $message->smallmessage = get_string('submissionconfirmationsmall', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
        ]);
        $message->notification = 1;
        $message->contexturl = $submissionurl->out(false);
        $message->contexturlname = get_string('viewcasestudy', 'mod_casestudy');
        $message->courseid = $course->id;

        return message_send($message);
    }

    /**
     * Send notification to learner when their submission is graded or status changes
     * (NOT when a marker makes a comment)
     *
     * @param object $casestudy Case study instance
     * @param object $submission Submission record
     * @param object $grade Grade record
     * @param object $cm Course module
     * @param object $course Course record
     * @param string $oldstatus Previous status
     * @param bool $notifystudent Whether to send notification (default true for backward compatibility)
     * @return bool Success
     */
    public static function send_grade_notification($casestudy, $submission, $grade, $cm, $course, $oldstatus = null, $notifystudent = true) {
        global $DB;

        // Don't send if notification is disabled
        if (!$notifystudent) {
            return false;
        }

        // Get the student.
        $student = $DB->get_record('user', ['id' => $submission->userid]);
        if (!$student) {
            return false;
        }

        // Get the grader.
        $grader = $DB->get_record('user', ['id' => $grade->graderid]);
        if (!$grader) {
            $grader = \core_user::get_noreply_user();
        }

        // Determine status text.
        $statusstr = self::get_status_string($submission->status);

        // Prepare the message.
        $submissionurl = new \moodle_url('/mod/casestudy/view_casestudy.php', [
            'id' => $cm->id,
            'submissionid' => $submission->id,
        ]);

        $message = new \core\message\message();
        $message->component = 'mod_casestudy';
        $message->name = 'gradenotification';
        $message->userfrom = $grader;
        $message->userto = $student;
        $message->subject = get_string('gradenotificationsubject', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
            'status' => $statusstr,
        ]);

        $messagedata = [
            'student' => fullname($student),
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
            'status' => $statusstr,
            'feedback' => !empty($grade->feedback) ? format_text($grade->feedback, $grade->feedbackformat) : '',
            'grader' => $casestudy->hidegrader ? get_string('grader', 'mod_casestudy') : fullname($grader),
            'url' => $submissionurl->out(false),
        ];

        $message->fullmessage = get_string('gradenotificationtext', 'mod_casestudy', $messagedata);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('gradenotificationhtml', 'mod_casestudy', $messagedata);
        $message->smallmessage = get_string('gradenotificationsmall', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
            'status' => $statusstr,
        ]);
        $message->notification = 1;
        $message->contexturl = $submissionurl->out(false);
        $message->contexturlname = get_string('viewsubmission', 'mod_casestudy');
        $message->courseid = $course->id;

        return message_send($message);
    }

    /**
     * Send to additional email addresses
     *
     * @param string $emails Comma-separated email addresses
     * @param object $message Base message object
     * @param object $student Student user object
     * @return void
     */
    protected static function send_to_additional_emails($emails, $message, $student) {
        global $CFG;

        $emaillist = array_map('trim', explode(',', $emails));

        foreach ($emaillist as $email) {
            if (validate_email($email)) {
                // Use email_to_user() instead of message_send() for external emails
                $tempuser = new \stdClass();
                $tempuser->email = $email;
                $tempuser->firstname = '';
                $tempuser->lastname = '';
                $tempuser->maildisplay = true;
                $tempuser->mailformat = 1; // HTML email
                $tempuser->id = -99;
                $tempuser->firstnamephonetic = '';
                $tempuser->lastnamephonetic = '';
                $tempuser->middlename = '';
                $tempuser->alternatename = '';

                $subject = $message->subject;
                $messagetext = $message->fullmessage;
                $messagehtml = $message->fullmessagehtml;

                // Send directly via email, bypassing the message system
                email_to_user($tempuser, $message->userfrom, $subject, $messagetext, $messagehtml);
            }
        }
    }

    /**
     * Get human-readable status string
     *
     * @param string $status Status constant
     * @return string
     */
    protected static function get_status_string($status) {
        return get_string('status_' . $status, 'mod_casestudy');
    }

    /**
     * Check if status change warrants notification
     * Only send when graded or status changed to resubmit, NOT for comments
     *
     * @param string $oldstatus Previous status
     * @param string $newstatus New status
     * @return bool
     */
    public static function should_notify_status_change($oldstatus, $newstatus) {
        // Notify when moving to final states or requesting resubmission.
        $notifiablestates = [
            CASESTUDY_STATUS_SATISFACTORY,
            CASESTUDY_STATUS_UNSATISFACTORY,
            CASESTUDY_STATUS_AWAITING_RESUBMISSION,
        ];

        return in_array($newstatus, $notifiablestates) && $oldstatus !== $newstatus;
    }

    /**
     * Send learner progress report email
     *
     * @param object $casestudy Case study instance
     * @param object $user User/student record
     * @param object $cm Course module
     * @param object $course Course record
     * @return bool Success
     */
    public static function send_learner_report($casestudy, $user, $cm, $course) {
        global $DB;

        // Get completion rules.
        $completionrules = $DB->get_records(
            'casestudy_completion_rules',
            ['casestudyid' => $casestudy->id, 'enabled' => 1],
            'sortorder ASC'
        );

        if (empty($completionrules)) {
            return false; // No completion criteria set, don't send report.
        }

        // Build the completion status report.
        $reportdata = self::build_learner_report_data($casestudy, $user->id, $completionrules);

        // Prepare the message.
        $viewurl = new \moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]);

        $message = new \core\message\message();
        $message->component = 'mod_casestudy';
        $message->name = 'learnerreport';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string('learnerreportsubject', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
            'course' => $course->shortname,
        ]);
        $message->fullmessage = self::format_learner_report_text($reportdata, $casestudy, $course, $user, $viewurl);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = self::format_learner_report_html($reportdata, $casestudy, $course, $user, $viewurl);
        $message->smallmessage = get_string('learnerreportsmall', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
        ]);
        $message->notification = 1;
        $message->contexturl = $viewurl->out(false);
        $message->contexturlname = get_string('viewcasestudy', 'mod_casestudy');
        $message->courseid = $course->id;

        return message_send($message);
    }

    /**
     * Build learner report data
     *
     * @param object $casestudy Case study instance
     * @param int $userid User ID
     * @param array $completionrules Completion rules
     * @return array Report data
     */
    protected static function build_learner_report_data($casestudy, $userid, $completionrules) {
        global $DB;

        $criteria = [];
        $overallcomplete = true;

        foreach ($completionrules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_TOTAL) {
                // Total satisfactory count.
                $current = $DB->count_records('casestudy_submissions', [
                    'casestudyid' => $casestudy->id,
                    'userid' => $userid,
                    'status' => CASESTUDY_STATUS_SATISFACTORY,
                ]);

                $completed = ($current >= $rule->count);
                if (!$completed) {
                    $overallcomplete = false;
                }

                $criteria[] = [
                    'label' => get_string('totalsatisfactory', 'mod_casestudy'),
                    'current' => $current,
                    'required' => $rule->count,
                    'completed' => $completed,
                ];
            } else if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY && !empty($rule->fieldid)) {
                // Category-based completion.
                $field = $DB->get_record('casestudy_fields', ['id' => $rule->fieldid]);
                if (!$field) {
                    continue;
                }

                $fieldname = format_string($field->name);

                // Resolve category value.
                $actualvalue = null;
                if (!empty($rule->categoryvalue)) {
                    $fields = $DB->get_records(
                        'casestudy_fields',
                        ['casestudyid' => $casestudy->id, 'category' => 1],
                        'sortorder ASC',
                        'id, param1'
                    );

                    $optionindex = 1;
                    foreach ($fields as $f) {
                        $values = $f->param1 ? json_decode($f->param1, true) : [];
                        if (is_array($values)) {
                            foreach ($values as $v) {
                                if ($optionindex == $rule->categoryvalue && $f->id == $rule->fieldid) {
                                    $actualvalue = $v;
                                    break 2;
                                }
                                $optionindex++;
                            }
                        }
                    }
                }

                // Count satisfactory submissions for this category.
                if (!empty($actualvalue)) {
                    $contentwhere = 'AND c.content = :content';
                    $params = [
                        'casestudyid' => $casestudy->id,
                        'userid' => $userid,
                        'status' => CASESTUDY_STATUS_SATISFACTORY,
                        'fieldid' => $rule->fieldid,
                        'content' => $actualvalue,
                    ];
                } else {
                    $contentwhere = 'AND c.content IS NOT NULL AND c.content != \'\'';
                    $params = [
                        'casestudyid' => $casestudy->id,
                        'userid' => $userid,
                        'status' => CASESTUDY_STATUS_SATISFACTORY,
                        'fieldid' => $rule->fieldid,
                    ];
                }

                $current = $DB->count_records_sql(
                    "
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

                $completed = ($current >= $rule->count);
                if (!$completed) {
                    $overallcomplete = false;
                }

                $label = $fieldname;
                if (!empty($actualvalue)) {
                    $label .= ' (' . $actualvalue . ')';
                }

                $criteria[] = [
                    'label' => $label,
                    'current' => $current,
                    'required' => $rule->count,
                    'completed' => $completed,
                ];
            }
        }

        return [
            'criteria' => $criteria,
            'overallcomplete' => $overallcomplete,
        ];
    }

    /**
     * Format learner report as plain text
     *
     * @param array $reportdata Report data
     * @param object $casestudy Case study instance
     * @param object $course Course record
     * @param object $user User record
     * @param object $viewurl View URL
     * @return string Plain text message
     */
    protected static function format_learner_report_text($reportdata, $casestudy, $course, $user, $viewurl) {
        $text = get_string('learnerreporthello', 'mod_casestudy', fullname($user)) . "\n\n";
        $text .= get_string('learnerreportintro', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
        ]) . "\n\n";

        $text .= "-------------------------------------------\n";
        $text .= get_string('completionstatus', 'mod_casestudy') . "\n";
        $text .= "-------------------------------------------\n\n";

        foreach ($reportdata['criteria'] as $criterion) {
            $status = $criterion['completed'] ? '[✓] ' . get_string('completed', 'completion')
                                               : '[ ] ' . get_string('todo', 'completion');
            $text .= sprintf(
                "%s: %d / %d %s\n",
                $criterion['label'],
                $criterion['current'],
                $criterion['required'],
                $status
            );
        }

        $text .= "\n-------------------------------------------\n";
        if ($reportdata['overallcomplete']) {
            $text .= get_string('learnerreportcomplete', 'mod_casestudy') . "\n";
        } else {
            $text .= get_string('learnerreportincomplete', 'mod_casestudy') . "\n";
        }
        $text .= "-------------------------------------------\n\n";

        $text .= get_string('learnerreportviewlink', 'mod_casestudy') . "\n";
        $text .= $viewurl->out(false) . "\n";

        return $text;
    }

    /**
     * Format learner report as HTML
     *
     * @param array $reportdata Report data
     * @param object $casestudy Case study instance
     * @param object $course Course record
     * @param object $user User record
     * @param object $viewurl View URL
     * @return string HTML message
     */
    protected static function format_learner_report_html($reportdata, $casestudy, $course, $user, $viewurl) {
        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';

        $html .= '<p>' . get_string('learnerreporthello', 'mod_casestudy', fullname($user)) . '</p>';
        $html .= '<p>' . get_string('learnerreportintro', 'mod_casestudy', [
            'casestudy' => $casestudy->name,
            'course' => $course->fullname,
        ]) . '</p>';

        $html .= '<h3>' . get_string('completionstatus', 'mod_casestudy') . '</h3>';
        $html .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $html .= '<thead><tr style="background-color: #f5f5f5;">';
        $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">' . get_string('criterion', 'mod_casestudy') . '</th>';
        $html .= '<th style="padding: 10px; text-align: center; border: 1px solid #ddd;">' . get_string('progress', 'mod_casestudy') . '</th>';
        $html .= '<th style="padding: 10px; text-align: center; border: 1px solid #ddd;">' . get_string('status', 'mod_casestudy') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($reportdata['criteria'] as $criterion) {
            $statusbg = $criterion['completed'] ? '#d4edda' : '#fff3cd';
            $statustext = $criterion['completed'] ? get_string('completed', 'completion') : get_string('todo', 'completion');
            $statusicon = $criterion['completed'] ? '✓' : '';

            $html .= '<tr>';
            $html .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $criterion['label'] . '</td>';
            $html .= '<td style="padding: 10px; text-align: center; border: 1px solid #ddd;"><strong>' . $criterion['current'] . '</strong> / ' . $criterion['required'] . '</td>';
            $html .= '<td style="padding: 10px; text-align: center; border: 1px solid #ddd; background-color: ' . $statusbg . ';">' . $statusicon . ($statusicon ? ' ' : '') . $statustext . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        if ($reportdata['overallcomplete']) {
            $html .= '<div style="padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;">';
            $html .= '<strong style="color: #155724;">✓ ' . get_string('learnerreportcomplete', 'mod_casestudy') . '</strong>';
            $html .= '</div>';
        } else {
            $html .= '<div style="padding: 15px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; margin: 20px 0;">';
            $html .= '<strong style="color: #856404;">' . get_string('learnerreportincomplete', 'mod_casestudy') . '</strong>';
            $html .= '</div>';
        }

        $html .= '<p><a href="' . $viewurl->out(false) . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 5px;">'
               . get_string('viewcasestudy', 'mod_casestudy') . '</a></p>';

        $html .= '</div>';

        return $html;
    }
}
