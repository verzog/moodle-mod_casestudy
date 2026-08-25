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
 * Reports table for Case Study activity - per-user submission counts by status.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\table;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Staff-facing report table: one row per enrolled user with submission counts by status.
 */
class report_table extends \table_sql {
    /** @var object Course module. */
    protected $cm;

    /** @var object Context. */
    protected $context;

    /** @var int Group ID for filtering. */
    protected $groupid;

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique ID for this table
     * @param object $cm Course module
     * @param object $context Context
     * @param int $groupid Group ID for filtering
     */
    public function __construct($uniqueid, $cm, $context, $groupid = 0) {
        parent::__construct($uniqueid);

        $this->cm = $cm;
        $this->context = $context;
        $this->groupid = $groupid;

        $this->baseurl = new moodle_url('/mod/casestudy/reports.php', ['id' => $cm->id]);

        $columns = [
            'fullname',
            'groups',
            'cntsubmitted',
            'cntunmarked',
            'cntmarked',
            'cntsatisfactory',
            'cntunsatisfactory',
        ];
        $headers = [
            get_string('fullname', 'core'),
            get_string('group', 'core'),
            get_string('reportcountsubmitted', 'mod_casestudy'),
            get_string('reportcountunmarked', 'mod_casestudy'),
            get_string('reportcountmarked', 'mod_casestudy'),
            get_string('reportcountsatisfactory', 'mod_casestudy'),
            get_string('reportcountunsatisfactory', 'mod_casestudy'),
        ];

        $this->define_columns($columns);
        $this->define_headers($headers);

        // Derived and per-row-computed columns cannot be sorted at the SQL level safely.
        $this->sortable(false);
        $this->no_sorting('groups');
        $this->no_sorting('cntsubmitted');
        $this->no_sorting('cntunmarked');
        $this->no_sorting('cntmarked');
        $this->no_sorting('cntsatisfactory');
        $this->no_sorting('cntunsatisfactory');

        $this->set_attribute('class', 'generaltable casestudy-report-table');
        $this->collapsible(false);

        $this->set_sql_and_params();
    }

    /**
     * Build the enrolled-users query with per-user submission counts.
     */
    protected function set_sql_and_params() {
        global $DB, $USER;

        $submitted = 'SELECT COUNT(*) FROM {casestudy_submissions} sub
                        WHERE sub.casestudyid = :cs_sub AND sub.userid = u.id
                          AND sub.status NOT IN (:st_new, :st_draft)';
        $satisfactory = 'SELECT COUNT(*) FROM {casestudy_submissions} sub2
                           WHERE sub2.casestudyid = :cs_sat AND sub2.userid = u.id
                             AND sub2.status = :st_sat';
        $unsatisfactory = 'SELECT COUNT(*) FROM {casestudy_submissions} sub3
                             WHERE sub3.casestudyid = :cs_unsat AND sub3.userid = u.id
                               AND sub3.status = :st_unsat';

        $fields = 'u.id, u.*, ' . $DB->sql_fullname('u.firstname', 'u.lastname') . ' AS fullname,
                   (' . $submitted . ') AS cntsubmitted,
                   (' . $satisfactory . ') AS cntsatisfactory,
                   (' . $unsatisfactory . ') AS cntunsatisfactory';
        $from = '{user} u';

        [$enrolledsql, $enrolledparams] = get_enrolled_sql($this->context, 'mod/casestudy:submit', 0, true);
        $where = "u.id IN ($enrolledsql)";
        $params = $enrolledparams;

        $params['cs_sub'] = $this->cm->instance;
        $params['cs_sat'] = $this->cm->instance;
        $params['cs_unsat'] = $this->cm->instance;
        $params['st_new'] = CASESTUDY_STATUS_NEW;
        $params['st_draft'] = CASESTUDY_STATUS_DRAFT;
        $params['st_sat'] = CASESTUDY_STATUS_SATISFACTORY;
        $params['st_unsat'] = CASESTUDY_STATUS_UNSATISFACTORY;

        // Group filtering, mirroring the summaries table behaviour.
        $groupmode = groups_get_activity_groupmode($this->cm);
        $allowedgroups = groups_get_activity_allowed_groups($this->cm);
        $canaccessall = has_capability('moodle/site:accessallgroups', $this->context);

        if (!empty($this->groupid) && $this->groupid > 0) {
            if ($groupmode == SEPARATEGROUPS && $allowedgroups !== false && !isset($allowedgroups[$this->groupid])) {
                $where .= ' AND 1 = 0';
            }
            $from .= ' JOIN {groups_members} gm ON gm.userid = u.id';
            $where .= ' AND gm.groupid = :selgroup';
            $params['selgroup'] = $this->groupid;
        } else if (!$canaccessall && $groupmode != NOGROUPS) {
            if (empty($allowedgroups)) {
                $where .= ' AND 1 = 0';
            } else {
                $usergroupids = array_keys($allowedgroups);
                $from .= ' JOIN {groups_members} gm2 ON gm2.userid = u.id';
                [$ingroupsql, $groupparams] = $DB->get_in_or_equal($usergroupids, SQL_PARAMS_NAMED);
                $where .= " AND gm2.groupid $ingroupsql";
                $params = array_merge($params, $groupparams);
            }
        }

        $this->set_sql($fields, $from, $where, $params);
        $this->set_count_sql("SELECT COUNT(DISTINCT u.id) FROM $from WHERE $where", $params);
    }

    /**
     * Render the user's full name, linked to their profile.
     *
     * @param object $row Table row
     * @return string HTML
     */
    public function col_fullname($row) {
        global $OUTPUT;

        $name = fullname($row, has_capability('moodle/site:viewfullnames', $this->context));
        if ($this->is_downloading()) {
            return $name;
        }

        $userpicture = $OUTPUT->user_picture($row, ['size' => 35, 'courseid' => $this->cm->course]);
        $url = new moodle_url('/user/view.php', ['id' => $row->id, 'course' => $this->cm->course]);
        $namelink = \html_writer::link($url, $name);

        return \html_writer::div($userpicture . ' ' . $namelink, 'user-info d-flex align-items-center');
    }

    /**
     * Render the comma-separated list of the user's groups in this course.
     *
     * @param object $row Table row
     * @return string Group names, or a dash when the user is in no groups
     */
    public function col_groups($row) {
        $groups = groups_get_all_groups($this->cm->course, $row->id, 0, 'g.id, g.name');
        if (empty($groups)) {
            return '-';
        }
        $names = array_map(function ($g) {
            return format_string($g->name);
        }, $groups);
        return implode(', ', $names);
    }

    /**
     * Render the "marked" count (satisfactory + unsatisfactory).
     *
     * @param object $row Table row
     * @return int
     */
    public function col_cntmarked($row) {
        return (int) $row->cntsatisfactory + (int) $row->cntunsatisfactory;
    }

    /**
     * Render the "unmarked" count (submitted minus marked).
     *
     * @param object $row Table row
     * @return int
     */
    public function col_cntunmarked($row) {
        $marked = (int) $row->cntsatisfactory + (int) $row->cntunsatisfactory;
        return max(0, (int) $row->cntsubmitted - $marked);
    }
}
