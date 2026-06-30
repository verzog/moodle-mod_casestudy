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
 * Case Study renderer
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/casestudy/lib.php');

use mod_casestudy\local\casestudy;
use plugin_renderer_base;
use mod_casestudy\local\field_manager;
use mod_casestudy\local\helper;
use mod_casestudy\local\submission;

/**
 * Case Study renderer class
 */
class renderer extends plugin_renderer_base {
    /**
     * Render management interface for teachers
     *
     * @param object $casestudy Case study instance
     * @param object $cm Course module
     * @param array $fields Array of field objects
     * @param bool $hasfields Whether fields exist
     * @return string HTML output
     */
    public function management_interface($casestudy, $cm, $fields, $hasfields) {

        $templatecontext = [
            'hasfields' => $hasfields,
            'cmid' => $cm->id,
            'managementbuttons' => $this->get_management_buttons($cm, $hasfields),
        ];

        return $this->render_from_template('mod_casestudy/management_interface', $templatecontext);
    }

    /**
     * Render student interface
     *
     * @param object $casestudy Case study instance
     * @param object $cm Course module
     * @param array $fields Array of field objects
     * @param array $submissions User submissions
     * @param bool $cansubmitmore Whether user can submit more
     * @param object $casestudyinstance Case study instance (for completion criteria)
     * @param string $availabilitymessage Message about availability restrictions
     * @param string $availabilitystatus Status indicator (notopened, closed, or empty)
     * @param bool $preventaccess Whether to completely hide content (for not opened/closed)
     * @return string HTML output
     */
    public function student_interface(
        $casestudy,
        $cm,
        $fields,
        $submissions,
        $cansubmitmore,
        $casestudyinstance = null,
        $availabilitymessage = '',
        $availabilitystatus = '',
        $preventaccess = false
    ) {
        global $USER, $DB;

        // Use casestudy object if no separate instance provided
        $casestudyinstance = $casestudyinstance ?: $casestudy;

        // Format completion summary
        $completionsummary = $this->format_completion_summary($casestudyinstance, $USER->id);

        // Generate submissions table only if access is allowed
        $tableoutput = '';
        if (!$preventaccess) {
            $table = new \mod_casestudy\local\table\submission_table(
                'student-submissions-' . $cm->id,
                $cm,
                \context_module::instance($cm->id),
                0,
                ''
            );
            ob_start();
            $table->out(25, true);
            $tableoutput = ob_get_clean();
        }

        // Get case study information
        $casestudyinfo = $this->get_casestudy_info($casestudy, $USER->id);

        $templatecontext = [
            'cmid' => $cm->id,
            'cansubmitmore' => $cansubmitmore,
            'completionsummary' => $completionsummary,
            'tableoutput' => $tableoutput,
            'submissionurl' => new \moodle_url('/mod/casestudy/submission.php', ['id' => $cm->id]),
            'availabilitymessage' => $availabilitymessage,
            'hasavailabilitymessage' => !empty($availabilitymessage),
            'availabilitystatus' => $availabilitystatus,
            'preventaccess' => $preventaccess,
            'casestudyinfo' => $casestudyinfo,
        ];

        return $this->render_from_template('mod_casestudy/student_interface', $templatecontext);
    }

