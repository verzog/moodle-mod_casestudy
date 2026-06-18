<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Submissions table for Case Study module
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\local\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

use table_sql;
use moodle_url;
use pix_icon;
use core_table\dynamic as dynamic_table;
use mod_casestudy\local\helper;

/**
 * Dynamic table for displaying case study submissions
 */
class submission_table extends table_sql {

    /** @var object $cm Course module object */
    protected $cm;

    /** @var object $context Context object */
    protected $context;

    /** @var string $sesskey Session key for forms */
    protected $sesskey;

    /** @var int $groupid Group filter */
    protected $groupid;

    /** @var string $statusfilter Status filter */
    protected $statusfilter;

    /** @var int $userid User filter */
    protected $userid;

    protected $column_textsort = [];

    /**
     * Constructor
     *
     * @param string $uniqueid Unique identifier for the table
     * @param object $cm Course module
     * @param object $context Context
     * @param int $groupid Group filter (optional)
     * @param string $statusfilter Status filter (optional)
     */
    public function __construct($uniqueid, $cm, $context, $groupid = 0, $statusfilter = '') {
        parent::__construct($uniqueid);

        $this->cm = $cm;
        $this->context = $context;
        $this->sesskey = sesskey();
        $this->groupid = $groupid;
        $this->statusfilter = $statusfilter;

        $this->baseurl = new moodle_url('/mod/casestudy/view.php', ['id' => $cm->id]);
    }


    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $DB, $USER;

        $this->userid = optional_param('userid', null, PARAM_INT);
        if (empty($this->statusfilter)) {
            $this->statusfilter = optional_param('status', '', PARAM_ALPHAEXT);
            set_user_preference('casestudy_status_filter', $this->statusfilter);
        }

        if (empty($this->groupid)) {
            $this->groupid = optional_param('group', 0, PARAM_INT);
        }

        // Get fields marked as "Show in List view"
        $listfields = $DB->get_records('casestudy_fields', [
            'casestudyid' => $this->cm->instance, 'showlistview' => 1], 'sortorder ASC');


        $columns = ['fullname', 'groupname'];
        $headers = [get_string('fullname', 'core'), get_string('group')];

        // Add dynamic field columns
        foreach ($listfields as $field) {
            $columns[] = 'field_' . $field->id;
            $headers[] = format_string($field->name);
            $this->no_sorting('field_' . $field->id);
        }

        // Add standard columns
        $columns = array_merge($columns, ['submissioncount', 'status', 'timecreated', 'timemodified', 'actions']);
        $headers = array_merge($headers, [
            get_string('submissioncount', 'mod_casestudy'),
            get_string('status', 'core'),
            get_string('timecreated', 'mod_casestudy'),
            get_string('timemodified', 'mod_casestudy'),
            get_string('actions', 'core')
        ]);

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->no_sorting('fullname');
        $this->no_sorting('groupname');
        $this->no_sorting('submissioncount');
        $this->no_sorting('status');
        $this->no_sorting('actions'); // Disable sorting on actions column

        // Configure table properties
        $this->sortable(true, 'timesubmitted', SORT_DESC);
        $this->collapsible(false);
        $this->set_attribute('class', 'casestudy-submissions-table table table-striped table-hover');

        // Get group mode.
        $groupmode = groups_get_activity_groupmode($this->cm);

        $allowedgroups = groups_get_activity_allowed_groups($this->cm);

        // When a specific group is selected from dropdown.
        $selectedgroup = optional_param('group', 0, PARAM_INT);
        $this->groupid = $selectedgroup;

        $canaccessall = has_capability('moodle/site:accessallgroups', $this->context);

        // Build base SQL.
        $fields = 's.*, ' .
                'u.firstname, u.lastname, u.email, u.picture, u.imagealt,
                u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename, ' .
                'f.grade, f.feedback, f.timecreated as grademodified, ' .
                'c.timeclose';

        $from = '{casestudy_submissions} s
                JOIN {user} u ON u.id = s.userid
                JOIN {casestudy} c ON c.id = s.casestudyid
                LEFT JOIN {casestudy_grades} f ON f.submissionid = s.id';

        $where = 's.casestudyid = :casestudyid
                AND s.id NOT IN (
                    SELECT parentid FROM {casestudy_submissions}
                    WHERE parentid IS NOT NULL
                )';

        $params = ['casestudyid' => $this->cm->instance];


