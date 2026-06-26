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
 * Library of functions and constants for Case Study module
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

// Case Study submission statuses
define('CASESTUDY_STATUS_NEW', 'new');
define('CASESTUDY_STATUS_DRAFT', 'draft');
define('CASESTUDY_STATUS_SUBMITTED', 'submitted');
define('CASESTUDY_STATUS_IN_REVIEW', 'in_review');
define('CASESTUDY_STATUS_AWAITING_RESUBMISSION', 'awaiting_resubmission');
define('CASESTUDY_STATUS_RESUBMITTED', 'resubmitted');
define('CASESTUDY_STATUS_RESUBMITTED_INREVIEW', 'resubmitted_inreview');
define('CASESTUDY_STATUS_SATISFACTORY', 'satisfactory');
define('CASESTUDY_STATUS_UNSATISFACTORY', 'unsatisfactory');

// Field types
define('CASESTUDY_FIELD_TEXT', 'text');
define('CASESTUDY_FIELD_TEXTAREA', 'textarea');
define('CASESTUDY_FIELD_DROPDOWN', 'dropdown');
define('CASESTUDY_FIELD_RADIO', 'radio');
define('CASESTUDY_FIELD_CHECKBOX', 'checkbox');
define('CASESTUDY_FIELD_FILE', 'file');

// Completion criteria types
define('CASESTUDY_COMPLETION_TOTAL', 'total_satisfactory');
define('CASESTUDY_COMPLETION_CATEGORY', 'category_satisfactory');

define('CASESTUDY_COMPLETION_ALL', 1);
define('CASESTUDY_COMPLETION_ANY', 0);

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $data       data object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function casestudy_view($data, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $data->id,
    ];

    $event = \mod_casestudy\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('casestudy', $data);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $casestudy An object from the form in mod_form.php
 * @param mod_casestudy_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted casestudy record
 */
function casestudy_add_instance(stdClass $casestudy, mod_casestudy_mod_form $mform = null) {
    global $DB;

    $casestudy->timecreated = time();
    $casestudy->timemodified = time();

    $casestudy->id = $DB->insert_record('casestudy', $casestudy);

    // Persist grader-information editor content and its embedded files (needs the new id/context).
    casestudy_save_graderinfo_editor($casestudy);

    casestudy_update_completion_criteria($casestudy);
    casestudy_grade_item_update($casestudy);

    return $casestudy->id;
}

/**
 * Save the grader-information editor's HTML and embedded files into the graderinfo file area.
 *
 * The form supplies graderinfo as an editor element (graderinfo_editor); without this the text
 * and any pasted images are silently dropped. Guarded so programmatic/restore paths that do not
 * provide the editor element are left untouched.
 *
 * @param stdClass $casestudy Instance data from the module form (must have id and coursemodule)
 * @return void
 */
