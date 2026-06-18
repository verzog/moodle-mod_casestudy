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
 * Define all the restore steps that will be used by the restore_casestudy_activity_task
 *
 * @package   mod_casestudy
 * @copyright 2025 Skin Cancer College Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete casestudy structure for restore, with file and id annotations
 *
 * @package   mod_casestudy
 * @copyright 2025 Skin Cancer College Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_casestudy_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure of the restore workflow.
     *
     * @return restore_path_element $structure
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $paths[] = new restore_path_element('casestudy', '/activity/casestudy');
        $paths[] = new restore_path_element('casestudy_field', '/activity/casestudy/fields/field');
        $paths[] = new restore_path_element('casestudy_completion_rule',
                                           '/activity/casestudy/completion_rules/completion_rule');

        if ($userinfo) {
            $paths[] = new restore_path_element('casestudy_override',
                                               '/activity/casestudy/overrides/override');
            $paths[] = new restore_path_element('casestudy_submission',
                                               '/activity/casestudy/submissions/submission');
            $paths[] = new restore_path_element('casestudy_content',
                                               '/activity/casestudy/submissions/submission/contents/content');
            $paths[] = new restore_path_element('casestudy_grade',
                                               '/activity/casestudy/submissions/submission/grades/grade');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a casestudy restore.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_casestudy($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Apply date offset for time fields.
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if ($data->grade < 0) {
            // Scale found, get mapping.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        // Insert the casestudy record.
        $newitemid = $DB->insert_record('casestudy', $data);

        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process a field restore
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_casestudy_field($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->casestudyid = $this->get_new_parentid('casestudy');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('casestudy_fields', $data);
        $this->set_mapping('casestudy_field', $oldid, $newitemid);
    }

    /**
     * Process a completion rule restore
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_casestudy_completion_rule($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->casestudyid = $this->get_new_parentid('casestudy');

        // Map fieldid if it exists.
        if (!empty($data->fieldid)) {
            $data->fieldid = $this->get_mappingid('casestudy_field', $data->fieldid);
        }

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('casestudy_completion_rules', $data);
        $this->set_mapping('casestudy_completion_rule', $oldid, $newitemid);
    }

    /**
     * Process an override restore
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_casestudy_override($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->casestudyid = $this->get_new_parentid('casestudy');

        // Map userid.
        if ($data->userid > 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }

        // Apply date offset.
        if (!empty($data->timeclose)) {
            $data->timeclose = $this->apply_date_offset($data->timeclose);
        }
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Check if the user mapping exists; if not, skip this override.
        if ($data->userid) {
            $newitemid = $DB->insert_record('casestudy_overrides', $data);
            $this->set_mapping('casestudy_override', $oldid, $newitemid);
        }
    }

    /**
     * Process a submission restore
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_casestudy_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->casestudyid = $this->get_new_parentid('casestudy');

        // Map userid.
        if ($data->userid > 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }

        // Map groupid.
        if (!empty($data->groupid)) {
            $data->groupid = $this->get_mappingid('group', $data->groupid);
        } else {
            $data->groupid = 0;
        }

        // Map parentid if it exists.
        if (!empty($data->parentid)) {
            $data->parentid = $this->get_mappingid('casestudy_submission', $data->parentid);
        } else {
            $data->parentid = 0;
        }

        // Apply date offset.
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timesubmitted = $this->apply_date_offset($data->timesubmitted);

        $newitemid = $DB->insert_record('casestudy_submissions', $data);
        $this->set_mapping('casestudy_submission', $oldid, $newitemid);
    }

    /**
     * Process submission content restore
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_casestudy_content($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('casestudy_submission');

        // Map fieldid.
        if (!empty($data->fieldid)) {
            $data->fieldid = $this->get_mappingid('casestudy_field', $data->fieldid);
        }

        // Only insert if we have a valid field mapping.
        if ($data->fieldid) {
            $newitemid = $DB->insert_record('casestudy_content', $data);
            $this->set_mapping('casestudy_content', $oldid, $newitemid);
        }
    }

    /**
     * Process a grade restore
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_casestudy_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->submissionid = $this->get_new_parentid('casestudy_submission');

        // Map userid.
        if ($data->userid > 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }

        // Map graderid.
        if ($data->graderid > 0) {
            $data->graderid = $this->get_mappingid('user', $data->graderid);
        }

        // Apply date offset.
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('casestudy_grades', $data);
        $this->set_mapping('casestudy_grade', $oldid, $newitemid);
    }

    /**
     * Once the database tables have been fully restored, restore the files
     */
    protected function after_execute() {
        // Add casestudy related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_casestudy', 'intro', null);
        $this->add_related_files('mod_casestudy', 'graderinfo', null);
        $this->add_related_files('mod_casestudy', 'description', 'casestudy_field');
        $this->add_related_files('mod_casestudy', 'content', 'casestudy_content');
        $this->add_related_files('mod_casestudy', 'feedback', 'casestudy_grade');
    }
}
