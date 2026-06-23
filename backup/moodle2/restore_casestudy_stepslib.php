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
 * Define all the restore steps that will be used by the restore_casestudy_activity_task
 *
 * @package   mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete casestudy structure for restore, with file and id annotations.
 */
class restore_casestudy_activity_structure_step extends restore_activity_structure_step {
    /**
     * Original (pre-restore) ids of file-type fields, collected as fields are processed.
     *
     * File-field uploads live in per-field areas named field_<fieldid>. Because the field id
     * is part of the area name (and gets remapped on restore), after_execute uses these to
     * pull the files in under their original area name and then move them to the new id.
     *
     * @var int[]
     */
    protected $filefieldoldids = [];

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
        $paths[] = new restore_path_element(
            'casestudy_completion_rule',
            '/activity/casestudy/completion_rules/completion_rule'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'casestudy_override',
                '/activity/casestudy/overrides/override'
            );
            $paths[] = new restore_path_element(
                'casestudy_submission',
                '/activity/casestudy/submissions/submission'
            );
            $paths[] = new restore_path_element(
                'casestudy_content',
                '/activity/casestudy/submissions/submission/contents/content'
            );
            $paths[] = new restore_path_element(
                'casestudy_grade',
                '/activity/casestudy/submissions/submission/grades/grade'
            );
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

        // Remember file-type fields so after_execute can restore and remap their
        // per-field file areas (field_<id>).
        if (isset($data->type) && $data->type === 'file') {
            $this->filefieldoldids[] = $oldid;
        }
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
        // Legacy/static content area: kept for backwards compatibility with any older backup
        // that stored file content here. Current file-field uploads use field_<id> areas below.
        $this->add_related_files('mod_casestudy', 'content', 'casestudy_content');
        $this->add_related_files('mod_casestudy', 'feedback', 'casestudy_grade');
        $this->add_related_files('mod_casestudy', 'submission_richtext', 'casestudy_submission');

        // Restore file-field uploads. They were backed up under their original field_<oldfieldid>
        // area keyed by submission id. The filearea name embeds the field id, which Moodle remaps
        // on restore, so the files have to be moved to field_<newfieldid>. This is done in two
        // phases through a collision-free staging area, because a new field id can numerically
        // equal a different field's old id (e.g. when restoring into a fresh site), and a naive
        // in-place rename could otherwise overwrite another field's files.
        $fs = get_file_storage();
        $contextid = $this->task->get_contextid();

        // Phase 1: pull each backed-up field_<oldfieldid> area in (remapping the submission
        // itemid) and drain it into a staging area keyed by the new field id.
        foreach ($this->filefieldoldids as $oldfieldid) {
            $oldarea = 'field_' . $oldfieldid;
            $this->add_related_files('mod_casestudy', $oldarea, 'casestudy_submission');

            $newfieldid = $this->get_mappingid('casestudy_field', $oldfieldid);
            if (empty($newfieldid) || (int)$newfieldid === (int)$oldfieldid) {
                // No mapping, or the id is unchanged; files already sit in the correct area.
                continue;
            }

            $stagearea = 'restorestage_field_' . $newfieldid;
            $files = $fs->get_area_files($contextid, 'mod_casestudy', $oldarea, false, 'id', false);
            foreach ($files as $file) {
                $fs->create_file_from_storedfile((object)['filearea' => $stagearea], $file);
                $file->delete();
            }
            $fs->delete_area_files($contextid, 'mod_casestudy', $oldarea);
        }

        // Phase 2: now that every source area has been cleared, move the staged files into their
        // final field_<newfieldid> area.
        foreach ($this->filefieldoldids as $oldfieldid) {
            $newfieldid = $this->get_mappingid('casestudy_field', $oldfieldid);
            if (empty($newfieldid) || (int)$newfieldid === (int)$oldfieldid) {
                continue;
            }

            $stagearea = 'restorestage_field_' . $newfieldid;
            $finalarea = 'field_' . $newfieldid;
            $files = $fs->get_area_files($contextid, 'mod_casestudy', $stagearea, false, 'id', false);
            foreach ($files as $file) {
                $fs->create_file_from_storedfile((object)['filearea' => $finalarea], $file);
                $file->delete();
            }
            $fs->delete_area_files($contextid, 'mod_casestudy', $stagearea);
        }
    }
}