function casestudy_save_graderinfo_editor(stdClass $casestudy) {
    global $DB;

    if (empty($casestudy->graderinfo_editor) || !is_array($casestudy->graderinfo_editor)
            || empty($casestudy->coursemodule)) {
        return;
    }

    $context = context_module::instance($casestudy->coursemodule);
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'noclean' => true,
        'context' => $context,
        'subdirs' => true,
    ];

    $update = (object) ['id' => $casestudy->id];
    $update->graderinfo = file_save_draft_area_files(
        $casestudy->graderinfo_editor['itemid'],
        $context->id,
        'mod_casestudy',
        'graderinfo',
        0,
        $editoroptions,
        $casestudy->graderinfo_editor['text']
    );
    $update->graderinfoformat = $casestudy->graderinfo_editor['format'];

    $DB->update_record('casestudy', $update);
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $casestudy An object from the form in mod_form.php
 * @param mod_casestudy_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function casestudy_update_instance(stdClass $casestudy, mod_casestudy_mod_form $mform = null) {
    global $DB;

    $casestudy->timemodified = time();
    $casestudy->id = $casestudy->instance;

    $result = $DB->update_record('casestudy', $casestudy);

    // Persist grader-information editor content and its embedded files.
    casestudy_save_graderinfo_editor($casestudy);

    casestudy_update_completion_criteria($casestudy);
    casestudy_grade_item_update($casestudy);

    // If completion settings have changed, reset user completions.
    if (!empty($casestudy->completionunlocked)) {
        $cm = get_coursemodule_from_instance('casestudy', $casestudy->id);
        if ($cm) {
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm)) {
                $completion->reset_all_state($cm);
            }
        }
    }

    return $result;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function casestudy_delete_instance($id) {
    global $DB;

    if (! $casestudy = $DB->get_record('casestudy', ['id' => $id])) {
        return false;
    }

    // Delete all dependent records
    $DB->delete_records('casestudy_overrides', ['casestudyid' => $id]);
    $DB->delete_records('casestudy_completion_rules', ['casestudyid' => $id]);

    // Delete submissions and their content/feedback
    $submissions = $DB->get_records('casestudy_submissions', ['casestudyid' => $id]);
    foreach ($submissions as $submission) {
        $DB->delete_records('casestudy_content', ['submissionid' => $submission->id]);
        $DB->delete_records('casestudy_grades', ['submissionid' => $submission->id]);
    }
    $DB->delete_records('casestudy_submissions', ['casestudyid' => $id]);

    // Delete fields
    $DB->delete_records('casestudy_fields', ['casestudyid' => $id]);

    // Delete the instance itself
    $DB->delete_records('casestudy', ['id' => $casestudy->id]);

    casestudy_grade_item_delete($casestudy);

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $casestudy The casestudy instance record
 * @return stdClass|null
 */
function casestudy_user_outline($course, $user, $mod, $casestudy) {
    global $DB;

    $result = new stdClass();
    $result->info = '';
    $result->time = 0;

    if (
        $submissions = $DB->get_records(
            'casestudy_submissions',
            ['casestudyid' => $casestudy->id, 'userid' => $user->id],
            'timemodified DESC'
        )
    ) {
        $submission = reset($submissions);
        $result->info = get_string('submitted', 'mod_casestudy');
        $result->time = $submission->timemodified;
    }

    return $result;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $casestudy the module instance record
 * @return void, is supposed to echo directly
 */
function casestudy_user_complete($course, $user, $mod, $casestudy) {
    global $DB;

    if (
        $submissions = $DB->get_records(
            'casestudy_submissions',
            ['casestudyid' => $casestudy->id, 'userid' => $user->id],
            'timemodified DESC'
        )
    ) {
        echo '<div class="casestudy-user-complete">';
        echo '<h4>' . get_string('submissions', 'mod_casestudy') . '</h4>';
        foreach ($submissions as $submission) {
            echo '<div class="submission">';
            echo '<strong>' . get_string('status') . ':</strong> ' . $submission->status . '<br>';
            echo '<strong>' . get_string('timemodified', 'mod_casestudy') . ':</strong> ' . userdate($submission->timemodified) . '<br>';
            echo '</div>';
        }
        echo '</div>';
    }
}

/**
 * Returns the course module info for the casestudy module.
 *
 * @param cm_info $cm Course module info object
 * @return cached_cm_info|null
 */
function casestudy_get_coursemodule_info($cm) {
    global $DB;

    $info = new cached_cm_info();

    $fields = 'id, name, intro, introformat, completionsatisfactory, cmpsatisfactorysubmissions, completioncategory';
    if ($casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], $fields)) {
        $info->name = $casestudy->name;

        if (!empty($casestudy->intro)) {
            // Set content and format for display on course page.
            $info->content = format_module_intro('casestudy', $casestudy, $cm->id, false);
        }
        $info->customdata['customcompletionrules']['completionsatisfactory'] = $casestudy->completionsatisfactory;
        $info->customdata['customcompletionrules']['completioncategory'] = $casestudy->completioncategory;
    }

    return $info;
}
/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in casestudy activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function casestudy_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function casestudy_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {
}

/**
 * Prints single activity item prepared by {@see casestudy_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@see get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function casestudy_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * @return boolean
 */
