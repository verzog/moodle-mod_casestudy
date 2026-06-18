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
 * Define all the backup steps that will be used by the backup_casestudy_activity_task
 *
 * @package   mod_casestudy
 * @copyright 2025 Skin Cancer College Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete casestudy structure for backup, with file and id annotations
 *
 * @package   mod_casestudy
 * @copyright 2025 Skin Cancer College Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_casestudy_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure for the casestudy activity
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $casestudy = new backup_nested_element('casestudy', ['id'], [
            'name', 'intro', 'introformat', 'timeopen', 'timeclose',
            'maxsubmissions', 'maxattempts', 'resubmissionbased', 'requiresubmit',
            'requireacceptance', 'grade', 'hidegrader', 'graderinfo', 'graderinfoformat',
            'notifygraders', 'notifyemail', 'notifystudentdefault',
            'completionaggr', 'completionsatisfactory', 'cmpsatisfactorysubmissions',
            'completioncategory', 'singletemplate', 'csstemplate',
            'timecreated', 'timemodified'
        ]);

        $fields = new backup_nested_element('fields');

        $field = new backup_nested_element('field', ['id'], [
            'type', 'name', 'shortname', 'description', 'descriptionformat',
            'required', 'sortorder', 'showlistview', 'category',
            'param1', 'param2', 'param3', 'param4', 'param5',
            'timecreated', 'timemodified'
        ]);

        $completionrules = new backup_nested_element('completion_rules');

        $completionrule = new backup_nested_element('completion_rule', ['id'], [
            'enabled', 'ruletype', 'count', 'fieldid', 'categoryvalue',
            'sortorder', 'timecreated', 'timemodified'
        ]);

        $overrides = new backup_nested_element('overrides');

        $override = new backup_nested_element('override', ['id'], [
            'userid', 'timeclose', 'maxattempts', 'timecreated', 'timemodified'
        ]);

        $submissions = new backup_nested_element('submissions');

        $submission = new backup_nested_element('submission', ['id'], [
            'userid', 'groupid', 'status', 'attempt', 'parentid',
            'timecreated', 'timemodified', 'timesubmitted'
        ]);

        $contents = new backup_nested_element('contents');

        $content = new backup_nested_element('content', ['id'], [
            'fieldid', 'content', 'contentformat',
            'content1', 'content2', 'content3', 'content4'
        ]);

        $grades = new backup_nested_element('grades');

        $grade = new backup_nested_element('grade', ['id'], [
            'userid', 'graderid', 'feedback', 'feedbackformat', 'grade',
            'requestresubmission', 'timecreated', 'timemodified'
        ]);

        // Build the tree.
        $casestudy->add_child($fields);
        $fields->add_child($field);

        $casestudy->add_child($completionrules);
        $completionrules->add_child($completionrule);

        $casestudy->add_child($overrides);
        $overrides->add_child($override);

        $casestudy->add_child($submissions);
        $submissions->add_child($submission);

        $submission->add_child($contents);
        $contents->add_child($content);

        $submission->add_child($grades);
        $grades->add_child($grade);

        // Define sources.
        $casestudy->set_source_table('casestudy', ['id' => backup::VAR_ACTIVITYID]);

        // Fields are always included as they define the structure.
        $field->set_source_table('casestudy_fields', ['casestudyid' => backup::VAR_PARENTID]);

        // Completion rules are always included.
        $completionrule->set_source_table('casestudy_completion_rules', ['casestudyid' => backup::VAR_PARENTID]);

        // All these source definitions only happen if we are including user info.
        if ($userinfo) {
            // User overrides.
            $override->set_source_table('casestudy_overrides', ['casestudyid' => backup::VAR_PARENTID]);

            // Submissions.
            $submission->set_source_table('casestudy_submissions', ['casestudyid' => backup::VAR_PARENTID]);

            // Submission content (field data).
            $content->set_source_table('casestudy_content', ['submissionid' => backup::VAR_PARENTID]);

            // Grades.
            $grade->set_source_table('casestudy_grades', ['submissionid' => backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $override->annotate_ids('user', 'userid');
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('group', 'groupid');
        $grade->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'graderid');
        $content->annotate_ids('casestudy_field', 'fieldid');
        $completionrule->annotate_ids('casestudy_field', 'fieldid');

        // Define file annotations.
        $casestudy->annotate_files('mod_casestudy', 'intro', null);
        $casestudy->annotate_files('mod_casestudy', 'graderinfo', null);
        $field->annotate_files('mod_casestudy', 'description', 'id');
        $content->annotate_files('mod_casestudy', 'content', 'id');
        $grade->annotate_files('mod_casestudy', 'feedback', 'id');

        // Return the root element (casestudy), wrapped into standard activity structure.
        return $this->prepare_activity_structure($casestudy);
    }
}
