
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
 * local library functions for casestudy module
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class casestudy_selector_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;
        $options = $this->_customdata['options'];

        // Navigation buttons container
        $navigationgroup = [];

        // Previous/Next submission buttons
        if ($this->has_previous_submission()) {
            $navigationgroup[] = $mform->createElement('html', 'previoussubmission',
                get_string('previoussubmission', 'mod_casestudy'),
                ['class' => 'btn btn-secondary']);
        }

        $navigationgroup[] = $mform->createElement('autocomplete', 'submissionid', '', $options);

        if ($this->has_next_submission()) {
            $navigationgroup[] = $mform->createElement('html', 'nextsubmission',
                get_string('nextsubmission', 'mod_casestudy'),
                ['class' => 'btn btn-secondary']);
        }

        if (!empty($navigationgroup)) {
            $mform->addGroup($navigationgroup, 'navigation', get_string('navigation', 'mod_casestudy'), ' ', false);
        }

    }

     /**
     * Check if there's a previous submission for navigation
     *
     * @return bool
     */
    protected function has_previous_submission() {
        $casestudyid = $this->optional_param('casestudyid', 0, PARAM_INT);
        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);

        if (!$casestudyid || !$submissionid) {
            return false;
        }

        $manager = submission_manager::instance($casestudyid);
        return $manager->get_previous_submission_for_grading($submissionid) !== null;
    }

    /**
     * Check if there's a next submission for navigation
     *
     * @return bool
     */
    protected function has_next_submission() {
        $casestudyid = $this->optional_param('casestudyid', 0, PARAM_INT);
        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);

        if (!$casestudyid || !$submissionid) {
            return false;
        }

        $manager = submission_manager::instance($casestudyid);
        return $manager->get_next_submission_for_grading($submissionid) !== null;
    }
}

/**
 * Template editor form.
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 */
class template_editor_form extends moodleform {

    protected function definition() {

        $mform = $this->_form;
        $data = $this->_customdata;

        $mform->addElement('hidden', 'templatename', $data['templatename']);
        $mform->setType('templatename', PARAM_ALPHA);

        // if ($data['usehtmleditor']) {

        $editoroptions = [
            'subdirs' => false,
            'maxfiles' => 0,
            'context' => $data['context'],
        ];
        $mform->addElement('editor', 'templatecontent', get_string('templatecontent', 'mod_casestudy'), null, $editoroptions);
        $mform->setType('templatecontent', PARAM_RAW);
        $mform->setDefault('templatecontent', ['text' => $data['templatecontent'], 'format' => FORMAT_HTML]);

        // } else {
        //     $mform->addElement('textarea', 'templatecontent', get_string('templatecontent', 'mod_casestudy'), ['rows' => 20, 'cols' => 75, 'style' => 'width: 100%;']);
        //     $mform->setType('templatecontent', PARAM_RAW);
        //     $mform->setDefault('templatecontent', $data['templatecontent']);
        // }

        if ($data['disableeditor']) {
            $mform->hardFreeze('templatecontent');
        }

        // Add action buttons.
        $this->add_action_buttons(true, get_string('savetemplate', 'mod_casestudy'));
    }
}