function casestudy_cron() {
    return true;
}

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function casestudy_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_ASSIGNMENT;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        case FEATURE_ADVANCED_GRADING:
            return true;
        default:
            return null;
    }
}

/**
 * Create grade item for given casestudy
 *
 * @param stdClass $casestudy object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function casestudy_grade_item_update($casestudy, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = ['itemname' => clean_param($casestudy->name, PARAM_NOTAGS), 'idnumber' => $casestudy->cmidnumber];

    if ($casestudy->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $casestudy->grade;
        $item['grademin']  = 0;
    } else if ($casestudy->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$casestudy->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/casestudy', $casestudy->course, 'mod', 'casestudy', $casestudy->id, 0, $grades, $item);
}

/**
 * Delete grade item for given casestudy
 *
 * @param stdClass $casestudy object
 * @return int
 */
function casestudy_grade_item_delete($casestudy) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/casestudy', $casestudy->course, 'mod', 'casestudy', $casestudy->id, 0, null, ['deleted' => 1]);
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $casestudy object
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function casestudy_get_user_grades($casestudy, $userid = 0) {
    global $CFG, $DB;

    $grades = [];

    $params = ['casestudyid' => $casestudy->id];
    if ($userid) {
        $params['userid'] = $userid;
    }

    $sql = "SELECT s.userid, f.grade, f.timemodified as dategraded, f.graderid as usermodified
              FROM {casestudy_submissions} s
              JOIN {casestudy_grades} f ON s.id = f.submissionid
             WHERE s.casestudyid = :casestudyid";

    if ($userid) {
        $sql .= " AND s.userid = :userid";
    }

    $sql .= " ORDER BY f.timemodified DESC";

    if ($records = $DB->get_records_sql($sql, $params)) {
        foreach ($records as $record) {
            if (!isset($grades[$record->userid])) {
                $grades[$record->userid] = new stdClass();
                $grades[$record->userid]->userid = $record->userid;
                $grades[$record->userid]->rawgrade = $record->grade;
                $grades[$record->userid]->dategraded = $record->dategraded;
                $grades[$record->userid]->usermodified = $record->usermodified;
            }
        }
    }

    // Check if due date has passed - add unsatisfactory grades for ungraded students with submissions
    if (!empty($casestudy->timeclose) && $casestudy->timeclose > 0 && time() > $casestudy->timeclose) {
        // Get all users with submissions but no grades
        $ungradedparams = ['casestudyid' => $casestudy->id];
        if ($userid) {
            $ungradedparams['userid'] = $userid;
        }

        $ungradedsql = "SELECT DISTINCT s.userid
                          FROM {casestudy_submissions} s
                     LEFT JOIN {casestudy_grades} f ON s.id = f.submissionid
                         WHERE s.casestudyid = :casestudyid
                           AND (f.grade IS NULL OR f.grade = '')";

        if ($userid) {
            $ungradedsql .= " AND s.userid = :userid";
        }

        if ($ungradedusers = $DB->get_records_sql($ungradedsql, $ungradedparams)) {
            foreach ($ungradedusers as $user) {
                if (!isset($grades[$user->userid])) {
                    if ($casestudy->grade < 0) {
                        $gradevalue = 1;
                    } else if ($casestudy->grade > 0) {
                        $gradevalue = 0;
                    } else {
                        continue;
                    }

                    $grades[$user->userid] = new stdClass();
                    $grades[$user->userid]->userid = $user->userid;
                    $grades[$user->userid]->rawgrade = $gradevalue;
                    $grades[$user->userid]->dategraded = $casestudy->timeclose;
                    $grades[$user->userid]->usermodified = null;
                }
            }
        }
    }

    return $grades;
}

/**
 * Update grades in central gradebook
 *
 * @param stdClass $casestudy object
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone
 */
function casestudy_update_grades($casestudy, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($grades = casestudy_get_user_grades($casestudy, $userid)) {
        casestudy_grade_item_update($casestudy, $grades);
    } else if ($userid && $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        casestudy_grade_item_update($casestudy, $grade);
    } else {
        casestudy_grade_item_update($casestudy);
    }
}