    /**
     * Render grader interface
     *
     * @param object $cm Course module
     * @return string HTML output
     */
    public function grader_interface($cm) {
        $output = '';

        // Use dynamic submissions table for all submissions (graders can see all)
        $table = new \mod_casestudy\local\table\submission_table(
            'grader-submissions-' . $cm->id,
            $cm,
            \context_module::instance($cm->id),
            0,
            ''
        );

        $table->initialbars(true);

        ob_start();
        $table->out(25, true);
        $tableoutput = ob_get_clean();

        $baseurl = new \moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]);
        $casestudy = \mod_casestudy\local\casestudy::instance($cm->instance);

        $actionmenu = new \mod_casestudy\output\submission_actionmenu($casestudy, $baseurl, [
            'firstname' => $table->get_initial_first(),
            'lastname' => $table->get_initial_last(),
        ]);
        $actionmenu->set_current_view('submissions');

        $output .= $this->render($actionmenu);

        if (empty($tableoutput) || strpos($tableoutput, 'Nothing to display') !== false) {
            $output .= \html_writer::div(
                get_string('nosubmissions', 'mod_casestudy'),
                'alert alert-info'
            );
        } else {
            $output .= $tableoutput;
        }

        return $output;
    }

    /**
     * Render view only interface
     *
     * @return string HTML output
     */
    public function view_only_interface() {
        return $this->render_from_template('mod_casestudy/view_only_interface', []);
    }

    /**
     * Render summaries interface for markers/managers/admins
     *
     * @param object $cm Course module
     * @param int $groupid Group ID for filtering
     * @param int $userid User ID for filtering
     * @return string HTML output
     */
    public function summaries_interface($cm, $groupid = 0, $userid = 0) {
        global $DB;

        $output = '';

        $context = \context_module::instance($cm->id);
        $casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

        // Create the summary table.
        $table = new \mod_casestudy\local\table\summary_table(
            'casestudy-summaries-' . $cm->id,
            $cm,
            $context,
            $groupid
        );

        // Set up tertiary navigation.
        $baseurl = new \moodle_url('/mod/casestudy/summaries.php', ['id' => $cm->id]);
        $casestudyobj = \mod_casestudy\local\casestudy::instance($cm->instance);

        $actionmenu = new \mod_casestudy\output\submission_actionmenu($casestudyobj, $baseurl, [
            'firstname' => $table->get_initial_first(),
            'lastname' => $table->get_initial_last(),
        ]);

        // Set current view to summaries.
        $actionmenu->set_current_view('summaries');

        // Disable the status filter for summaries view.
        $actionmenu->disable_filters(['status']);

        $output .= $this->render($actionmenu);

        // Render the table.
        ob_start();
        $table->out(25, true);
        $tableoutput = ob_get_clean();

        if (empty($tableoutput) || strpos($tableoutput, 'Nothing to display') !== false) {
            $output .= \html_writer::div(
                get_string('nostudents', 'mod_casestudy'),
                'alert alert-info'
            );
        } else {
            $output .= $tableoutput;
        }

        return $output;
    }

    /**
     * Render fields management page
     *
     * @param object $cm Course module
     * @param object $context Context object
     * @param array $fields Array of field objects
     * @param int $maxorder Maximum sort order value
     * @return string HTML output
     */
    public function fields_management_page($cm, $context, $fields, $maxorder) {

        $fieldtypes = $this->get_field_types_with_icons();

        // Inlcude URls.
        $url = new \moodle_url('/mod/casestudy/fields/edit.php', ['id' => $cm->id]);

        $fieldtypes = array_map(function ($type) use ($url) {
            $url->param('type', $type['value']);
            $type['url'] = $url->out(false);
            return $type;
        }, $fieldtypes);

        // Generate fields table
        $table = new \mod_casestudy\local\table\fields_table('casestudy-fields-table', $cm, $context);
        $table->define_baseurl(new \moodle_url('/mod/casestudy/fields.php', ['id' => $cm->id]));

        ob_start();
        $table->out(count($fields), false);
        $fieldstable = ob_get_clean();

        $templatecontext = [
            'cmid' => $cm->id,
            'sesskey' => sesskey(),
            'hasfields' => !empty($fields),
            'fieldtypes' => $fieldtypes,
            'fieldstable' => $fieldstable,
            'backurl' => new \moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]),
            'previewurl' => new \moodle_url('/mod/casestudy/submission.php', ['id' => $cm->id, 'mode' => 'preview']),
        ];

        return $this->render_from_template('mod_casestudy/managefields', $templatecontext);
    }

    /**
     * Render view only interface
     *
     * @return string HTML output
     */
    public function render_view_only_interface() {
        return $this->render_from_template('mod_casestudy/view_only_interface', []);
    }

    /**
     * Render no fields message
     *
     * @param bool $canmanage Whether user can manage fields
     * @return string HTML output
     */
    public function render_no_fields_message($canmanage) {
        $templatecontext = [
            'canmanage' => $canmanage,
        ];

        return $this->render_from_template('mod_casestudy/no_fields_message', $templatecontext);
    }

    /**
     * Get management buttons data
     *
     * @param object $cm Course module
     * @param bool $hasfields Whether fields exist
     * @return array Button data
     */
    private function get_management_buttons($cm, $hasfields) {
        $buttons = [];

        if (!$hasfields) {
            $buttons[] = [
                'url' => new \moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]),
                'label' => get_string('managefields', 'mod_casestudy'),
                'class' => 'btn btn-primary',
            ];
        } else {
            $buttons[] = [
                'url' => new \moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]),
                'label' => get_string('managefields', 'mod_casestudy'),
                'class' => 'btn btn-secondary',
            ];
            $buttons[] = [
                'url' => new \moodle_url('/mod/casestudy/submission.php', ['id' => $cm->id]),
                'label' => get_string('viewsubmissions', 'mod_casestudy'),
                'class' => 'btn btn-secondary',
            ];
            $buttons[] = [
                'url' => new \moodle_url('/mod/casestudy/overrides.php', ['id' => $cm->id]),
                'label' => get_string('overrides', 'mod_casestudy'),
                'class' => 'btn btn-secondary',
            ];
            $buttons[] = [
                'url' => new \moodle_url('/mod/casestudy/reports.php', ['id' => $cm->id]),
                'label' => get_string('reports', 'mod_casestudy'),
                'class' => 'btn btn-secondary',
            ];
        }

        return $buttons;
    }

    /**
     * Format completion summary for template
     *
     * @param object $casestudy Case study instance (contains completion criteria directly)
     * @param int $userid User ID
     * @return array Formatted completion data
     */
    private function format_completion_summary($casestudy, $userid) {
        global $DB;

        // Get completion rules from the new casestudy_completion_rules table
        $completionrules = $DB->get_records(
            'casestudy_completion_rules',
            ['casestudyid' => $casestudy->id, 'enabled' => 1],
            'sortorder ASC'
        );

        if (empty($completionrules)) {
            return ['hascompletion' => false];
        }

        $criteria = [];
        $categorycount = 0;

        // Get aggregation mode for category rules
        $aggregation = isset($casestudy->completionaggr) ? $casestudy->completionaggr : CASESTUDY_COMPLETION_ALL;

        foreach ($completionrules as $rule) {
            // Handle total satisfactory completion rule
            if ($rule->ruletype == CASESTUDY_COMPLETION_TOTAL) {
                $current = $DB->count_records('casestudy_submissions', [
                    'casestudyid' => $casestudy->id,
                    'userid' => $userid,
                    'status' => CASESTUDY_STATUS_SATISFACTORY,
                ]);

                $criteria[] = [
                    'label' => get_string('totalsatisfactory', 'mod_casestudy'),
                    'current' => $current,
                    'required' => $rule->count,
                    'completed' => $current >= $rule->count,
                    'iscategory' => false,
                ];
            }

            // Handle category-based completion rules
            if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY && !empty($rule->fieldid)) {
                $categorycount++;

                $field = $DB->get_record('casestudy_fields', ['id' => $rule->fieldid]);
                if (!$field) {
                    continue;
                }

                // Determine the actual category value from the global index
                $actualvalue = null;
                if (!empty($rule->categoryvalue)) {
                    // Rebuild the global index-to-value mapping to find the actual value
                    $fields = $DB->get_records(
                        'casestudy_fields',
                        ['casestudyid' => $casestudy->id, 'category' => 1],
                        'sortorder ASC',
                        'id, param1'
                    );

                    $optionindex = 1;
                    foreach ($fields as $fielditem) {
                        $values = $fielditem->param1 ? json_decode($fielditem->param1, true) : [];
                        if (is_array($values)) {
                            foreach ($values as $v) {
                                if ($optionindex == $rule->categoryvalue && $fielditem->id == $rule->fieldid) {
                                    $actualvalue = $v;
                                    break 2;
                                }
                                $optionindex++;
                            }
                        }
                    }
                }

                // Build query based on whether we need a specific value or any value
                if (!empty($actualvalue)) {
                    // Count submissions with specific category value
                    $current = $DB->count_records_sql(
                        "
                        SELECT COUNT(DISTINCT s.id)
                          FROM {casestudy_submissions} s
                          JOIN {casestudy_content} c ON s.id = c.submissionid
                         WHERE s.casestudyid = :casestudyid
                           AND s.userid = :userid
                           AND s.status = :status
                           AND c.fieldid = :fieldid
                           AND c.content = :content",
                        [
                            'casestudyid' => $casestudy->id,
                            'userid' => $userid,
                            'status' => CASESTUDY_STATUS_SATISFACTORY,
                            'fieldid' => $rule->fieldid,
                            'content' => $actualvalue,
                        ]
                    );

                    $label = $field->name . ' (' . format_string($actualvalue) . ')';
                } else {
                    // Count submissions with any value in this category field
                    $current = $DB->count_records_sql(
                        "
                        SELECT COUNT(DISTINCT s.id)
                          FROM {casestudy_submissions} s
                          JOIN {casestudy_content} c ON s.id = c.submissionid
                         WHERE s.casestudyid = :casestudyid
                           AND s.userid = :userid
                           AND s.status = :status
                           AND c.fieldid = :fieldid
                           AND c.content IS NOT NULL
                           AND c.content != ''",
                        [
                            'casestudyid' => $casestudy->id,
                            'userid' => $userid,
                            'status' => CASESTUDY_STATUS_SATISFACTORY,
                            'fieldid' => $rule->fieldid,
                        ]
                    );

                    $label = format_string($field->name);
                }

                $criteria[] = [
                    'label' => $label,
                    'current' => $current,
                    'required' => $rule->count,
                    'completed' => $current >= $rule->count,
                    'iscategory' => true,
                ];
            }
        }

        // Add aggregation mode information if there are multiple category rules
        $aggregationmode = '';
        if ($categorycount > 1) {
            $aggregationmode = ($aggregation == CASESTUDY_COMPLETION_ALL)
                ? get_string('completionaggregationall', 'mod_casestudy')
                : get_string('completionaggregationany', 'mod_casestudy');
        }

        return [
            'hascompletion' => true,
            'criteria' => $criteria,
            'hascategoryaggregation' => $categorycount > 1,
            'aggregationmode' => $aggregationmode,
        ];
    }

    /**
     * Get case study information for display
     *
     * @param object $casestudy Case study instance
     * @param int $userid User ID
     * @return array Information array
     */
    private function get_casestudy_info($casestudy, $userid) {
        global $DB;

        // Get effective settings including any user overrides
        $effective = casestudy_get_effective_settings($casestudy, $userid);

        // Count original/parent submissions only (not resubmissions).
        $sql = "SELECT COUNT(*)
                  FROM {casestudy_submissions}
                 WHERE casestudyid = :casestudyid
                   AND userid = :userid
                   AND (parentid IS NULL OR parentid = 0)";
        $totalentries = $DB->count_records_sql($sql, [
            'casestudyid' => $casestudy->id,
            'userid' => $userid,
        ]);

        $info = [];

        // Add entries count (number of unique case studies, not including resubmissions)
        $entriesvalue = ($casestudy->maxsubmissions == 0) ? $totalentries : $totalentries;
        $info[] = [
            'icon' => 'list',
            'label' => get_string('currententries', 'mod_casestudy'),
            'value' => $entriesvalue,
        ];

        // Add max submissions information if limited (use effective settings for overrides)
        if (!empty($effective->maxsubmissions) && $effective->maxsubmissions > 0) {
            $remaining = max(0, $effective->maxsubmissions - $totalentries);

            if ($remaining == 0) {
                // Maximum reached - show completed message
                $info[] = [
                    'icon' => 'check-circle',
                    'label' => get_string('submissionsremaining', 'mod_casestudy'),
                    'value' => get_string('limitreached', 'mod_casestudy'),
                ];
            } else {
                // Show remaining count
                $info[] = [
                    'icon' => 'file-text',
                    'label' => get_string('submissionsremaining', 'mod_casestudy'),
                    'value' => $remaining . ' / ' . $effective->maxsubmissions,
                ];
            }
        }

        // Add max attempts (re-attempts) information if set (use effective settings for overrides)
        if (!empty($effective->maxattempts)) {
            $info[] = [
                'icon' => 'repeat',
                'label' => get_string('maxattempts', 'mod_casestudy'),
                'value' => $effective->maxattempts,
            ];
        }

        // Add start date if set
        if (!empty($casestudy->timeopen)) {
            $info[] = [
                'icon' => 'calendar-check',
                'label' => get_string('startdate', 'mod_casestudy'),
                'value' => userdate($casestudy->timeopen, get_string('strftimedatetime', 'langconfig')),
            ];
        }

        // Add due date if set
        if (!empty($casestudy->timeclose)) {
            $info[] = [
                'icon' => 'calendar-times',
                'label' => get_string('duedate', 'mod_casestudy'),
                'value' => userdate($casestudy->timeclose, get_string('strftimedatetime', 'langconfig')),
            ];
        }

        return [
            'hasinfo' => !empty($info),
            'items' => $info,
        ];
    }

    /**
     * Get available field types for dropdown
     *
     * @return array Array of field type options
     */
    private function get_field_types() {

        return [
            ['value' => 'text', 'label' => get_string('fieldtype_text', 'mod_casestudy')],
            ['value' => 'textarea', 'label' => get_string('fieldtype_textarea', 'mod_casestudy')],
            ['value' => 'dropdown', 'label' => get_string('fieldtype_dropdown', 'mod_casestudy')],
            ['value' => 'radio', 'label' => get_string('fieldtype_radio', 'mod_casestudy')],
            ['value' => 'checkbox', 'label' => get_string('fieldtype_checkbox', 'mod_casestudy')],
            ['value' => 'file', 'label' => get_string('fieldtype_file', 'mod_casestudy')],
            ['value' => 'sectionheading', 'label' => get_string('fieldtype_sectionheading', 'mod_casestudy')],
        ];
    }

    /**
     * Get available field types with icons for popover
     *
     * @return array Array of field types with icons
     */
    public function get_field_types_with_icons() {

        return [
            [
                'value' => 'text',
                'label' => get_string('fieldtype_text', 'mod_casestudy'),
                'icon' => 'font',
            ],
            [
                'value' => 'textarea',
                'label' => get_string('fieldtype_textarea', 'mod_casestudy'),
                'icon' => 'align-left',
            ],
            [
                'value' => 'richtext',
                'label' => get_string('fieldtype_richtext', 'mod_casestudy'),
                'icon' => 'edit',
            ],
            [
                'value' => 'dropdown',
                'label' => get_string('fieldtype_dropdown', 'mod_casestudy'),
                'icon' => 'caret-down',
            ],
            [
                'value' => 'radio',
                'label' => get_string('fieldtype_radio', 'mod_casestudy'),
                'icon' => 'dot-circle',
            ],
            [
                'value' => 'checkbox',
                'label' => get_string('fieldtype_checkbox', 'mod_casestudy'),
                'icon' => 'check-square',
            ],
            [
                'value' => 'file',
                'label' => get_string('fieldtype_file', 'mod_casestudy'),
                'icon' => 'file',
            ],
            [
                'value' => 'sectionheading',
                'label' => get_string('fieldtype_sectionheading', 'mod_casestudy'),
                'icon' => 'header',
            ],
        ];
    }


    /**
     * Render the tertiary navigation action menu shown across submission/grading pages.
     *
     * @param \mod_casestudy\local\casestudy $casestudy Activity wrapper.
     * @param \moodle_url $baseurl Page URL the menu links should anchor against.
     * @param array $initials Optional initials-filter selection.
     * @param array $additionalactions Extra action items to append (e.g. download).
     * @return string HTML.
     */
    public function submission_action_menu(
        \mod_casestudy\local\casestudy $casestudy,
        $baseurl,
        $initials = [],
        $additionalactions = []
    ) {
        $actionmenu = new submission_actionmenu($casestudy, $baseurl, $initials, $additionalactions);
        return $this->render($actionmenu);
    }


    /**
     * Render individual submission view
     *
     * @param object $submission Submission object
     * @param object $user User object
     * @param array $fields Array of field objects
     * @param array $content Array of submission content
     * @param object $cm Course module
     * @param bool $canedit Whether user can edit
     * @param bool $cangrade Whether user can grade
     * @return string HTML output
     */
    public function view_submission(submission $submission, $user, $content, $cm, $canedit = false, $cangrade = false) {
        global $USER, $CFG, $DB;

        require_once($CFG->dirroot . '/mod/casestudy/locallib.php');

        // Fetch the submission data for template.
        $submissiondata = $submission->export_for_template($this);

        // Get case study instance and context.
        $casestudy = \mod_casestudy\local\casestudy::instance($submission->get_submission()->casestudyid);
        $casestudyrecord = $casestudy->get_casestudy_record();
        $context = \context_module::instance($cm->id);

        // Fields list and contents.
        $fields = field_manager::instance($casestudyrecord->id)->get_fields($casestudyrecord->id);
        $contents = $DB->get_records('casestudy_content', ['submissionid' => $submission->get_submission()->id], '', 'fieldid, id, content, contentformat');
        $grade = $DB->get_record('casestudy_grades', ['submissionid' => $submission->get_submission()->id], '*', IGNORE_MISSING);

        // Check if custom template is configured
        $template = new \mod_casestudy\template($casestudy, $cm, $context);
        $customtemplatehtml = $template->render_submission($submission, $fields, $contents, $grade);

        // Prepare grading form if user has permission and submission is not a draft
        $gradeform = null;
        if ($cangrade && has_capability('mod/casestudy:grade', $context) && $submission->get_submission()->status != CASESTUDY_STATUS_DRAFT) {
            try {
                $form = new \mod_casestudy\local\forms\grading_form(null, null, 'post', '', [
                        'data-form' => 'casestudy-grading-form', 'class' => 'casestudy-grading-form mt-3']);
                $form->set_data_for_dynamic_submission();
                $gradeform = $form->render();
            } catch (\Exception $e) {
                debugging('Error creating grading form: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $gradeform = null;
            }
        }

        // Prepare action menu
        $baseurl = new \moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]);
        $additionalactions = [];

        // Actions menu.
        $actionmenu = new submission_actionmenu($casestudy, $baseurl, [], $additionalactions);
        $actionmenu->disable_filters(['group', 'status', 'user', 'initials']);
        $actionmenuhtml = $this->render($actionmenu);

        // Add user navigation for users who can view all submissions
        $usernavigationhtml = '';
        $studentnavigationhtml = '';
        $context = \context_module::instance($cm->id);
        $submissionrecord = $submission->get_submission();
        if (has_capability('mod/casestudy:viewallsubmissions', $context)) {
            $usernavigationhtml = $this->render_user_navigation($cm, $casestudy, $submissionrecord);
        } else if ($submissionrecord->userid == $USER->id) {
            // The viewer is the owner of the submission; offer prev / next through their own attempts.
            $studentnavigationhtml = $this->render_student_submission_navigation($cm, $casestudy, $submissionrecord);
        }

        $submissiondata['customtemplate'] = $customtemplatehtml;

        // Get submission history
        $manager = new \mod_casestudy\local\submission_manager($casestudyrecord->id, $casestudyrecord, $cm);
        $history = $manager->get_submission_history($submission->get_submission()->id);
        $submissiondata['history'] = $this->format_submission_history($history, $context);
        $submissiondata['hashistory'] = count($history) > 1; // Only show if there are multiple attempts

        $submissiondata['showgraderinfo'] = !$casestudy->get_casestudy_record()->hidegrader;
        // Otherwise, use the mustache template with submission data.
        $submissiondata['actionmenu'] = $actionmenuhtml;
        $submissiondata['usernavigation'] = $usernavigationhtml;
        $submissiondata['studentnavigation'] = $studentnavigationhtml;
        $submissiondata['gradeform'] = $gradeform;
        $submissiondata['issubmituser'] = (!$cangrade && !has_capability('mod/casestudy:grade', $context)) ? true : false;

        // Header summary for the student so the attempt number + state is unmistakable.
        $sublocal = $submission->get_submission();
        $statuskey = 'status_' . $sublocal->status;

        // Show edit/submit-draft actions only to the submission's owner viewing a draft.
        $ownerviewingdraft = $sublocal->userid == $USER->id
            && in_array($sublocal->status, [
                CASESTUDY_STATUS_DRAFT,
                CASESTUDY_STATUS_AWAITING_RESUBMISSION,
            ], true);

        $editurl = (new \moodle_url('/mod/casestudy/submission.php', [
            'id' => $cm->id,
            'submissionid' => $sublocal->id,
        ]))->out(false);
        $submitdrafturl = (new \moodle_url('/mod/casestudy/view_casestudy.php', [
            'id' => $cm->id,
            'submissionid' => $sublocal->id,
            'action' => 'submit',
            'sesskey' => sesskey(),
        ]))->out(false);

        $submissiondata['attemptheader'] = [
            'attempt' => (int) $sublocal->attempt,
            'attemptlabel' => get_string('attemptheading', 'mod_casestudy', (int) $sublocal->attempt),
            'statuslabel' => get_string($statuskey, 'mod_casestudy'),
            'statusclass' => helper::get_status_info($sublocal->status, 'class'),
            'isdraft' => $sublocal->status === CASESTUDY_STATUS_DRAFT,
            'issubmitted' => in_array($sublocal->status, [
                CASESTUDY_STATUS_SUBMITTED,
                CASESTUDY_STATUS_IN_REVIEW,
                CASESTUDY_STATUS_RESUBMITTED,
                CASESTUDY_STATUS_RESUBMITTED_INREVIEW,
            ], true),
            'isgraded' => in_array($sublocal->status, [
                CASESTUDY_STATUS_SATISFACTORY,
                CASESTUDY_STATUS_UNSATISFACTORY,
            ], true),
            'canedit' => $ownerviewingdraft,
            'cansubmitdraft' => $ownerviewingdraft && $sublocal->status === CASESTUDY_STATUS_DRAFT,
            'editurl' => $editurl,
            'submitdrafturl' => $submitdrafturl,
        ];

        // Wire the lightbox AMD so every image inside the submission body
        // (file-field thumbnails AND inline rich-text images) is clickable to
        // enlarge. The module is idempotent across renders.
        $this->page->requires->js_call_amd('mod_casestudy/field_file', 'init');

        return $this->render_from_template('mod_casestudy/view_casestudy', $submissiondata);
    }

    /**
     * Render user navigation for grading
     *
     * @param object $cm Course module
     * @param object $casestudy Case study instance
     * @param object $currentsubmission Current submission
     * @return string HTML
     */
    protected function render_user_navigation($cm, $casestudy, $currentsubmission) {
        global $DB;

        // Get all submissions for this case study
        $context = \context_module::instance($cm->id);

        // Only show navigation if user has permission to view all submissions
        if (!has_capability('mod/casestudy:viewallsubmissions', $context)) {
            return '';
        }

        $groupid = groups_get_activity_group($cm, true);

        $sql = "SELECT s.id
                  FROM {casestudy_submissions} s
                  JOIN {user} u ON u.id = s.userid
                 WHERE s.casestudyid = :casestudyid";

        $params = ['casestudyid' => $casestudy->casestudyid];

        // Add group filter if needed
        if ($groupid) {
            $groupmembers = groups_get_members($groupid, 'u.id');
            if (!empty($groupmembers)) {
                [$insql, $inparams] = $DB->get_in_or_equal(array_keys($groupmembers), SQL_PARAMS_NAMED);
                $sql .= " AND s.userid $insql";
                $params = array_merge($params, $inparams);
            } else {
                return '';
            }
        }

        $sql .= " ORDER BY u.lastname ASC, u.firstname ASC, s.attempt DESC";

        $submissions = $DB->get_records_sql($sql, $params);
        $submissionids = array_keys($submissions);

        $currentindex = array_search($currentsubmission->id, $submissionids);
        if ($currentindex === false) {
            $currentindex = 0;
        }

        $templatecontext = [
            'currentsubmissionid' => $currentsubmission->id,
            'casestudyid' => $casestudy->casestudyid,
            'cmid' => $cm->id,
            'currentindex' => $currentindex + 1,
            'totalcount' => count($submissions),
            'isfirst' => $currentindex === 0,
            'islast' => $currentindex === count($submissions) - 1,
            'larrow' => $this->output->larrow(),
            'rarrow' => $this->output->rarrow(),
        ];

        return $this->render_from_template('mod_casestudy/grading_navigation', $templatecontext);
    }

    /**
     * Render previous/next navigation for a student cycling through their own submissions
     * (drafts + finalised attempts) ordered by attempt number.
     *
     * @param object $cm Course module.
     * @param \mod_casestudy\local\casestudy $casestudy Activity wrapper.
     * @param object $currentsubmission The submission currently being viewed.
     * @return string HTML, or '' when the student only has the one attempt.
     */
    protected function render_student_submission_navigation($cm, $casestudy, $currentsubmission) {
        global $DB, $USER;

        $submissions = $DB->get_records(
            'casestudy_submissions',
            ['casestudyid' => $casestudy->casestudyid, 'userid' => $USER->id],
            'attempt ASC, id ASC',
            'id, attempt, status'
        );

        if (count($submissions) < 2) {
            return '';
        }

        $ordered = array_values($submissions);
        $currentindex = 0;
        foreach ($ordered as $i => $row) {
            if ((int) $row->id === (int) $currentsubmission->id) {
                $currentindex = $i;
                break;
            }
        }

        $base = new \moodle_url('/mod/casestudy/view_casestudy.php', ['id' => $cm->id]);
        $previd = $currentindex > 0 ? $ordered[$currentindex - 1]->id : null;
        $nextid = $currentindex < count($ordered) - 1 ? $ordered[$currentindex + 1]->id : null;

        $templatecontext = [
            'cmid' => $cm->id,
            'currentindex' => $currentindex + 1,
            'totalcount' => count($ordered),
            'hasprev' => $previd !== null,
            'hasnext' => $nextid !== null,
            'prevurl' => $previd
                ? (new \moodle_url($base, ['submissionid' => $previd]))->out(false) : '',
            'nexturl' => $nextid
                ? (new \moodle_url($base, ['submissionid' => $nextid]))->out(false) : '',
            'larrow' => $this->output->larrow(),
            'rarrow' => $this->output->rarrow(),
            'listurl' => (new \moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]))->out(false),
        ];

        return $this->render_from_template('mod_casestudy/student_navigation', $templatecontext);
    }

    /**
     * Format submission history for template display
     *
     * @param array $history Array of history records
     * @param \context|null $context Module context, used to resolve feedback image URLs
     * @return array Formatted history for template
     */
    private function format_submission_history($history, $context = null) {
        global $DB;

        $formatted = [];

        $statuslist = helper::get_status_list();
        foreach ($history as $item) {
            $submission = $item->submission;
            $grade = $item->grade;

            $statusinfo = \mod_casestudy\local\helper::get_status_info($submission->status);

            $status = $submission->status == CASESTUDY_STATUS_AWAITING_RESUBMISSION
                    ? get_string('requestresubmission', 'mod_casestudy') : $statuslist[$submission->status];

            $historyitem = [
                'attempt' => $item->attempt,
                'submissionnumber' => $item->submissionnumber ?? $item->attempt,
                'islatest' => $item->islatest,
                'status' => $status,
                'statusclass' => $statusinfo['class'],
                'timesubmitted' => $submission->timesubmitted ? userdate($submission->timesubmitted, get_string('strftimedatetime', 'langconfig')) : '-',
                'hasfeedback' => !empty($grade),
            ];

            if (!empty($grade)) {
                $status = $submission->status == CASESTUDY_STATUS_AWAITING_RESUBMISSION
                    ? get_string('requestresubmission', 'mod_casestudy') : $statuslist[$submission->status];
                $grader = $DB->get_record('user', ['id' => $grade->graderid]);
                $historyitem['feedback'] = $this->format_feedback_text($grade, $context);
                $historyitem['grader'] = fullname($grader);
                $historyitem['timegraded'] = userdate($grade->timemodified, get_string('strftimedatetime', 'langconfig'));
                $historyitem['gradestatus'] = $status;
                $historyitem['resubmissionrequested'] = !empty($grade->requestresubmission);
            }

            $formatted[] = $historyitem;
        }

        return $formatted;
    }

    /**
     * Format grader feedback for display, resolving embedded image URLs.
     *
     * Grader feedback is an editor field whose inline images live in the
     * 'feedback' filearea keyed by the grade id. The stored HTML should keep
     * @@PLUGINFILE@@ placeholders, but feedback saved before save_feedback()
     * tokenised the text kept the editor's draft URLs (draftfile.php/.../user/
     * draft/<id>/), and a course restore can also leave absolute pluginfile URLs
     * that carry the source site's context id. In all three cases the image never
     * renders without rewriting: the placeholder is left literal, the draft URL is
     * dead once the session draft is cleaned up, and a restored absolute URL 404s
     * against the old context. This mirrors richtext_field::display(): retarget any
     * stale feedback URL (draft or cross-context pluginfile) to @@PLUGINFILE@@,
     * then rewrite the placeholders to real pluginfile URLs in this context before
     * formatting.
     *
     * @param \stdClass $grade Grade record (provides ->id, ->feedback, ->feedbackformat).
     * @param \context|null $context Module context; when absent, feedback is formatted as-is.
     * @return string Formatted feedback HTML.
     */
    private function format_feedback_text($grade, $context) {
        $text = (string) $grade->feedback;
        $format = $grade->feedbackformat ?? FORMAT_HTML;

        if ($context === null || $text === '') {
            return format_text($text, $format);
        }

        $contextid = (int) $context->id;

        // Older feedback was saved without tokenising the editor's draft URLs, so the stored
        // HTML can still embed draftfile.php/.../user/draft/<id>/<filename> URLs (per-session
        // draft area, never backed up). These are always stale in stored content, so retarget
        // every one to @@PLUGINFILE@@ — the filename is kept and resolves against the feedback
        // files below. Both the slash form and the ?file= form (raw or %2F-encoded) are handled.
        $text = preg_replace(
            '~https?://[^"\'\s<>]+?/draftfile\.php(?:\?file=)?(?:/|%2F)\d+(?:/|%2F)user'
                . '(?:/|%2F)draft(?:/|%2F)\d+(?:/|%2F)~i',
            '@@PLUGINFILE@@/',
            $text
        );

        // Retarget stale absolute feedback pluginfile URLs (carrying a different context,
        // e.g. from a course restore) to the @@PLUGINFILE@@ placeholder so they resolve
        // against this context's files below. URLs already in the current context are left
        // untouched. Both the slash form and the ?file= form (raw or %2F-encoded) are handled.
        $text = preg_replace_callback(
            '~https?://[^"\'\s<>]+?/pluginfile\.php(?:\?file=)?(?:/|%2F)(\d+)'
                . '(?:/|%2F)mod_casestudy(?:/|%2F)feedback(?:/|%2F)\d+(?:/|%2F)~i',
            function ($matches) use ($contextid) {
                return ((int) $matches[1] === $contextid) ? $matches[0] : '@@PLUGINFILE@@/';
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

    /**
     * Get advanced grading preview for display
     *
     * @param object $cm Course module
     * @param int $submissionid Submission ID
     * @return string|null HTML preview or null
     */
    protected function get_advanced_grading_preview($cm, $submissionid) {
        $context = \context_module::instance($cm->id);
        $gradingmanager = get_grading_manager($context, 'mod_casestudy', 'submissions');

        // Check if advanced grading is enabled.
        if ($gradingmethod = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller($gradingmethod);

            if ($controller->is_form_defined()) {
                // Check if there's an existing grading instance.
                $existinginstance = $controller->get_current_instance(0, $submissionid);

                if ($existinginstance) {
                    // Show existing grading.
                    return $controller->render_grade($this->page, $submissionid, null, false, true);
                } else {
                    // Show preview of the grading form.
                    return $controller->render_preview($this->page);
                }
            }
        }

        return null;
    }

    /**
     * Renders an mform element from a template.
     *
     * @param HTML_QuickForm_element $element element
     * @param bool $required if input is required field
     * @param bool $advanced if input is an advanced field
     * @param string $error error message to display
     * @param bool $ingroup True if this element is rendered as part of a group
     * @return mixed string|bool
     */
    public function mform_element_filemanager($element, $required, $advanced, $error, $ingroup) {
        $templatename = 'core_form/element-' . $element->getType();
        if ($ingroup) {
            $templatename .= "-inline";
        }
        try {
            // We call this to generate a file not found exception if there is no template.
            // We don't want to call export_for_template if there is no template.
            \core\output\mustache_template_finder::get_template_filepath($templatename);

            if ($element instanceof \templatable) {
                $elementcontext = $element->export_for_template($this);

                $helpbutton = '';
                if (method_exists($element, 'getHelpButton')) {
                    $helpbutton = $element->getHelpButton();
                }
                $label = $element->getLabel();
                $text = '';
                if (method_exists($element, 'getText')) {
                    // There currently exists code that adds a form element with an empty label.
                    // If this is the case then set the label to the description.
                    if (empty($label)) {
                        $label = $element->getText();
                    } else {
                        $text = $element->getText();
                    }
                }

                // Generate the form element wrapper ids and names to pass to the template.
                // This differs between group and non-group elements.
                if ($element->getType() === 'group') {
                    // Group element.
                    // The id will be something like 'fgroup_id_NAME'. E.g. fgroup_id_mygroup.
                    $elementcontext['wrapperid'] = $elementcontext['id'];

                    // Ensure group elements pass through the group name as the element name.
                    $elementcontext['name'] = $elementcontext['groupname'];
                } else {
                    // Non grouped element.
                    // Creates an id like 'fitem_id_NAME'. E.g. fitem_id_mytextelement.
                    $elementcontext['wrapperid'] = 'fitem_' . $elementcontext['id'];
                }

                $context = [
                    'element' => $elementcontext,
                    'label' => $label,
                    'text' => $text,
                    'required' => $required,
                    'advanced' => $advanced,
                    'helpbutton' => $helpbutton,
                    'error' => $error,
                ];
                return [$context, $this->render_from_template($templatename, $context)];
            }
        } catch (\Exception $e) {
            // No template for this element.
            return false;
        }
    }
}
