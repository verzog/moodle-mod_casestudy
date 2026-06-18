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
 * Case Study external services and functions
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'mod_casestudy_update_field_order' => [
        'classname'   => 'mod_casestudy\external\update_field_order',
        'methodname'  => 'execute',
        'description' => 'Update the order of case study fields',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
        'capabilities' => 'mod/casestudy:managefields',
    ],

    'mod_casestudy_list_participants' => [
        'classname'   => 'mod_casestudy\external\list_participants',
        'methodname'  => 'execute',
        'description' => 'Update the order of case study fields',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
        'capabilities' => 'mod/casestudy:managefields',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    'mod_casestudy_get_submissions_for_grading' => [
        'classname'   => 'mod_casestudy\external\get_submissions_for_grading',
        'methodname'  => 'execute',
        'description' => 'Get list of submissions for grading navigation',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
        'capabilities' => 'mod/casestudy:grade',
    ],
];
