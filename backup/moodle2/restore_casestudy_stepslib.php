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
     * Restored content rows belonging to file-type fields, for legacy backups only.
     *
     * Older plugin versions backed up file-field uploads under the static 'content' area keyed
     * by casestudy_content.id (the backup annotated `content`/`content.id`), even though the
     * module serves uploads from field_<fieldid> keyed by submission id. For those backups,
     * after_execute moves any restored 'content'-area files into the correct per-field area so
     * they display. Each entry is an object with ->contentid, ->fieldid and ->submissionid
     * (all post-restore ids). Empty for current-format backups, where it stays a harmless no-op.
     *
     * @var \stdClass[]
     */
    protected $legacyfilecontents = [];

    /**
     * New casestudy_content ids restored in this run, used to normalise rich-text
     * pluginfile URLs once all files and mappings are in place.
     *
     * @var int[]
     */
    protected $restoredcontentids = [];

    /**
     * Maps old child submission id → old parent submission id for resubmissions.
     *
     * Backups created before copy_submission_files() existed can contain resubmissions
     * whose submission_richtext area is empty: the recreate_submission() code copied the
     * content rows (so @@PLUGINFILE@@ references are in the HTML) but never copied the
     * actual files to the new submission's area.  after_execute uses these pairs to
     * propagate parent files into child areas so those images display after restore.
     *
     * @var array<int,int>  [oldchildid => oldparentid]
     */
    protected $submissionparentoldids = [];

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

        // Record old parent id before remapping, so after_execute can propagate
        // submission_richtext files from parent to child for resubmissions created
        // before copy_submission_files() existed.
        $oldparentid = (int)($data->parentid ?? 0);

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

        // Remember this child→parent pair (using old ids) so after_execute can fill in
        // submission_richtext files that were absent because copy_submission_files() didn't
        // exist yet when this resubmission was originally created.
        if ($oldparentid > 0) {
            $this->submissionparentoldids[$oldid] = $oldparentid;
        }
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

        // Remember whether this row belongs to a file-type field before the id is remapped,
        // so legacy 'content'-area uploads can be moved to field_<id> in after_execute.
        $isfilefield = !empty($data->fieldid) && in_array((int)$data->fieldid, $this->filefieldoldids, true);

        // Map fieldid.
        if (!empty($data->fieldid)) {
            $data->fieldid = $this->get_mappingid('casestudy_field', $data->fieldid);
        }

        // Only insert if we have a valid field mapping.
        if ($data->fieldid) {
            $newitemid = $DB->insert_record('casestudy_content', $data);
            $this->set_mapping('casestudy_content', $oldid, $newitemid);
            $this->restoredcontentids[] = $newitemid;

            if ($isfilefield) {
                $this->legacyfilecontents[] = (object) [
                    'contentid' => $newitemid,
                    'fieldid' => $data->fieldid,
                    'submissionid' => $data->submissionid,
                ];
            }
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

        // Legacy backups (older plugin versions) stored file-field uploads in the static 'content'
        // area keyed by casestudy_content.id; line above already restored them into that area. The
        // module serves uploads from field_<newfieldid> keyed by submission id, so move them there
        // or they would never display. No-op for current-format backups, whose 'content' area holds
        // no files.
        foreach ($this->legacyfilecontents as $content) {
            $finalarea = 'field_' . $content->fieldid;
            $files = $fs->get_area_files($contextid, 'mod_casestudy', 'content', $content->contentid, 'id', false);
            foreach ($files as $file) {
                // If the current-format upload is already in the destination, just drop the legacy copy.
                $exists = $fs->file_exists(
                    $contextid,
                    'mod_casestudy',
                    $finalarea,
                    $content->submissionid,
                    $file->get_filepath(),
                    $file->get_filename()
                );
                if (!$exists) {
                    $fs->create_file_from_storedfile(
                        (object) ['filearea' => $finalarea, 'itemid' => $content->submissionid],
                        $file
                    );
                }
                $file->delete();
            }
        }

        // Propagate submission_richtext files from parent submissions to resubmissions whose
        // richtext area is empty.  Backups taken before copy_submission_files() was added to
        // recreate_submission() contain resubmissions where the HTML was copied from the parent
        // (so @@PLUGINFILE@@ references are present) but the underlying files were not, making
        // images 404 after restore.  Walking each parent→child pair and copying any missing
        // files retroactively fixes those resubmissions without touching correctly-backed-up ones.
        $this->propagate_richtext_files_to_resubmissions($fs, $contextid);

        // Optimise restored uploads when enabled, so restoring an old backup full of large
        // originals does not bloat this site's storage and future backups. No-op for images
        // already within bounds.
        if (\mod_casestudy\local\image_optimizer::is_enabled_on_restore()) {
            $fileids = \mod_casestudy\local\image_optimizer::get_field_file_ids($contextid);
            $files = [];
            foreach ($fileids as $fileid) {
                if ($stored = $fs->get_file_by_id($fileid)) {
                    $files[] = $stored;
                }
            }
            \mod_casestudy\local\image_optimizer::optimize_files($fs, $files);
        }

        $this->normalise_richtext_pluginfile_urls();
    }

    /**
     * Copy submission_richtext files from parent submissions to child resubmissions that
     * have no files of their own in that area.
     *
     * Backups created before copy_submission_files() was added to recreate_submission()
     * contain resubmissions where the @@PLUGINFILE@@ references in the HTML came from the
     * copied content rows, but the actual files were never copied to the child submission's
     * area.  This method walks the recorded parent→child pairs (using the pre-restore old
     * ids) and, for each child that has an empty submission_richtext area, copies every
     * file from the already-restored parent area into the child area.  Idempotent: if the
     * child already has files (current-format backups) nothing is touched.
     *
     * @param \file_storage $fs    Moodle file storage instance.
     * @param int           $contextid  New module context id.
     * @return void
     */
    protected function propagate_richtext_files_to_resubmissions(\file_storage $fs, int $contextid): void {
        if (empty($this->submissionparentoldids)) {
            return;
        }

        foreach ($this->submissionparentoldids as $oldchildid => $oldparentid) {
            $newchildid  = (int) $this->get_mappingid('casestudy_submission', $oldchildid);
            $newparentid = (int) $this->get_mappingid('casestudy_submission', $oldparentid);

            if (!$newchildid || !$newparentid) {
                continue;
            }

            // Only fill in missing files; leave correctly-backed-up children untouched.
            $childfiles = $fs->get_area_files(
                $contextid, 'mod_casestudy', 'submission_richtext', $newchildid, 'id', false
            );
            if (!empty($childfiles)) {
                continue;
            }

            $parentfiles = $fs->get_area_files(
                $contextid, 'mod_casestudy', 'submission_richtext', $newparentid, 'id', false
            );
            foreach ($parentfiles as $file) {
                // file_storage deduplicates by content hash, so copying is always safe.
                $fs->create_file_from_storedfile([
                    'contextid' => $contextid,
                    'component' => 'mod_casestudy',
                    'filearea'  => 'submission_richtext',
                    'itemid'    => $newchildid,
                ], $file);
            }
        }
    }

    /**
     * Convert absolute submission_richtext pluginfile URLs in restored content to the
     * @@PLUGINFILE@@ placeholder.
     *
     * Rich-text submission content can embed absolute pluginfile URLs that carry the
     * original context and submission ids (for example images inserted as file links).
     * Restore remaps the files themselves, but those baked-in URLs are not rewritten, so
     * they 404 in the new course. The placeholder is context/itemid independent, so once
     * normalised the URLs resolve against the restored files at display time.
     *
     * @return void
     */
    protected function normalise_richtext_pluginfile_urls() {
        global $DB;

        if (empty($this->restoredcontentids)) {
            return;
        }

        $patterns = [
            '~https?://[^"\'\s<>]+?/pluginfile\.php/\d+/mod_casestudy/submission_richtext/\d+/~i',
            '~https?://[^"\'\s<>]+?/pluginfile\.php\?file=/\d+/mod_casestudy/submission_richtext/\d+/~i',
        ];

        foreach ($this->restoredcontentids as $contentid) {
            $record = $DB->get_record('casestudy_content', ['id' => $contentid], 'id, content');
            if (!$record || $record->content === null || $record->content === ''
                    || strpos($record->content, 'submission_richtext') === false) {
                continue;
            }

            $updated = preg_replace($patterns, '@@PLUGINFILE@@/', $record->content);
            if ($updated !== null && $updated !== $record->content) {
                $DB->set_field('casestudy_content', 'content', $updated, ['id' => $contentid]);
            }
        }
    }
}
