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
 * The mod_casestudy submission updated event.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
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
            'submissionid' => $this->objectid,
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
            'status' => $submission->status,
        ];

        if ($updatetype !== null) {
            $other['updatetype'] = $updatetype;
        }

        $data = [
            'context' => \context_module::instance($cm->id),
            'objectid' => $submission->id,
            'relateduserid' => $submission->userid,
            'other' => $other,
        ];
        $event = self::create($data);
        $event->add_record_snapshot('casestudy_submissions', $submission);
        $event->add_record_snapshot('casestudy', $casestudy);
        return $event;
    }
}
