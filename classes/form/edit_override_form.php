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
 * Form for editing case study overrides
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\form;

use cm_info;
use context_module;
use moodle_url;
use moodleform;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for editing case study override settings.
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_override_form extends moodleform {

    /** @var cm_info course module object. */
    protected $cm;

    /** @var stdClass the case study settings object. */
    protected $casestudy;

    /** @var context_module the case study context. */
    protected $context;

    /** @var int userid, if provided. */
    protected $userid;

    /** @var int overrideid, if provided. */
    protected int $overrideid;

    /**
     * Constructor.
     *
     * @param moodle_url $submiturl the form action URL.
     * @param cm_info $cm course module object.
     * @param stdClass $casestudy the case study settings object.
     * @param context_module $context the case study context.
     * @param stdClass|null $override the override being edited, if it already exists.
     */
    public function __construct(moodle_url $submiturl,
            cm_info $cm, stdClass $casestudy, context_module $context,
            ?stdClass $override) {

        $this->cm = $cm;
        $this->casestudy = $casestudy;
        $this->context = $context;
        $this->userid = empty($override->userid) ? 0 : $override->userid;
        $this->overrideid = $override->id ?? 0;

        parent::__construct($submiturl);
    }

    /**
     * Define the form.
     */
    protected function definition() {
        global $DB;

        $cm = $this->cm;
        $mform = $this->_form;

        $mform->addElement('header', 'override', get_string('override', 'casestudy'));

        // User selection.
        if ($this->userid) {
            $user = $DB->get_record('user', ['id' => $this->userid], '*', MUST_EXIST);
            $userchoices = [$this->userid => fullname($user)];
            $mform->addElement('select', 'userid',
                    get_string('overrideuser', 'casestudy'), $userchoices);
            $mform->freeze('userid');
        } else {
            // Prepare the list of users who can submit.
            $users = get_enrolled_users($this->context, 'mod/casestudy:submit', 0, 'u.*', null, 0, 0, true);

            // Exclude users who already have overrides.
            if (!empty($users)) {
                $existingoverrideusers = $DB->get_records('casestudy_overrides',
                    ['casestudyid' => $this->casestudy->id], '', 'userid');
                foreach ($existingoverrideusers as $existing) {
                    unset($users[$existing->userid]);
                }
            }

            if (empty($users)) {
                $link = new moodle_url('/mod/casestudy/overrides.php', ['cmid' => $cm->id]);
                throw new \moodle_exception('usersnone', 'casestudy', $link);
            }

            $userchoices = [];
            foreach ($users as $id => $user) {
                $userchoices[$id] = fullname($user);
            }

            $mform->addElement('searchableselector', 'userid',
                    get_string('overrideuser', 'casestudy'), $userchoices);
            $mform->addRule('userid', get_string('required'), 'required', null, 'client');
        }

        // End date override (timeclose).
        $mform->addElement('checkbox', 'enabletimeclose', get_string('enableenddate', 'casestudy'));
        $mform->addHelpButton('enabletimeclose', 'overrideenddate', 'casestudy');

        $mform->addElement('date_time_selector', 'timeclose',
                get_string('casestudycloses', 'casestudy'), ['optional' => false]);
        $mform->setDefault('timeclose', $this->casestudy->timeclose);
        $mform->disabledIf('timeclose', 'enabletimeclose');

        // Maximum attempts override (resubmission attempts per case study).
        $mform->addElement('checkbox', 'enablemaxattempts', get_string('enablemaxattempts', 'casestudy'));
        $mform->addHelpButton('enablemaxattempts', 'overridemaxattempts', 'casestudy');

        $attemptsoptions = [];
        for ($i = 1; $i <= 10; $i++) {
            $attemptsoptions[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts',
                get_string('totalattempts', 'casestudy'), $attemptsoptions);
        $mform->setDefault('maxattempts', $this->casestudy->maxattempts > 0 ? $this->casestudy->maxattempts : 1);
        $mform->disabledIf('maxattempts', 'enablemaxattempts');

        // Submit buttons.
        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton',
                get_string('saveoverride', 'casestudy'));
        $buttonarray[] = $mform->createElement('submit', 'againbutton',
                get_string('saveoverrideandstay', 'casestudy'));
        $buttonarray[] = $mform->createElement('cancel');

        $mform->addGroup($buttonarray, 'buttonbar', '', [' '], false);
        $mform->closeHeaderBefore('buttonbar');
    }

    /**
     * Validate the data from the form.
     *
     * @param  array $data form data
     * @param  array $files form files
     * @return array An array of error messages.
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        // Check for duplicate override if creating a new one.
        if (empty($this->overrideid) && !empty($data['userid'])) {
            $existing = $DB->get_record('casestudy_overrides',
                ['casestudyid' => $this->casestudy->id, 'userid' => $data['userid']]);
            if ($existing) {
                $errors['userid'] = get_string('useroverrideexists', 'casestudy');
            }
        }

        // Validate that at least one override is enabled.
        if (empty($data['enabletimeclose']) && empty($data['enablemaxattempts'])) {
            $errors['enabletimeclose'] = get_string('error', 'core');
            $errors['enablemaxattempts'] = get_string('atleastoneoption', 'casestudy');
        }

        return $errors;
    }
}
