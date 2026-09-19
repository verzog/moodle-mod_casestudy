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
require_once($CFG->dirroot . '/user/lib.php');


use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use core_text;
use user_picture;

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
            [
                'casestudyid' => new external_value(PARAM_INT, 'casestudy instance id'),
                'groupid' => new external_value(PARAM_INT, 'group id'),
                'filter' => new external_value(PARAM_RAW, 'search string to filter the results'),
                'skip' => new external_value(PARAM_INT, 'number of records to skip', VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'maximum number of records to return', VALUE_DEFAULT, 0),
                'onlyids' => new external_value(PARAM_BOOL, 'Do not return all user fields', VALUE_DEFAULT, false),
                'includeenrolments' => new external_value(
                    PARAM_BOOL,
                    'Do return courses where the user is enrolled',
                    VALUE_DEFAULT,
                    true
                ),
                'tablesort' => new external_value(
                    PARAM_BOOL,
                    'Apply current user table sorting preferences.',
                    VALUE_DEFAULT,
                    false
                ),
            ]
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

        return [$casestudy, $casestudy->get_course(), $casestudy->get_cm(), $casestudy->get_context()];
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
    public static function execute(
        $casestudyid,
        $groupid,
        $filter,
        $skip,
        $limit,
        $onlyids,
        $includeenrolments,
        $tablesort
    ) {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/grouplib.php');

        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'casestudyid' => $casestudyid,
                'groupid' => $groupid,
                'filter' => $filter,
                'skip' => $skip,
                'limit' => $limit,
                'onlyids' => $onlyids,
                'includeenrolments' => $includeenrolments,
                'tablesort' => $tablesort,
            ]
        );

        [$casestudy, $course, $cm, $context] = self::validate_casestudy($params['casestudyid']);

        // This service backs the grading action bar's participant picker, so restrict it to staff
        // who may view other learners' submissions. This also stops a learner enumerating the
        // participant list and their identity fields.
        if (!has_any_capability(['mod/casestudy:viewallsubmissions', 'mod/casestudy:grade'], $context)) {
            require_capability('mod/casestudy:viewallsubmissions', $context);
        }

        $PAGE->set_context($context);

        $participants = [];
        if (groups_group_visible($params['groupid'], $course, $cm)) {
            $participants = $casestudy->list_participants_with_filter_status_and_group(
                $params['groupid'],
                $params['tablesort']
            );
        }

        // Only expose the email address to staff who are allowed to see user identity fields.
        $showemail = has_capability('moodle/site:viewuseridentity', $context);

        // Optional server-side name filter. The client fetches the whole set once and filters in
        // the browser, so this is normally empty, but honour it when supplied.
        $needle = core_text::strtolower(trim($params['filter']));

        $result = [];
        $index = 0;
        foreach ($participants as $record) {
            $fullname = $record->fullname;

            if ($needle !== '' && core_text::strpos(core_text::strtolower($fullname), $needle) === false) {
                continue;
            }

            $index++;
            if ($index <= $params['skip']) {
                continue;
            }
            if ($params['limit'] > 0 && ($index - $params['skip']) > $params['limit']) {
                break;
            }

            $userdetails = [
                'id' => (int) $record->id,
                'fullname' => $fullname,
            ];

            if (empty($params['onlyids'])) {
                // Name parts drive the combobox's search-as-you-type matching.
                $userdetails['firstname'] = $record->firstname;
                $userdetails['lastname'] = $record->lastname;
                if ($showemail && !empty($record->email)) {
                    $userdetails['email'] = $record->email;
                }

                // The avatar is optional in the dropdown; never let building it break the list.
                try {
                    $userpicture = new user_picture($record);
                    $userpicture->size = 100;
                    $userdetails['profileimageurl'] = $userpicture->get_url($PAGE)->out(false);
                    $userpicture->size = 35;
                    $userdetails['profileimageurlsmall'] = $userpicture->get_url($PAGE)->out(false);
                } catch (\Throwable $e) {
                    // Leave the image out and let the template fall back to initials.
                    debugging('casestudy: could not build user picture: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            $result[] = $userdetails;
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
        // A lean, self-contained shape: just what the grading action bar's participant picker
        // needs to search and render. Building this directly keeps the service resilient to
        // core user-description changes across Moodle upgrades.
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'ID of the user'),
                    'fullname' => new external_value(PARAM_NOTAGS, 'The full name of the user'),
                    'firstname' => new external_value(PARAM_NOTAGS, 'The first name of the user', VALUE_OPTIONAL),
                    'lastname' => new external_value(PARAM_NOTAGS, 'The surname of the user', VALUE_OPTIONAL),
                    'email' => new external_value(PARAM_NOTAGS, 'Email address', VALUE_OPTIONAL),
                    'profileimageurl' => new external_value(PARAM_URL, 'User image profile URL - big version', VALUE_OPTIONAL),
                    'profileimageurlsmall' => new external_value(
                        PARAM_URL,
                        'User image profile URL - small version',
                        VALUE_OPTIONAL
                    ),
                ]
            )
        );
    }
}