        if ($groupmode == SEPARATEGROUPS || $groupmode == VISIBLEGROUPS) {
           if ($this->groupid > 0) {
                $from .= ' JOIN {groups_members} gm ON gm.userid = u.id';
                $where .= ' AND gm.groupid = :selectedgroup';
                $params['selectedgroup'] = $this->groupid;
            } else {

                if (!$canaccessall) {
                    if (empty($allowedgroups)) {
                        // No allowed groups → return no users
                        $where .= ' AND 1 = 0';
                    } else {
                        // Filter users by allowed groups
                        $usergroupids = array_keys($allowedgroups);
                        $from .= ' JOIN {groups_members} gm2 ON gm2.userid = u.id';
                        list($ingroupSQL, $groupParams) = $DB->get_in_or_equal($usergroupids, SQL_PARAMS_NAMED);
                        $where .= " AND gm2.groupid $ingroupSQL";
                        $params = array_merge($params, $groupParams);
                    }
                }
            }
        }
        if (!empty($this->statusfilter)) {
            $where .= ' AND s.status = :statusfilter';
            $params['statusfilter'] = $this->statusfilter;
        }

        if (!has_capability('mod/casestudy:viewallsubmissions', $this->context)) {
            $where .= ' AND s.userid = :currentuserid';
            $params['currentuserid'] = $USER->id;
        }

        if (!empty($this->userid)) {
            $where .= ' AND s.userid = :useridfilter';
            $params['useridfilter'] = $this->userid;
        }

