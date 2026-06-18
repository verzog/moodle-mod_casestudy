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
 * The mod_casestudy submission graded event.
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_casestudy submission graded event class.
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_graded extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'casestudy_gradess';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $graderid = $this->userid;
        $studentid = $this->relateduserid;
        $submissionid = $this->other['submissionid'];
        $grade = $this->other['grade'] ?? 'N/A';

        return "The user with id '$graderid' graded the submission with id '$submissionid' " .
            "for the student with id '$studentid' in the casestudy activity with course module id " .
            "'$this->contextinstanceid'. Grade: $grade.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissiongraded', 'mod_casestudy');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/casestudy/view_casestudy.php', [
            'id' => $this->contextinstanceid,
            'submissionid' => $this->other['submissionid']
        ]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set (student being graded).');
        }

        if (!isset($this->other['casestudyid'])) {
            throw new \coding_exception('The \'casestudyid\' value must be set in other.');
        }

        if (!isset($this->other['submissionid'])) {
            throw new \coding_exception('The \'submissionid\' value must be set in other.');
        }
    }

    /**
     * Create instance of event.
     *
     * @param object $casestudy The casestudy instance
     * @param object $cm The course module instance
     * @param object $submission The submission object
     * @param object $grade The grade record
     * @return submission_graded
     */
    public static function create_from_grade($casestudy, $cm, $submission, $grade) {
        $data = [
            'context' => \context_module::instance($cm->id),
            'objectid' => $grade->id,
            'relateduserid' => $submission->userid,
            'other' => [
                'casestudyid' => $casestudy->id,
                'submissionid' => $submission->id,
                'grade' => $grade->grade ?? null,
                'feedback' => isset($grade->feedback) ? 1 : 0
            ]
        ];
        $event = self::create($data);
        $event->add_record_snapshot('casestudy_gradess', $grade);
        $event->add_record_snapshot('casestudy_submissions', $submission);
        $event->add_record_snapshot('casestudy', $casestudy);
        return $event;
    }

    /**
     * Returns relevant URL.
     *
     * @return array
     */
    public function get_legacy_logdata() {
        return [$this->courseid, 'casestudy', 'grade submission',
                'view_casestudy.php?id=' . $this->contextinstanceid . '&submissionid=' . $this->other['submissionid'],
                $this->other['casestudyid'], $this->contextinstanceid];
    }

    /**
     * Returns true if this is a grading action.
     *
     * @return bool
     */
    public static function is_grading() {
        return true;
    }
}
