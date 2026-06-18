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
 * Case Study external services and functions
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        'capabilities' => 'mod/casestudy:managefields'
    ],

    'mod_casestudy_list_participants' => [
        'classname'   => 'mod_casestudy\external\list_participants',
        'methodname'  => 'execute',
        'description' => 'Update the order of case study fields',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
        'capabilities' => 'mod/casestudy:managefields',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ],

    'mod_casestudy_get_submissions_for_grading' => [
        'classname'   => 'mod_casestudy\external\get_submissions_for_grading',
        'methodname'  => 'execute',
        'description' => 'Get list of submissions for grading navigation',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
        'capabilities' => 'mod/casestudy:grade'
    ],
];
