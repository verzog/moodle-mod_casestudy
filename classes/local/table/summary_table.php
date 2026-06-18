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
 * Summary table for Case Study activity - shows completion status for all students
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

use table_sql;
use moodle_url;
use html_writer;
use context_module;

/**
 * Summary table class for displaying student completion summaries
 */
class summary_table extends table_sql {

    /** @var object Course module */
    protected $cm;

    /** @var object Context */
    protected $context;

    /** @var object Case study instance */
    protected $casestudy;

    /** @var array Category fields for completion */
    protected $categoryfields = [];

    /** @var array Category completion rules */
    protected $completionrules = [];

    /** @var int Group ID for filtering */
    protected $groupid;

    /**
     * Constructor
     *
     * @param string $uniqueid Unique ID for this table
     * @param object $cm Course module
     * @param object $context Context
     * @param int $groupid Group ID for filtering
     */
    public function __construct($uniqueid, $cm, $context, $groupid = 0) {
        global $DB;

        parent::__construct($uniqueid);

        $this->cm = $cm;
        $this->context = $context;
        $this->groupid = $groupid;
        $this->casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

        $this->baseurl = new moodle_url('/mod/casestudy/summaries.php', ['id' => $cm->id]);

        // Load completion rules from the new table.
        $this->completionrules = $DB->get_records('casestudy_completion_rules',
            ['casestudyid' => $this->casestudy->id, 'enabled' => 1], 'sortorder ASC');

        $columns = ['fullname'];

        // Add column for total satisfactory if enabled.
        $totalrule = null;
        foreach ($this->completionrules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_TOTAL) {
                $totalrule = $rule;
                $columns[] = 'totalsatisfactory';
                break;
            }
        }

