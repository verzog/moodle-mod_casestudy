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
 * List participants for grading external function
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once("$CFG->dirroot/user/externallib.php");


use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;

use context_module;
use mod_casestudy\local\field_manager;
use core_user_external;

/**
 * External function for updating field order
 */
class list_participants extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function execute_parameters() {

        return new external_function_parameters(
            array(
                'casestudyid' => new external_value(PARAM_INT, 'casestudy instance id'),
                'groupid' => new external_value(PARAM_INT, 'group id'),
                'filter' => new external_value(PARAM_RAW, 'search string to filter the results'),
                'skip' => new external_value(PARAM_INT, 'number of records to skip', VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'maximum number of records to return', VALUE_DEFAULT, 0),
                'onlyids' => new external_value(PARAM_BOOL, 'Do not return all user fields', VALUE_DEFAULT, false),
                'includeenrolments' => new external_value(PARAM_BOOL, 'Do return courses where the user is enrolled',
                                                          VALUE_DEFAULT, true),
                'tablesort' => new external_value(PARAM_BOOL, 'Apply current user table sorting preferences.',
                                                          VALUE_DEFAULT, false)
            )
        );
    }

    /**
     * Validates the casestudy instance and returns related objects.
     *
     * @param int $casestudyid the casestudy instance id
     * @return array containing the casestudy instance, course, cm and context
     * @throws \moodle_exception if the casestudy id is invalid
     */
    public static function validate_casestudy($casestudyid) {
        global $DB;

        $casestudy = \mod_casestudy\local\casestudy::instance($casestudyid);

        if (!$casestudy) {
            throw new \moodle_exception('invalidcasestudyid', 'mod_casestudy');
        }

        return array($casestudy, $casestudy->get_course(), $casestudy->get_cm(), $casestudy->get_context());
    }

    /**
     * Retrieves the list of students to be graded for the casestudyment.
     *
     * @param int $casestudyid the casestudy instance id
     * @param int $groupid the current group id
     * @param string $filter search string to filter the results.
     * @param int $skip Number of records to skip
     * @param int $limit Maximum number of records to return
     * @param bool $onlyids Only return user ids.
     * @param bool $includeenrolments Return courses where the user is enrolled.
     * @param bool $tablesort Apply current user table sorting params from the grading table.
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function execute($casestudyid, $groupid, $filter, $skip,
            $limit, $onlyids, $includeenrolments, $tablesort) {
        global $DB, $CFG, $PAGE;

        require_once($CFG->dirroot . "/user/lib.php");
        require_once($CFG->libdir . '/grouplib.php');

        $params = self::validate_parameters(self::execute_parameters(),
                        [
                            'casestudyid' => $casestudyid,
                            'groupid' => $groupid,
                            'filter' => $filter,
                            'skip' => $skip,
                            'limit' => $limit,
                            'onlyids' => $onlyids,
                            'includeenrolments' => $includeenrolments,
                            'tablesort' => $tablesort
                        ]);
        $warnings = array();

        list($casestudy, $course, $cm, $context) = self::validate_casestudy($params['casestudyid']);

        require_capability('mod/casestudy:view', $context);

        $PAGE->set_context($context);

        $participants = array();
        $coursegroups = [];
        if (groups_group_visible($params['groupid'], $course, $cm)) {
            $participants = $casestudy->list_participants_with_filter_status_and_group($params['groupid'], $params['tablesort']);
            $coursegroups = groups_get_all_groups($course->id);
        }

        $userfields = user_get_default_fields();
        if (!$params['includeenrolments']) {
            // Remove enrolled courses from users fields to be returned.
            $key = array_search('enrolledcourses', $userfields);
            if ($key !== false) {
                unset($userfields[$key]);
            } else {
                throw new moodle_exception('invaliduserfield', 'error', '', 'enrolledcourses');
            }
        }

        $result = array();
        $index = 0;
        foreach ($participants as $record) {
            // Preserve the fullname set by the casestudyment.
            $fullname = $record->fullname;
            $searchable = $fullname;
            $match = false;
            if (empty($filter)) {
                $match = true;
            } else {
                $filter = core_text::strtolower($filter);
                $value = core_text::strtolower($searchable);
                if (is_string($value) && (core_text::strpos($value, $filter) !== false)) {
                    $match = true;
                }
            }
            if ($match) {
                $index++;
                if ($index <= $params['skip']) {
                    continue;
                }
                if (($params['limit'] > 0) && (($index - $params['skip']) > $params['limit'])) {
                    break;
                }

                $userdetails = user_get_user_details($record, $course, $userfields);
                $userdetails['fullname'] = $fullname;
                $userdetails['submitted'] = $record->submitted;
                $userdetails['requiregrading'] = $record->requiregrading;
                $userdetails['grantedextension'] = $record->grantedextension;
                $userdetails['submissionstatus'] = $record->submissionstatus;
                if (!empty($record->groupid)) {
                    $userdetails['groupid'] = $record->groupid;

                    if (!empty($coursegroups[$record->groupid])) {
                        // Format properly the group name.
                        $group = $coursegroups[$record->groupid];
                        $userdetails['groupname'] = \core_external\util::format_string($group->name, $context);
                    }
                }
                // Unique id is required for blind marking.
                $userdetails['recordid'] = -1;
                if (!empty($record->recordid)) {
                    $userdetails['recordid'] = $record->recordid;
                }

                $result[] = $userdetails;
            }
        }
        return $result;
    }

    /**
     * Returns the description of the results of the mod_casestudy_external::list_participants() method.
     *
     * @return \core_external\external_description
     * @since Moodle 3.1
     */
    public static function execute_returns() {
        // Get user description.
        $userdesc = core_user_external::user_description();
        $unneededproperties = [
            'auth', 'confirmed', 'lang', 'calendartype', 'theme', 'timezone', 'mailformat'
        ];
        // Remove unneeded properties for consistency with the previous version.
        foreach ($unneededproperties as $prop) {
            unset($userdesc->keys[$prop]);
        }

        // Override property attributes for consistency with the previous version.
        $userdesc->keys['fullname']->type = PARAM_NOTAGS;
        $userdesc->keys['profileimageurlsmall']->required = VALUE_OPTIONAL;
        $userdesc->keys['profileimageurl']->required = VALUE_OPTIONAL;
        $userdesc->keys['email']->desc = 'Email address';
        $userdesc->keys['idnumber']->desc = 'The idnumber of the user';
        $userdesc->keys['recordid'] = new external_value(PARAM_INT, 'record id');

        // Define other keys.
        $otherkeys = [
            'groups' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'group id'),
                        'name' => new external_value(PARAM_RAW, 'group name'),
                        'description' => new external_value(PARAM_RAW, 'group description'),
                    ]
                ), 'user groups', VALUE_OPTIONAL
            ),
            'roles' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'roleid' => new external_value(PARAM_INT, 'role id'),
                        'name' => new external_value(PARAM_RAW, 'role name'),
                        'shortname' => new external_value(PARAM_ALPHANUMEXT, 'role shortname'),
                        'sortorder' => new external_value(PARAM_INT, 'role sortorder')
                    ]
                ), 'user roles', VALUE_OPTIONAL
            ),
            'enrolledcourses' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT, 'Id of the course'),
                        'fullname' => new external_value(PARAM_RAW, 'Fullname of the course'),
                        'shortname' => new external_value(PARAM_RAW, 'Shortname of the course')
                    ]
                ), 'Courses where the user is enrolled - limited by which courses the user is able to see', VALUE_OPTIONAL
            ),
            'submitted' => new external_value(PARAM_BOOL, 'have they submitted their casestudyment'),
            'requiregrading' => new external_value(PARAM_BOOL, 'is their submission waiting for grading'),
            'grantedextension' => new external_value(PARAM_BOOL, 'have they been granted an extension'),
            'submissionstatus' => new external_value(PARAM_ALPHA, 'The submission status (new, draft, reopened or submitted).
                Empty when not submitted.', VALUE_OPTIONAL),
            'groupid' => new external_value(PARAM_INT, 'for group casestudyments this is the group id', VALUE_OPTIONAL),
            'groupname' => new external_value(PARAM_TEXT, 'for group casestudyments this is the group name', VALUE_OPTIONAL),
        ];

        // Merge keys.
        $userdesc->keys = array_merge($userdesc->keys, $otherkeys);
        return new external_multiple_structure($userdesc);
    }

}