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
use mod_casestudy\local\report_stats;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Staff-facing report table: one row per enrolled user with submission counts by status.
 *
 * Each count is shown as "cases (attempts)": the number of distinct cases (resubmission
 * chains) followed by the number of individual submission attempts in brackets.
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

        // Counts are computed in PHP (per-case collapsing needs the resubmission chain),
        // so they cannot be sorted at the SQL level.
        $this->sortable(false);
        $this->set_attribute('class', 'generaltable casestudy-report-table');
        $this->collapsible(false);

        $this->set_sql_and_params();
    }

    /**
     * Build the enrolled-users query (counts are attached later in {@see query_db()}).
     */
    protected function set_sql_and_params() {
        global $DB;

        $fields = 'u.id, u.*, ' . $DB->sql_fullname('u.firstname', 'u.lastname') . ' AS fullname';
        $from = '{user} u';

        [$enrolledsql, $enrolledparams] = get_enrolled_sql($this->context, 'mod/casestudy:submit', 0, true);
        $where = "u.id IN ($enrolledsql)";
        $params = $enrolledparams;

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
     * Fetch the page of users, then attach per-user submission statistics.
     *
     * @param int $pagesize Number of rows per page.
     * @param bool $useinitialsbar Whether to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        parent::query_db($pagesize, $useinitialsbar);
        foreach ($this->rawdata as $row) {
            $row->stats = report_stats::for_user((int) $this->cm->instance, (int) $row->id);
        }
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
     * Submitted count (cases and attempts).
     *
     * @param object $row Table row
     * @return string
     */
    public function col_cntsubmitted($row) {
        return $this->format_count($row->stats->submittedcases, $row->stats->submittedattempts);
    }

    /**
     * Unmarked count (cases and attempts).
     *
     * @param object $row Table row
     * @return string
     */
    public function col_cntunmarked($row) {
        return $this->format_count($row->stats->unmarkedcases, $row->stats->unmarkedattempts);
    }

    /**
     * Marked count (cases and attempts).
     *
     * @param object $row Table row
     * @return string
     */
    public function col_cntmarked($row) {
        return $this->format_count($row->stats->markedcases, $row->stats->markedattempts);
    }

    /**
     * Satisfactory count (cases and attempts).
     *
     * @param object $row Table row
     * @return string
     */
    public function col_cntsatisfactory($row) {
        return $this->format_count($row->stats->satisfactorycases, $row->stats->satisfactoryattempts);
    }

    /**
     * Unsatisfactory count (cases and attempts).
     *
     * @param object $row Table row
     * @return string
     */
    public function col_cntunsatisfactory($row) {
        return $this->format_count($row->stats->unsatisfactorycases, $row->stats->unsatisfactoryattempts);
    }

    /**
     * Format a count as "cases (attempts)".
     *
     * @param int $cases Number of distinct cases.
     * @param int $attempts Number of submission attempts.
     * @return string
     */
    protected function format_count($cases, $attempts) {
        if ($this->is_downloading()) {
            return $cases . ' (' . $attempts . ')';
        }
        return $cases . ' ' . \html_writer::span('(' . $attempts . ')', 'text-muted');
    }
}