/**
 * Returns all other caps used in the module
 *
 * @return array
 */
function casestudy_get_extra_capabilities() {
    return [];
}

/**
 * Update completion criteria for a casestudy instance
 *
 * @param stdClass $casestudy The casestudy instance data from the form
 * @return void
 */
function casestudy_update_completion_criteria($casestudy) {
    global $DB;

    $time = time();

    // Delete existing rules for this case study.
    $DB->delete_records('casestudy_completion_rules', ['casestudyid' => $casestudy->id]);

    $sortorder = 0;

    // Save total satisfactory rule if enabled.
    if (!empty($casestudy->completionsatisfactory) && isset($casestudy->cmpsatisfactorysubmissions)) {
        $rule = new stdClass();
        $rule->casestudyid = $casestudy->id;
        $rule->enabled = 1;
        $rule->ruletype = CASESTUDY_COMPLETION_TOTAL;
        $rule->count = $casestudy->cmpsatisfactorysubmissions;
        $rule->fieldid = null;
        $rule->categoryvalue = null;
        $rule->sortorder = $sortorder++;
        $rule->timecreated = $time;
        $rule->timemodified = $time;

        $DB->insert_record('casestudy_completion_rules', $rule);
    }

    // Save category rules if any are enabled.
    if (!empty($casestudy->categoryrule_enabled)) {
        $indextovaluemap = [];
        $fields = $DB->get_records('casestudy_fields', [
            'casestudyid' => $casestudy->id, 'category' => 1], 'sortorder ASC', 'id, name, param1');

        $optionindex = 1;
        foreach ($fields as $field) {
            $values = $field->param1 ? json_decode($field->param1, true) : [];
            if (is_array($values)) {
                foreach ($values as $v) {
                    $indextovaluemap[$optionindex] = ['fieldid' => $field->id, 'value' => $v];
                    $optionindex++;
                }
            }
        }

        foreach ($casestudy->categoryrule_enabled as $index => $enabled) {
            // Only save if enabled and has a valid field selected.
            if (
                $enabled && !empty($casestudy->categoryrule_fieldid[$index]) &&
                $casestudy->categoryrule_fieldid[$index] != 0
            ) {
                $rule = new stdClass();
                $rule->casestudyid = $casestudy->id;
                $rule->enabled = 1;
                $rule->ruletype = CASESTUDY_COMPLETION_CATEGORY;
                $rule->count = isset($casestudy->categoryrule_count[$index]) ? $casestudy->categoryrule_count[$index] : 0;
                $rule->fieldid = $casestudy->categoryrule_fieldid[$index];
                $rule->categoryvalue = isset($casestudy->categoryrule_value[$index]) ? $casestudy->categoryrule_value[$index] : 0;
                $rule->sortorder = $sortorder++;
                $rule->timecreated = $time;
                $rule->timemodified = $time;

                $DB->insert_record('casestudy_completion_rules', $rule);
            }
        }
    }
}

/**
 * Extend the settings navigation with the case study settings
 *
 * @param settings_navigation $settingsnav
 * @param navigation_node $casestudynode
 */
