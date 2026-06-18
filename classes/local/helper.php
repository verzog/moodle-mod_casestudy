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
 * Helper class for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_casestudy\local;
class helper {

    public static function get_casestudy(int $casestudyid) {
        global $DB, $PAGE;
        static $casestudy = null;

        if ($casestudy && $casestudy->id == $casestudyid) {
            return $casestudy;
        }

        if ($casestudyid) {
            $casestudy = $DB->get_record('casestudy', array('id' => $casestudyid), '*', MUST_EXIST);
        } else if ($PAGE->cm && $PAGE->cm->modname === 'casestudy') {
            $casestudy = $DB->get_record('casestudy', array('id' => $PAGE->cm->instance), '*', MUST_EXIST);
        } else {
            throw new \coding_exception('casestudyid must be provided if not on a casestudy activity page');
        }

        return $casestudy;
    }

    public static function get_submission(int $submissionid) {
        global $DB;
        static $submission = null;

        if ($submission && $submission->id == $submissionid) {
            return $submission;
        }

        if ($submissionid) {
            return $DB->get_record('casestudy_submissions', array('id' => $submissionid), '*', MUST_EXIST);
        } else {
            throw new \coding_exception('submissionid must be provided to get_submission');
        }
    }

    /**
     * Get CSS class for submission status
     *
     * @param string $status Status string
     * @return string CSS class
     */
    public static function get_status_info($status, $type='') {

        switch ($status) {
            case CASESTUDY_STATUS_NEW:
                $result = ['class' =>'secondary', 'statusclass' => 'badge-secondary', 'iconclass' => 'fa-plus' ];
                break;
            case CASESTUDY_STATUS_DRAFT:
                $result = ['class' =>'secondary', 'statusclass' => 'badge-secondary', 'iconclass' => 'fa-edit' ];
                break;
            case CASESTUDY_STATUS_SUBMITTED:
                $result = ['class' =>'primary', 'statusclass' => 'badge-primary', 'iconclass' => 'fa-paper-plane'];
                break;
            case CASESTUDY_STATUS_IN_REVIEW:
                $result = ['class' =>'info', 'statusclass' => 'badge-warning', 'iconclass' => 'fa-search'];
                break;
            case CASESTUDY_STATUS_AWAITING_RESUBMISSION:
                $result = ['class' =>'warning', 'statusclass' => 'badge-info', 'iconclass' => 'fa-clock'];
                break;
            case CASESTUDY_STATUS_RESUBMITTED:
                $result = ['class' =>'primary', 'statusclass' => 'badge-primary', 'iconclass' => 'fa-redo'];
                break;
            case CASESTUDY_STATUS_RESUBMITTED_INREVIEW:
                $result = ['class' =>'info', 'statusclass' => 'badge-warning', 'iconclass' => 'fa-search'];
                break;
            case CASESTUDY_STATUS_SATISFACTORY:
                $result = ['class' =>'success', 'statusclass' => 'badge-success', 'iconclass' => 'fa-check'];
                break;
            case CASESTUDY_STATUS_UNSATISFACTORY:
                $result = ['class' =>'danger', 'statusclass' =>  'badge-danger', 'iconclass' => 'fa-times'];
                break;
            default:
                $result = ['class' =>'secondary', 'statusclass' => 'badge-light', 'iconclass' => 'fa-question'];
        }

        return $type ? $result[$type] : $result;
    }

    public static function get_status_list() {
        return [
            CASESTUDY_STATUS_NEW => get_string('status_new', 'mod_casestudy'),
            CASESTUDY_STATUS_DRAFT => get_string('status_draft', 'mod_casestudy'),
            CASESTUDY_STATUS_SUBMITTED => get_string('status_submitted', 'mod_casestudy'),
            CASESTUDY_STATUS_IN_REVIEW => get_string('status_in_review', 'mod_casestudy'),
            CASESTUDY_STATUS_AWAITING_RESUBMISSION => get_string('status_awaiting_resubmission', 'mod_casestudy'),
            CASESTUDY_STATUS_RESUBMITTED => get_string('status_resubmitted', 'mod_casestudy'),
            CASESTUDY_STATUS_RESUBMITTED_INREVIEW => get_string('status_resubmitted_inreview', 'mod_casestudy'),
            CASESTUDY_STATUS_SATISFACTORY => get_string('status_satisfactory', 'mod_casestudy'),
            CASESTUDY_STATUS_UNSATISFACTORY => get_string('status_unsatisfactory', 'mod_casestudy'),
        ];
    }

}