        $this->set_sql($fields, $from, $where, $params);

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }

    /**
     * @return string SQL fragment that can be used in an ORDER BY clause.
     */
    public function get_sql_sort() {
        $columns = $this->get_sort_columns();

        foreach ($columns as $column => $sortdirection) {
            if (stripos($column, 'field_') === 0) {
                $fieldid = (int)str_replace('field_', '', $column);
                $columns["(SELECT content FROM {casestudy_content} WHERE submissionid = s.id AND fieldid = $fieldid)"] = $sortdirection;
                unset($columns[$column]);
            }
        }

        return self::construct_order_by($columns, $this->column_textsort ?: []);
    }

    /**
     * Check if user has capability to view submissions
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('mod/casestudy:viewsubmissions', $this->context) ||
               has_capability('mod/casestudy:viewallsubmissions', $this->context);
    }

    /**
     * Print casestudy stats
     */
    protected function print_casestudy_stats() {
        // TODO: Implement the stats of counts of casestudy submissions in different statuses.
    }

    /**
     * This function is not part of the public api.
     */
    public function start_html() {
        global $OUTPUT;

        // Render the dynamic table header.
        echo $this->get_dynamic_table_html_start();

        // Render button to allow user to reset table preferences.
        echo $this->render_reset_button();
        echo $this->print_casestudy_stats();

        // Paging bar.
        if ($this->use_pages) {
            $pagingbar = new \paging_bar($this->totalrows, $this->currpage, $this->pagesize, $this->baseurl);
            $pagingbar->pagevar = $this->request[TABLE_VAR_PAGE];
            echo $OUTPUT->render($pagingbar);
        }

        if (in_array(TABLE_P_TOP, $this->showdownloadbuttonsat)) {
            echo $this->download_buttons();
        }

        $this->wrap_html_start();
        // Start of main data table.

        if ($this->responsive) {
            echo \html_writer::start_tag('div', ['class' => 'no-overflow']);
        }

        echo \html_writer::start_tag('table', $this->attributes) . $this->render_caption();
    }

    /**
     * Format student column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_fullname($row) {
        global $OUTPUT, $COURSE;


        $name = fullname($row, has_capability('moodle/site:viewfullnames', $this->get_context()));
        if ($this->download) {
            return $name;
        }

        $userid = $row->userid;
        if ($COURSE->id == SITEID) {
            $profileurl = new moodle_url('/user/profile.php', ['id' => $userid]);
        } else {
            $profileurl = new moodle_url(
                '/user/view.php',
                ['id' => $userid, 'course' => $COURSE->id]
            );
        }

        $user = (object)[
            'id' => $row->userid,
            'firstname' => $row->firstname,
            'lastname' => $row->lastname,
            'email' => $row->email,
            'picture' => $row->picture,
            'imagealt' => $row->imagealt,
            'firstnamephonetic' => $row->firstnamephonetic,
            'lastnamephonetic' => $row->lastnamephonetic,
            'middlename' => $row->middlename,
            'alternatename' => $row->alternatename,
        ];

        $userpicture = $OUTPUT->user_picture($user, ['size' => 35, 'courseid' => $this->cm->course]);

        $namelink = \html_writer::link($profileurl, $name);
        return \html_writer::div(
            $userpicture . ' ' . $namelink,
            'user-info d-flex align-items-center'
        );
    }

    /**
     * Format group mode column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_groupname($row) {
        global $DB;

        // Get course module.
        if (!$cm = get_coursemodule_from_instance('casestudy', $row->casestudyid)) {
            return '-';
        }

        // Get group mode of the activity.
        $groupmode = groups_get_activity_groupmode($cm);

        // Get all groups for this user in this course.
        $groups = groups_get_all_groups($cm->course, $row->userid);

        if (empty($groups)) {
            return '-';
        }

        // Collect group names.
        $groupnames = array_map(function($g) {
            return format_string($g->name);
        }, $groups);

        // Return as comma separated list.
        return implode(', ', $groupnames);
    }

    /**
     * Format status column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_status($row) {
        $statusclass = 'badge ';
        $iconclass = 'fa ';

        $info = helper::get_status_info($row->status);
        $statusclass .= $info['statusclass'];
        $iconclass .= $info['iconclass'];

        $statustext = get_string('status_' . $row->status, 'mod_casestudy');
        $icon = \html_writer::tag('i', '', ['class' => $iconclass]);

        $reattemptstatus = '';
        if (!empty($row->parentid)) {
            $reattemptstatus = \html_writer::span(
                get_string('reattempt', 'mod_casestudy'),
                'badge badge-info ml-2',
                ['title' => get_string('titlesubmissionreattempt', 'mod_casestudy')]
            );
        }

        return \html_writer::span(
            $icon . ' ' . $statustext,
            $statusclass
        ) . $reattemptstatus;
    }

    /**
     * Format attempt column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_attempt($row) {
        return \html_writer::span(
            $row->attempt,
            'badge badge-light',
            ['title' => get_string('attemptnumber', 'mod_casestudy', $row->attempt)]
        );
    }

    /**
     * Format submission count column - shows total submissions in the chain
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_submissioncount($row) {
        global $DB;

        // Find the root submission (original parent) by going up the chain
        $rootid = $this->get_root_submission_id($row->id);

        // Count all submissions in this chain starting from root
        $count = $this->count_submission_chain($rootid);

        // Get effective max attempts setting for this user (includes overrides)
        $casestudy = $DB->get_record('casestudy', ['id' => $this->cm->instance]);
        $effective = casestudy_get_effective_settings($casestudy, $row->userid);
        $maxattempts = !empty($effective->maxattempts) ? $effective->maxattempts : 0;

        // Determine badge class based on whether limit is reached
        if ($maxattempts > 0 && $count >= $maxattempts) {
            $badgeclass = 'badge badge-danger';
            $title = get_string('resubmissionlimitreached', 'mod_casestudy', $count);
            $displaytext = $count . ' / ' . $maxattempts;
        } else if ($maxattempts > 0) {
            $badgeclass = 'badge badge-info';
            $title = get_string('submissionsof', 'mod_casestudy', (object)['count' => $count, 'max' => $maxattempts]);
            $displaytext = $count . ' / ' . $maxattempts;
        } else {
            $badgeclass = 'badge badge-secondary';
            $title = get_string('totalsubmissions', 'mod_casestudy', $count);
            $displaytext = $count;
        }

        return \html_writer::span(
            $displaytext,
            $badgeclass,
            ['title' => $title]
        );
    }

    /**
     * Get the root submission ID by following parent chain upwards
     *
     * @param int $submissionid Current submission ID
     * @return int Root submission ID
     */
    private function get_root_submission_id($submissionid) {
        global $DB;

        $current = $DB->get_record('casestudy_submissions', ['id' => $submissionid], 'id, parentid');
        if (!$current) {
            return $submissionid;
        }

        // Follow parent chain up to root
        while (!empty($current->parentid)) {
            $parent = $DB->get_record('casestudy_submissions', ['id' => $current->parentid], 'id, parentid');
            if (!$parent) {
                break;
            }
            $current = $parent;
        }

        return $current->id;
    }

    /**
     * Count all submissions in a chain (root + all descendants)
     *
     * @param int $rootid Root submission ID
     * @return int Total count of submissions in the chain
     */
    private function count_submission_chain($rootid) {
        global $DB;
        $count = 1;
        $count += $this->count_submission_children($rootid);

        return $count;
    }

    /**
     * Recursively count all child submissions
     *
     * @param int $parentid Parent submission ID
     * @return int Count of child submissions
     */
    private function count_submission_children($parentid) {
        global $DB;

        $children = $DB->get_records('casestudy_submissions', ['parentid' => $parentid], '', 'id');
        $count = count($children);

        // Recursively count grandchildren
        foreach ($children as $child) {
            $count += $this->count_submission_children($child->id);
        }

        return $count;
    }

    /**
     * Format time created column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_timecreated($row) {
        if (empty($row->timecreated)) {
            return \html_writer::span('-', 'text-muted');
        }
        return userdate($row->timecreated, get_string('strftimedatetime'));
    }

    /**
     * Format time modified column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_timemodified($row) {
        if (empty($row->timemodified)) {
            return \html_writer::span('-', 'text-muted');
        }
        return userdate($row->timemodified, get_string('strftimedatetime'));
    }

    /**
     * Format time submitted column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_timesubmitted($row) {
        if (empty($row->timesubmitted)) {
            return \html_writer::span('-', 'text-muted');
        }
        return userdate($row->timesubmitted, get_string('strftimedate'));
    }

    /**
     * Format grade column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_grade($row) {
        // Check if due date has passed and no grade assigned
        if (empty($row->grade)) {
            // If timeclose is set and has passed, show as unsatisfactory
            if (!empty($row->timeclose) && $row->timeclose > 0 && time() > $row->timeclose) {
                $gradetext = get_string('grade_unsatisfactory', 'mod_casestudy');
                $icon = \html_writer::tag('i', '', ['class' => 'fa fa-times']);
                return \html_writer::span(
                    $icon . ' ' . $gradetext,
                    'text-danger font-weight-bold'
                );
            }
            // Otherwise show not graded
            return \html_writer::span(get_string('notgraded', 'core_grades'), 'text-muted font-italic');
        }

        $gradeclass = '';
        $iconclass = '';

        switch ($row->grade) {
            case 'satisfactory':
                $gradeclass = 'text-success';
                $iconclass = 'fa fa-check';
                break;
            case 'unsatisfactory':
                $gradeclass = 'text-danger';
                $iconclass = 'fa fa-times';
                break;
        }

        $gradetext = get_string('grade_' . $row->grade, 'mod_casestudy');
        $icon = \html_writer::tag('i', '', ['class' => $iconclass]);

        return \html_writer::span(
            $icon . ' ' . $gradetext,
            $gradeclass . ' font-weight-bold'
        );
    }


    public function is_reattempt_created($row) {
        global $DB;

        return $DB->record_exists('casestudy_submissions', [
            'casestudyid' => $this->cm->instance,
            'userid' => $row->userid,
            'parentid' => $row->id,
        ]);
    }

    /**
     * Format actions column
     *
     * @param object $row Table row
     * @return string HTML output
     */
    public function col_actions($row) {
        global $OUTPUT, $USER;

        $actions = [];
        $isownsubmission = $row->userid == $USER->id ? true : false;

        if (
            $isownsubmission
            && in_array($row->status, [CASESTUDY_STATUS_NEW, CASESTUDY_STATUS_DRAFT, CASESTUDY_STATUS_AWAITING_RESUBMISSION])
        ) {

            $submissionurl =  new moodle_url('/mod/casestudy/submission.php', ['id' => $this->cm->id, 'submissionid' => $row->id]);
            $editicon = new pix_icon('i/customfield', get_string('edit'));
            $title = get_string('edit');

            $recreated = false;

            // If awaiting resubmission, set action to reattempt.
            if ($row->status == CASESTUDY_STATUS_AWAITING_RESUBMISSION) {

                $submissionurl->param('action', 'reattempt');
                $submissionurl->param('sesskey', $this->sesskey);
                $editicon = new pix_icon('i/reload', get_string('recreate', 'mod_casestudy'));
                $title = get_string('recreate', 'mod_casestudy');

                $recreated = $this->is_reattempt_created($row);
            }

            if (!$recreated) {
                $actions[] = $OUTPUT->action_icon(
                    $submissionurl, $editicon, null, ['title' => $title, 'class' => 'btn btn-sm btn-outline-secondary']);
            }
        }

        // View submission action.
        $viewurl = new moodle_url('/mod/casestudy/view_casestudy.php', ['id' => $this->cm->id, 'submissionid' => $row->id]);

        // Grade action (if user has grading capability and submission has been submitted).
        // Only show grade button for submissions that are ready to be graded (not new/draft).
        $gradeablestatuses = [
            CASESTUDY_STATUS_SUBMITTED,
            CASESTUDY_STATUS_IN_REVIEW,
            CASESTUDY_STATUS_RESUBMITTED,
            CASESTUDY_STATUS_RESUBMITTED_INREVIEW,
            CASESTUDY_STATUS_AWAITING_RESUBMISSION,
            CASESTUDY_STATUS_SATISFACTORY,
            CASESTUDY_STATUS_UNSATISFACTORY,
        ];

        if (has_capability('mod/casestudy:grade', $this->context) && in_array($row->status, $gradeablestatuses)) {
            $viewurl->param('mode', 'grade');
            $actions[] = $OUTPUT->action_icon(
                $viewurl, new pix_icon('t/grades', get_string('grade', 'mod_casestudy')), null,
                ['title' => get_string('grade', 'mod_casestudy'), 'class' => 'btn btn-sm btn-outline-success']
            );

        }

        if ($isownsubmission || has_capability('mod/casestudy:viewallsubmissions', $this->context)) {
            // View action (if user owns submission or has view all capability).
            $viewurl->param('mode', 'preview');
            $actions[] = $OUTPUT->action_icon(
                $viewurl, new pix_icon('t/preview', get_string('view')), null,
                ['title' => get_string('viewcasestudy', 'mod_casestudy'), 'class' => 'btn btn-sm btn-outline-primary']
            );
        }

        // Delete action - use submission_manager to check if user can delete
        $submissionmanager = \mod_casestudy\local\submission_manager::instance(
            $this->cm->instance,
            null,
            $this->cm
        );

        if ($submissionmanager->can_delete_submission($row, $USER->id)) {
            $deleteurl = new moodle_url('/mod/casestudy/submission.php', [
                'id' => $this->cm->id, 'action' => 'delete', 'submissionid' => $row->id, 'sesskey' => $this->sesskey
            ]);

            if (class_exists('core\output\actions\confirm_action')) {
                $confirmaction = new \core\output\actions\confirm_action(get_string('confirmdeletecasestudy', 'mod_casestudy'));
            } else {
                $confirmaction = new \confirm_action(get_string('confirmdeletecasestudy', 'mod_casestudy'));
            }

            $actions[] = $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')),
                $confirmaction, ['title' => get_string('delete', 'core'), 'class' => 'btn btn-sm btn-outline-danger']);
        }

        return \html_writer::div(implode(' ', $actions), 'btn-group', ['role' => 'group']);
    }

    /**
     * Override to add custom CSS classes and attributes to table rows
     *
     * @param object $row
     * @return string
     */
    public function get_row_class($row) {
        $classes = [];

        // Add status-specific classes
        $classes[] = 'submission-status-' . str_replace('_', '-', $row->status);

        // Add late submission class if applicable
        if ($row->status === 'submitted' && !empty($row->timesubmitted)) {
            // $classes[] = 'submission-late';
        }

        return implode(' ', $classes);
    }

    /**
     * Override to add custom attributes to table rows
     *
     * @param object $row
     * @return array
     */
    public function get_row_attributes($row) {
        return [
            'data-submission-id' => $row->id,
            'data-user-id' => $row->userid,
            'data-status' => $row->status,
            'data-attempt' => $row->attempt
        ];
    }

    /**
     * Set group filter
     *
     * @param int $groupid Group ID
     */
    public function set_group_filter($groupid) {
        $this->groupid = $groupid;
    }

    /**
     * Set status filter
     *
     * @param string $status Status
     */
    public function set_status_filter($status) {
        $this->statusfilter = $status;
    }

    /**
     * Handle dynamic field columns
     *
     * @param string $column Column name
     * @param object $row Table row
     * @return string HTML output
     */
    public function other_cols($column, $row) {
        global $DB;

        // Handle dynamic field columns
        if (strpos($column, 'field_') === 0) {
            $fieldid = (int)str_replace('field_', '', $column);

            $row->fieldid = $fieldid; // Pass fieldid to row for use in field type class.

            // Get field information
            $field = $DB->get_record('casestudy_fields', ['id' => $fieldid]);
            if (!$field) {
                return \html_writer::span('-', 'text-muted');
            }

            // Get field content for this submission
            $content = $DB->get_record('casestudy_content', [
                'submissionid' => $row->id,
                'fieldid' => $fieldid
            ]);

            if (!$content) {
                return \html_writer::span('-', 'text-muted');
            }

            // Use field type class to format content for list view
            try {
                $fieldmanager = \mod_casestudy\local\field_manager::instance($this->cm->instance);
                $fieldtype = $fieldmanager->get_field_type_class($field->type, $field);

                // Use the field type's list view formatting if available
                if (method_exists($fieldtype, 'format_for_list_view')) {
                    return $fieldtype->format_for_list_view($content);
                } else {
                    // Fallback to display value method
                    return $fieldtype->get_list_display($content->content, $row);
                }
            } catch (Exception $e) {
                // Fallback if field type class not found
                return \html_writer::span(format_string($content->content));
            }
        }

        return '';
    }
}