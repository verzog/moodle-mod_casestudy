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
 * Privacy provider for mod_casestudy.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Implementation of the privacy subsystem plugin provider for mod_casestudy.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the user data stored by this plugin.
     *
     * @param collection $collection The metadata collection to populate.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'casestudy_submissions',
            [
                'userid' => 'privacy:metadata:casestudy_submissions:userid',
                'groupid' => 'privacy:metadata:casestudy_submissions:groupid',
                'status' => 'privacy:metadata:casestudy_submissions:status',
                'attempt' => 'privacy:metadata:casestudy_submissions:attempt',
                'timecreated' => 'privacy:metadata:casestudy_submissions:timecreated',
                'timemodified' => 'privacy:metadata:casestudy_submissions:timemodified',
                'timesubmitted' => 'privacy:metadata:casestudy_submissions:timesubmitted',
            ],
            'privacy:metadata:casestudy_submissions'
        );

        $collection->add_database_table(
            'casestudy_content',
            [
                'submissionid' => 'privacy:metadata:casestudy_content:submissionid',
                'fieldid' => 'privacy:metadata:casestudy_content:fieldid',
                'content' => 'privacy:metadata:casestudy_content:content',
            ],
            'privacy:metadata:casestudy_content'
        );

        $collection->add_database_table(
            'casestudy_grades',
            [
                'userid' => 'privacy:metadata:casestudy_grades:userid',
                'graderid' => 'privacy:metadata:casestudy_grades:graderid',
                'feedback' => 'privacy:metadata:casestudy_grades:feedback',
                'grade' => 'privacy:metadata:casestudy_grades:grade',
                'timecreated' => 'privacy:metadata:casestudy_grades:timecreated',
                'timemodified' => 'privacy:metadata:casestudy_grades:timemodified',
            ],
            'privacy:metadata:casestudy_grades'
        );

        $collection->add_database_table(
            'casestudy_overrides',
            [
                'userid' => 'privacy:metadata:casestudy_overrides:userid',
                'timeclose' => 'privacy:metadata:casestudy_overrides:timeclose',
                'maxattempts' => 'privacy:metadata:casestudy_overrides:maxattempts',
            ],
            'privacy:metadata:casestudy_overrides'
        );

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :modulelevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {casestudy} cs ON cs.id = cm.instance
             LEFT JOIN {casestudy_submissions} s ON s.casestudyid = cs.id AND s.userid = :userid1
             LEFT JOIN {casestudy_grades} g ON g.userid = :userid2 OR g.graderid = :userid3
             LEFT JOIN {casestudy_overrides} o ON o.casestudyid = cs.id AND o.userid = :userid4
                 WHERE s.id IS NOT NULL OR g.id IS NOT NULL OR o.id IS NOT NULL";

        $contextlist->add_from_sql($sql, [
            'modulelevel' => CONTEXT_MODULE,
            'modname' => 'casestudy',
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid, 'modname' => 'casestudy'];

        $sql = "SELECT s.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {casestudy_submissions} s ON s.casestudyid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT g.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {casestudy_submissions} s ON s.casestudyid = cm.instance
                  JOIN {casestudy_grades} g ON g.submissionid = s.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT g.graderid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {casestudy_submissions} s ON s.casestudyid = cm.instance
                  JOIN {casestudy_grades} g ON g.submissionid = s.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('graderid', $sql, $params);

        $sql = "SELECT o.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {casestudy_overrides} o ON o.casestudyid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('casestudy', $context->instanceid);
            if (!$cm) {
                continue;
            }

            helper::export_context_files($context, $user);

            // Own submissions (with content + any grade received).
            $submissions = $DB->get_records(
                'casestudy_submissions',
                ['casestudyid' => $cm->instance, 'userid' => $user->id],
                'attempt ASC'
            );
            foreach ($submissions as $submission) {
                self::export_submission($context, $user, $submission);
            }

            // Grades the user authored on other students' submissions.
            $gradedsql = "SELECT g.*, s.casestudyid, s.userid AS studentid
                            FROM {casestudy_grades} g
                            JOIN {casestudy_submissions} s ON s.id = g.submissionid
                           WHERE s.casestudyid = :csid AND g.graderid = :graderid";
            $gradesgiven = $DB->get_records_sql($gradedsql, ['csid' => $cm->instance, 'graderid' => $user->id]);
            if ($gradesgiven) {
                $items = [];
                foreach ($gradesgiven as $grade) {
                    $items[] = [
                        'submissionid' => $grade->submissionid,
                        'studentid' => $grade->studentid,
                        'grade' => $grade->grade,
                        'feedback' => format_text($grade->feedback ?? '', $grade->feedbackformat ?? FORMAT_HTML),
                        'timecreated' => \core_privacy\local\request\transform::datetime($grade->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:gradesgiven', 'mod_casestudy')],
                    (object) ['grades' => $items]
                );
            }

            // Overrides applied to this user.
            $overrides = $DB->get_records(
                'casestudy_overrides',
                ['casestudyid' => $cm->instance, 'userid' => $user->id]
            );
            if ($overrides) {
                $items = [];
                foreach ($overrides as $override) {
                    $items[] = [
                        'timeclose' => $override->timeclose
                            ? \core_privacy\local\request\transform::datetime($override->timeclose) : null,
                        'maxattempts' => $override->maxattempts,
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:overrides', 'mod_casestudy')],
                    (object) ['overrides' => $items]
                );
            }
        }
    }

    /**
     * Export a single submission (content, grade, attached files).
     *
     * @param context $context Module context.
     * @param \stdClass $user The user whose data is being exported.
     * @param \stdClass $submission Submission record.
     */
    protected static function export_submission(context $context, \stdClass $user, \stdClass $submission) {
        global $DB;

        $subpath = [
            get_string('privacy:submissions', 'mod_casestudy'),
            get_string('privacy:submissionnumber', 'mod_casestudy', $submission->attempt),
        ];

        $contents = $DB->get_records('casestudy_content', ['submissionid' => $submission->id]);
        $contentitems = [];
        foreach ($contents as $content) {
            $field = $DB->get_record('casestudy_fields', ['id' => $content->fieldid], 'id, name, shortname, type');
            $contentitems[] = [
                'fieldname' => $field ? $field->name : '',
                'shortname' => $field ? $field->shortname : '',
                'type' => $field ? $field->type : '',
                'content' => format_text($content->content ?? '', $content->contentformat ?? FORMAT_HTML),
            ];

            // Per-field file area.
            if ($field) {
                writer::with_context($context)->export_area_files(
                    array_merge($subpath, [get_string('privacy:fieldfiles', 'mod_casestudy', $field->name)]),
                    'mod_casestudy',
                    'field_' . $field->id,
                    $submission->id
                );
            }
        }

        $grade = $DB->get_record('casestudy_grades', ['submissionid' => $submission->id]);

        $data = (object) [
            'status' => $submission->status,
            'attempt' => $submission->attempt,
            'timecreated' => \core_privacy\local\request\transform::datetime($submission->timecreated),
            'timemodified' => \core_privacy\local\request\transform::datetime($submission->timemodified),
            'timesubmitted' => $submission->timesubmitted
                ? \core_privacy\local\request\transform::datetime($submission->timesubmitted) : null,
            'content' => $contentitems,
            'grade' => $grade ? [
                'grade' => $grade->grade,
                'feedback' => format_text($grade->feedback ?? '', $grade->feedbackformat ?? FORMAT_HTML),
                'timecreated' => \core_privacy\local\request\transform::datetime($grade->timecreated),
            ] : null,
        ];

        writer::with_context($context)->export_data($subpath, $data);

        // Inline rich-text files.
        writer::with_context($context)->export_area_files(
            array_merge($subpath, [get_string('privacy:richtextfiles', 'mod_casestudy')]),
            'mod_casestudy',
            'submission_richtext',
            $submission->id
        );

        // Grader feedback files.
        if ($grade) {
            writer::with_context($context)->export_area_files(
                array_merge($subpath, [get_string('privacy:feedbackfiles', 'mod_casestudy')]),
                'mod_casestudy',
                'feedback',
                $grade->id
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('casestudy', $context->instanceid);
        if (!$cm) {
            return;
        }

        $fs = get_file_storage();
        $submissionids = $DB->get_fieldset_select('casestudy_submissions', 'id', 'casestudyid = ?', [$cm->instance]);
        if ($submissionids) {
            [$insql, $params] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED);

            // Grades + feedback files.
            $gradeids = $DB->get_fieldset_select('casestudy_grades', 'id', "submissionid $insql", $params);
            foreach ($gradeids as $gradeid) {
                $fs->delete_area_files($context->id, 'mod_casestudy', 'feedback', $gradeid);
            }
            $DB->delete_records_select('casestudy_grades', "submissionid $insql", $params);

            // Field content files (per-field area, keyed by submission id).
            $fieldids = $DB->get_fieldset_select('casestudy_fields', 'id', 'casestudyid = ?', [$cm->instance]);
            foreach ($fieldids as $fieldid) {
                foreach ($submissionids as $sid) {
                    $fs->delete_area_files($context->id, 'mod_casestudy', 'field_' . $fieldid, $sid);
                }
            }

            // Rich-text inline files keyed by submission id.
            foreach ($submissionids as $sid) {
                $fs->delete_area_files($context->id, 'mod_casestudy', 'submission_richtext', $sid);
            }

            $DB->delete_records_select('casestudy_content', "submissionid $insql", $params);
            $DB->delete_records_select('casestudy_submissions', "id $insql", $params);
        }

        $DB->delete_records('casestudy_overrides', ['casestudyid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $fs = get_file_storage();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('casestudy', $context->instanceid);
            if (!$cm) {
                continue;
            }

            self::delete_submissions_for_users($context, $cm->instance, [$userid], $fs);

            // Anonymise grading actions the user made on other students' submissions.
            $sql = "UPDATE {casestudy_grades}
                       SET graderid = 0
                     WHERE graderid = :userid
                       AND submissionid IN (
                           SELECT id FROM {casestudy_submissions} WHERE casestudyid = :csid
                       )";
            $DB->execute($sql, ['userid' => $userid, 'csid' => $cm->instance]);

            $DB->delete_records('casestudy_overrides', ['casestudyid' => $cm->instance, 'userid' => $userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user list to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('casestudy', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        $fs = get_file_storage();
        self::delete_submissions_for_users($context, $cm->instance, $userids, $fs);

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params['csid'] = $cm->instance;

        // Anonymise graders we're deleting in this context.
        $sql = "UPDATE {casestudy_grades}
                   SET graderid = 0
                 WHERE graderid $insql
                   AND submissionid IN (
                       SELECT id FROM {casestudy_submissions} WHERE casestudyid = :csid
                   )";
        $DB->execute($sql, $params);

        $DB->delete_records_select(
            'casestudy_overrides',
            "casestudyid = :csid AND userid $insql",
            $params
        );
    }

    /**
     * Cascade-delete the given users' submissions and all attached records/files.
     *
     * @param context_module $context Module context.
     * @param int $casestudyid Case study instance id.
     * @param int[] $userids Users to delete.
     * @param \file_storage $fs File storage handle.
     */
    protected static function delete_submissions_for_users(
        context_module $context,
        int $casestudyid,
        array $userids,
        \file_storage $fs
    ) {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params['csid'] = $casestudyid;

        $submissionids = $DB->get_fieldset_select(
            'casestudy_submissions',
            'id',
            "casestudyid = :csid AND userid $insql",
            $params
        );
        if (empty($submissionids)) {
            return;
        }

        [$subin, $subparams] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED, 'sub');

        $gradeids = $DB->get_fieldset_select('casestudy_grades', 'id', "submissionid $subin", $subparams);
        foreach ($gradeids as $gradeid) {
            $fs->delete_area_files($context->id, 'mod_casestudy', 'feedback', $gradeid);
        }
        $DB->delete_records_select('casestudy_grades', "submissionid $subin", $subparams);

        $fieldids = $DB->get_fieldset_select('casestudy_fields', 'id', 'casestudyid = ?', [$casestudyid]);
        foreach ($fieldids as $fieldid) {
            foreach ($submissionids as $sid) {
                $fs->delete_area_files($context->id, 'mod_casestudy', 'field_' . $fieldid, $sid);
            }
        }

        foreach ($submissionids as $sid) {
            $fs->delete_area_files($context->id, 'mod_casestudy', 'submission_richtext', $sid);
        }

        $DB->delete_records_select('casestudy_content', "submissionid $subin", $subparams);
        $DB->delete_records_select('casestudy_submissions', "id $subin", $subparams);
    }
}