function casestudy_extend_settings_navigation($settingsnav, $casestudynode) {
    global $PAGE, $DB;

    // Get the course module and context
    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    $context = context_module::instance($cm->id);
    $casestudy = $DB->get_record('casestudy', ['id' => $cm->instance], '*', MUST_EXIST);

    if (!has_capability('mod/casestudy:managefields', $context)) {
        return;
    }

    $fieldmanager = \mod_casestudy\local\field_manager::instance($casestudy->id);
    $hasfields = !empty($fieldmanager->get_fields());

    // Manage Fields - always available
    $casestudynode->add(
        get_string('managefields', 'mod_casestudy'),
        new moodle_url('/mod/casestudy/fields/manage.php', ['id' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'managefields',
        new pix_icon('i/customfield', get_string('managefields', 'mod_casestudy')),
    );

    // Manage Templates - available if user has capability
    if (has_capability('mod/casestudy:managetemplates', $context)) {
        $casestudynode->add(
            get_string('managetemplates', 'mod_casestudy'),
            new moodle_url('/mod/casestudy/templates.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'managetemplates',
            new pix_icon('i/settings', get_string('managetemplates', 'mod_casestudy'))
        );
    }

    // Only add submission-related navigation if fields exist
    if ($hasfields) {
        // Overrides
        if (has_capability('mod/casestudy:manageoverrides', $context)) {
            $casestudynode->add(
                get_string('overrides', 'mod_casestudy'),
                new moodle_url('/mod/casestudy/overrides.php', ['cmid' => $cm->id]),
                navigation_node::TYPE_SETTING,
                null,
                'mod_casestudy_useroverrides',
                new pix_icon('i/duration', get_string('overrides', 'mod_casestudy'))
            );
        }
    }
}

/**
 * File serving for casestudy module.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 */
function casestudy_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    $fs = get_file_storage();
    $file = null;

    if (strpos($filearea, 'field_') !== false || $filearea === 'submission_richtext') {
        // Per-submission areas: itemid is the submission ID.
        $itemid = array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        // For richtext fields, itemid is the submission ID - verify user can view it.
        if ($filearea === 'submission_richtext') {
            $submission = $DB->get_record('casestudy_submissions', ['id' => $itemid], 'userid, casestudyid', MUST_EXIST);

            // Verify this submission belongs to this case study.
            if ($submission->casestudyid != $cm->instance) {
                send_file_not_found();
            }

            // Check if user can view this submission.
            $canview = false;

            // User can view their own submission.
            if ($submission->userid == $USER->id) {
                $canview = true;
            }

            // Teachers/markers can view all submissions.
            if (has_capability('mod/casestudy:viewallsubmissions', $context)) {
                $canview = true;
            }

            if (!$canview) {
                send_file_not_found();
            }
        }

        $file = $fs->get_file($context->id, 'mod_casestudy', $filearea, $itemid, $filepath, $filename);
    } else if ($filearea === 'intro' || $filearea === 'graderinfo') {
        // Activity-level rich-text areas always use itemid 0. Grader information is for markers
        // only, so restrict it; the intro is visible to anyone who can reach the activity.
        if ($filearea === 'graderinfo' && !has_capability('mod/casestudy:viewallsubmissions', $context)) {
            send_file_not_found();
        }

        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $file = $fs->get_file($context->id, 'mod_casestudy', $filearea, 0, $filepath, $filename);
    } else if ($filearea === 'feedback') {
        // Grader feedback files: itemid is the casestudy_grades ID. Only the owning student
        // and markers may view them.
        $itemid = array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $grade = $DB->get_record('casestudy_grades', ['id' => $itemid], 'submissionid', MUST_EXIST);
        $submission = $DB->get_record(
            'casestudy_submissions',
            ['id' => $grade->submissionid],
            'userid, casestudyid',
            MUST_EXIST
        );

        // Verify this feedback belongs to this case study.
        if ($submission->casestudyid != $cm->instance) {
            send_file_not_found();
        }

        if ($submission->userid != $USER->id && !has_capability('mod/casestudy:viewallsubmissions', $context)) {
            send_file_not_found();
        }

        $file = $fs->get_file($context->id, 'mod_casestudy', $filearea, $itemid, $filepath, $filename);
    } else {
        send_file_not_found();
    }

    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    // Send the file. Force download only when the caller asked for it (e.g. download links);
    // leave images and other web-renderable files to display inline. Keep lifetime at 0 so
    // replacing an attachment with the same filename does not serve stale cached content.
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Update the name of the case study.
 *
 * @param  string $itemtype casestudy
 * @param  int $itemid ID of the edited case study
 * @param  string $newvalue New value to updated
 *
 * @return string Updated title of the casestudy.
 */
function mod_casestudy_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $PAGE;

    if ($itemtype === 'casestudyname') {
        $record = $DB->get_record('casestudy_fields', ['id' => $itemid], '*', MUST_EXIST);

        $cm = get_coursemodule_from_instance('casestudy', $record->casestudyid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $PAGE->set_context($context);

        require_login();
        // Check permission of the user to update the case study name.
        require_capability('mod/casestudy:managefields', $context);

        // Clean input and update the record.
        $newvalue = clean_param($newvalue, PARAM_NOTAGS);
        $DB->update_record('casestudy_fields', ['id' => $itemid, 'name' => $newvalue]);

        // Prepare the element for the output.
        $record->name = $newvalue;

        return new \core\output\inplace_editable(
            'mod_casestudy',
            'casestudyname',
            $record->id,
            true,
            \html_writer::tag('strong', format_string($record->name)),
            $record->name,
            get_string('casestudyname', 'casestudy'),
            get_string('newvaluefor', 'casestudy', format_string($record->name))
        );
    }

    return '';
}


/**
 * Lists all gradable areas for the advanced grading methods framework.
 *
 * @return array('string'=>'string') An array with area names as keys and descriptions as values
 */
function casestudy_grading_areas_list() {
    return [
        'submissions' => get_string('submissions', 'mod_casestudy'),
    ];
}

/**
 * Load a submission fragment for AJAX requests.
 *
 * @param array $args Arguments including 'context' and 'submissionid'
 * @return string Rendered HTML fragment
 * @throws \moodle_exception
 */
function mod_casestudy_output_fragment_load_submission($args) {
    global $PAGE;

    if ($args['context']->contextlevel !== CONTEXT_MODULE) {
        throw new \moodle_exception('invalidcontext');
    }

    $submissionid = $args['submissionid'] ?? 0;
    if (empty($submissionid)) {
        throw new \moodle_exception('invalidsubmissionid', 'mod_casestudy');
    }

    $renderer = $PAGE->get_renderer('mod_casestudy');

    return $renderer->render(\mod_casestudy\local\submission::instance($submissionid));
}

/**
 * Get effective case study settings for a user, applying any overrides.
 *
 * @param stdClass $casestudy The case study object
 * @param int $userid The user ID (0 for current user)
 * @return stdClass The case study object with overrides applied
 */
function casestudy_get_effective_settings($casestudy, $userid = 0) {
    global $DB, $USER;

    if ($userid == 0) {
        $userid = $USER->id;
    }

    // Clone the case study object so we don't modify the original.
    $effective = clone $casestudy;

    // Check if there's an override for this user.
    $override = $DB->get_record('casestudy_overrides', [
        'casestudyid' => $casestudy->id,
        'userid' => $userid,
    ]);

    if ($override) {
        // Apply override values if they are set.
        if (isset($override->timeclose)) {
            $effective->timeclose = $override->timeclose;
        }
        if (isset($override->maxattempts)) {
            $effective->maxattempts = $override->maxattempts;
        }
    }

    return $effective;
}

/**
 * Check if a user can still submit based on the deadline and overrides.
 *
 * @param stdClass $casestudy The case study object
 * @param int $userid The user ID (0 for current user)
 * @return bool True if the user can submit
 */
function casestudy_can_submit_by_deadline($casestudy, $userid = 0) {
    $effective = casestudy_get_effective_settings($casestudy, $userid);

    // If timeclose is 0 or not set, there's no deadline.
    if (empty($effective->timeclose)) {
        return true;
    }

    // Check if we're before the deadline.
    return time() < $effective->timeclose;
}

/**
 * Get the number of remaining submissions for a user.
 *
 * @param stdClass $casestudy The case study object
 * @param int $userid The user ID (0 for current user)
 * @return int Number of remaining submissions (-1 for unlimited)
 */
function casestudy_get_remaining_submissions($casestudy, $userid = 0) {
    global $DB, $USER;

    if ($userid == 0) {
        $userid = $USER->id;
    }

    $effective = casestudy_get_effective_settings($casestudy, $userid);

    // If maxsubmissions is 0, it's unlimited.
    if (empty($effective->maxsubmissions)) {
        return -1;
    }

    // Count existing submissions.
    $count = $DB->count_records('casestudy_submissions', [
        'casestudyid' => $casestudy->id,
        'userid' => $userid,
    ]);

    $remaining = $effective->maxsubmissions - $count;
    return max(0, $remaining);
}
