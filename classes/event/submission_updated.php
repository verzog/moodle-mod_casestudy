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
 * The mod_casestudy submission updated event.
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_casestudy submission updated event class.
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_updated extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'casestudy_submissions';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' updated a submission with id '$this->objectid' " .
            "for the casestudy activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissionupdated', 'mod_casestudy');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/casestudy/view_casestudy.php', [
            'id' => $this->contextinstanceid,
            'submissionid' => $this->objectid
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
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['casestudyid'])) {
            throw new \coding_exception('The \'casestudyid\' value must be set in other.');
        }
    }

    /**
     * Create instance of event.
     *
     * @param object $casestudy The casestudy instance
     * @param object $cm The course module instance
     * @param object $submission The submission object
     * @param string $updatetype Optional type of update (e.g., 'content', 'status')
     * @return submission_updated
     */
    public static function create_from_submission($casestudy, $cm, $submission, $updatetype = null) {
        $other = [
            'casestudyid' => $casestudy->id,
            'status' => $submission->status
        ];

        if ($updatetype !== null) {
            $other['updatetype'] = $updatetype;
        }

        $data = [
            'context' => \context_module::instance($cm->id),
            'objectid' => $submission->id,
            'relateduserid' => $submission->userid,
            'other' => $other
        ];
        $event = self::create($data);
        $event->add_record_snapshot('casestudy_submissions', $submission);
        $event->add_record_snapshot('casestudy', $casestudy);
        return $event;
    }
}