        // Add columns for each category completion rule.
        foreach ($this->completionrules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY) {
                $columns[] = 'categoryrule_' . $rule->id;
            }
        }

        $columns[] = 'grade';
        $columns[] = 'actions';

        $this->define_columns($columns);

        $headers = [get_string('student', 'mod_casestudy')];

        // Add header for total satisfactory.
        if ($totalrule) {
            $totalheader = get_string('totalsatisfactory', 'mod_casestudy') . '<br/>' .
                html_writer::tag('small', '/' . $totalrule->count, ['class' => 'text-muted']);
            $headers[] = $totalheader;
        }

        // Add headers for each category completion rule.
        foreach ($this->completionrules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY && $rule->fieldid) {
                $field = $DB->get_record('casestudy_fields', ['id' => $rule->fieldid]);
                if ($field) {
                    $fieldname = format_string($field->name);

                    // Get the actual value for this rule.
                    $valuedisplay = '';
                    if (!empty($rule->categoryvalue)) {
                        // Convert index to actual value.
                        $fields = $DB->get_records('casestudy_fields',
                            ['casestudyid' => $this->casestudy->id, 'category' => 1], 'sortorder ASC');

                        $optionindex = 1;
                        foreach ($fields as $f) {
                            $values = $f->param1 ? json_decode($f->param1, true) : [];
                            if (is_array($values)) {
                                foreach ($values as $v) {
                                    if ($optionindex == $rule->categoryvalue && $f->id == $rule->fieldid) {
                                        $valuedisplay = '<br/>' . html_writer::tag('small', $v, ['class' => 'text-muted']);
                                        break 2;
                                    }
                                    $optionindex++;
                                }
                            }
                        }
                    }

                    $fieldname .= $valuedisplay . '<br/>' . html_writer::tag(
                        'small',
                        get_string('satisfactory', 'mod_casestudy') . ' /' . $rule->count,
                        ['class' => 'text-muted']
                    );

                    $headers[] = $fieldname;
                }
            }
        }

        $headers[] = get_string('grade', 'casestudy');
        $headers[] = ''; // Actions.

        $this->define_headers($headers);

        $this->collapsible(false);
        $this->sortable(false);
        $this->pageable(true);
        $this->is_downloading('', '', '');

        // Prevent sorting on all columns to avoid SQL errors with dynamic columns.
        $this->no_sorting('fullname');
        if ($totalrule) {
            $this->no_sorting('totalsatisfactory');
        }
        $this->no_sorting('grade');
        $this->no_sorting('actions');

        // Also prevent sorting on all category rule columns.
        foreach ($this->completionrules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY) {
                $this->no_sorting('categoryrule_' . $rule->id);
            }
        }

        $this->set_sql_and_params();
    }


    /**
     * Set up SQL query
     */
    protected function set_sql_and_params() {
        global $DB, $USER;

        $fields = 'u.id, u.*, ' . $DB->sql_fullname('u.firstname', 'u.lastname') . ' AS fullname';
        $from = '{user} u';
        $params = [];
        list($enrolledsql, $enrolledparams) = get_enrolled_sql($this->context, 'mod/casestudy:submit', 0, true);

        $where = "u.id IN ($enrolledsql)";
        $params = $enrolledparams;

        $groupmode = groups_get_activity_groupmode($this->cm);
        $allowedgroups = groups_get_activity_allowed_groups($this->cm);

        // Check if user can view ALL groups.
        $canaccessall = has_capability('moodle/site:accessallgroups', $this->context);

        $groups = groups_get_all_groups($this->cm->course, $USER->id);

        if (!empty($this->groupid) && $this->groupid > 0) {
            if ($groupmode == SEPARATEGROUPS && $allowedgroups !== false) {
                if (!isset($allowedgroups[$this->groupid])) {
                    $where .= " AND 1=0";
                }
            }

            $from .= " JOIN {groups_members} gm ON gm.userid = u.id";
            $where .= " AND gm.groupid = :selgroup";
            $params['selgroup'] = $this->groupid;
        } else {
            if ($canaccessall || $groupmode == NOGROUPS) {
                // Show all enrolled users — no extra SQL needed
            } else {
                if (empty($allowedgroups)) {
                    $where .= " AND 1 = 0";
                } else {
                    $usergroupids = array_keys($allowedgroups);

                    $from .= " JOIN {groups_members} gm2 ON gm2.userid = u.id";
                    list($ingroupSql, $groupParams) = $DB->get_in_or_equal($usergroupids, SQL_PARAMS_NAMED);

                    $where .= " AND gm2.groupid $ingroupSql";
                    $params = array_merge($params, $groupParams);
                }
            }
        }

        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql("SELECT COUNT(DISTINCT u.id) FROM $from WHERE $where", $params);
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

        // Paging bar.
        if ($this->use_pages) {
            $pagingbar = new \paging_bar($this->totalrows, $this->currpage, $this->pagesize, $this->baseurl);
            $pagingbar->pagevar = $this->request[TABLE_VAR_PAGE];
            echo $OUTPUT->render($pagingbar);
        }

        $this->wrap_html_start();

        if ($this->responsive) {
            echo \html_writer::start_tag('div', ['class' => 'no-overflow']);
        }

        echo \html_writer::start_tag('table', $this->attributes) . $this->render_caption();
    }

    /**
     * Render fullname column with link to user submissions
     *
     * @param object $row Table row
     * @return string HTML
     */
    public function col_fullname($row) {
        global $OUTPUT, $COURSE;

        $name = fullname($row, has_capability('moodle/site:viewfullnames', $this->get_context()));
        if ($this->download) {
            return $name;
        }

        $user = (object)[
            'id' => $row->id,
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

        // Link to user profile page
        $url = new moodle_url('/user/view.php', [
            'id' => $row->id,
            'course' => $this->cm->course
        ]);

        $namelink = \html_writer::link($url, $name);

        return \html_writer::div(
            $userpicture . ' ' . $namelink,
            'user-info d-flex align-items-center'
        );
    }

    /**
     * Render total satisfactory column
     *
     * @param object $row Table row
     * @return string HTML
     */
    public function col_totalsatisfactory($row) {
        global $DB;

        // Find the total satisfactory rule.
        $totalrule = null;
        foreach ($this->completionrules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_TOTAL) {
                $totalrule = $rule;
                break;
            }
        }

        if (!$totalrule) {
            return '--';
        }

        $count = $DB->count_records('casestudy_submissions', [
            'casestudyid' => $this->casestudy->id,
            'userid' => $row->id,
            'status' => CASESTUDY_STATUS_SATISFACTORY
        ]);

        $required = $totalrule->count;
        $text = html_writer::tag('span', $count) . html_writer::tag('span', ' / ' .  $required);

        if ($count >= $required) {
            return html_writer::tag('div', $text, [
                'style' => 'background-color: #d4edda; padding: 5px; border-radius: 3px; text-align: center;'
            ]);
        }

        return html_writer::tag('div', $text, ['style' => 'text-align: center;']);
    }

    /**
     * Render category completion rule column
     *
     * @param string $column Column name
     * @param object $row Table row
     * @return string HTML
     */
    public function other_cols($column, $row) {
        global $DB;

        // Check if this is a category rule column.
        if (strpos($column, 'categoryrule_') === 0) {
            $ruleid = str_replace('categoryrule_', '', $column);

            // Find the rule.
            $rule = null;
            foreach ($this->completionrules as $r) {
                if ($r->id == $ruleid && $r->ruletype == CASESTUDY_COMPLETION_CATEGORY) {
                    $rule = $r;
                    break;
                }
            }

            if (!$rule) {
                return '--';
            }

            // Convert the stored global index to the actual option value.
            $actualvalue = null;
            if (!empty($rule->categoryvalue)) {
                $fields = $DB->get_records('casestudy_fields',
                    ['casestudyid' => $this->casestudy->id, 'category' => 1], 'sortorder ASC', 'id, param1');

                $optionindex = 1;
                foreach ($fields as $field) {
                    $values = $field->param1 ? json_decode($field->param1, true) : [];
                    if (is_array($values)) {
                        foreach ($values as $v) {
                            if ($optionindex == $rule->categoryvalue && $field->id == $rule->fieldid) {
                                $actualvalue = $v;
                                break 2;
                            }
                            $optionindex++;
                        }
                    }
                }
            }

            // Build WHERE clause based on whether we need a specific value or any value.
            if (!empty($actualvalue)) {
                $contentwhere = 'AND c.content = :content';
                $params = [
                    'casestudyid' => $this->casestudy->id,
                    'userid' => $row->id,
                    'status' => CASESTUDY_STATUS_SATISFACTORY,
                    'fieldid' => $rule->fieldid,
                    'content' => $actualvalue,
                ];
            } else {
                $contentwhere = 'AND c.content IS NOT NULL AND c.content != \'\'';
                $params = [
                    'casestudyid' => $this->casestudy->id,
                    'userid' => $row->id,
                    'status' => CASESTUDY_STATUS_SATISFACTORY,
                    'fieldid' => $rule->fieldid,
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

            $required = $rule->count;
            $text = html_writer::tag('span', $count) . html_writer::tag('span', ' / ' .  $required);

            if ($count >= $required) {
                return html_writer::tag('div', $text, [
                    'style' => 'background-color: #d4edda; padding: 5px; border-radius: 3px; text-align: center;'
                ]);
            }

            return html_writer::tag('div', $text, ['style' => 'text-align: center;']);
        }

        return '';
    }

    /**
     * Render grade column
     *
     * @param object $row Table row
     * @return string HTML
     */
    public function col_grade($row) {
        global $DB;

        // Check completion status based on aggregation method.
        $iscomplete = $this->check_user_completion($row->id);

        if ($iscomplete) {
            return html_writer::tag('span', get_string('satisfactory', 'mod_casestudy'),
                ['class' => 'badge badge-success bg-success']);
        }

        // Check if due date has passed.
        if (!empty($this->casestudy->timeclose) && $this->casestudy->timeclose < time()) {
            return html_writer::tag('span', get_string('unsatisfactory', 'mod_casestudy'),
                ['class' => 'badge badge-danger bg-danger']);
        }

        return '--';
    }

    /**
     * Check if user has completed the activity
     *
     * @param int $userid User ID
     * @return bool True if completed
     */
    protected function check_user_completion($userid) {
        global $DB;

        if (empty($this->completionrules)) {
            return false;
        }

        $results = [];

        // Check each completion rule.
        foreach ($this->completionrules as $rule) {
            if ($rule->ruletype == CASESTUDY_COMPLETION_TOTAL) {
                // Check total satisfactory.
                $count = $DB->count_records('casestudy_submissions', [
                    'casestudyid' => $this->casestudy->id,
                    'userid' => $userid,
                    'status' => CASESTUDY_STATUS_SATISFACTORY
                ]);
                $results[] = ($count >= $rule->count);
            } else if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY) {
                // Check category completion.
                $actualvalue = null;
                if (!empty($rule->categoryvalue)) {
                    $fields = $DB->get_records('casestudy_fields',
                        ['casestudyid' => $this->casestudy->id, 'category' => 1], 'sortorder ASC', 'id, param1');

                    $optionindex = 1;
                    foreach ($fields as $field) {
                        $values = $field->param1 ? json_decode($field->param1, true) : [];
                        if (is_array($values)) {
                            foreach ($values as $v) {
                                if ($optionindex == $rule->categoryvalue && $field->id == $rule->fieldid) {
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
                        'casestudyid' => $this->casestudy->id,
                        'userid' => $userid,
                        'status' => CASESTUDY_STATUS_SATISFACTORY,
                        'fieldid' => $rule->fieldid,
                        'content' => $actualvalue,
                    ];
                } else {
                    $contentwhere = 'AND c.content IS NOT NULL AND c.content != \'\'';
                    $params = [
                        'casestudyid' => $this->casestudy->id,
                        'userid' => $userid,
                        'status' => CASESTUDY_STATUS_SATISFACTORY,
                        'fieldid' => $rule->fieldid,
                    ];
                }

                $count = $DB->count_records_sql("
                    SELECT COUNT(DISTINCT s.id)
                    FROM {casestudy_submissions} s
                    JOIN {casestudy_content} c ON s.id = c.submissionid
                    WHERE s.casestudyid = :casestudyid AND s.userid = :userid AND s.status = :status AND c.fieldid = :fieldid
                    $contentwhere",
                    $params
                );

                $results[] = ($count >= $rule->count);
            }
        }

        if (empty($results)) {
            return false;
        }

        $aggregation = isset($this->casestudy->completionaggr) ? $this->casestudy->completionaggr : CASESTUDY_COMPLETION_ALL;

        if ($aggregation == CASESTUDY_COMPLETION_ALL) {
            return !in_array(false, $results, true);
        } else {
            return in_array(true, $results, true);
        }
    }

    /**
     * Render actions column
     *
     * @param object $row Table row
     * @return string HTML
     */
    public function col_actions($row) {
        $url = new moodle_url('/mod/casestudy/view.php', [
            'id' => $this->cm->id,
            'userid' => $row->id
        ]);

        return html_writer::link($url, get_string('view'), [
            'class' => 'btn btn-primary btn-sm'
        ]);
    }
}
