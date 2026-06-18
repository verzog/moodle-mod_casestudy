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
 * This file contains the restore activity for the casestudy module
 *
 * @package   mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/casestudy/backup/moodle2/restore_casestudy_stepslib.php');

/**
 * Casestudy restore task that provides all the settings and steps to perform one complete restore of the activity
 *
 * @package   mod_casestudy
 * @copyright 2025 Skin Cancer College Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_casestudy_activity_task extends restore_activity_task {
    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Casestudy only has one structure step.
        $this->add_step(new restore_casestudy_activity_structure_step('casestudy_structure', 'casestudy.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('casestudy', ['intro'], 'casestudy');
        $contents[] = new restore_decode_content('casestudy', ['graderinfo'], 'casestudy');
        $contents[] = new restore_decode_content('casestudy_fields', ['description'], 'casestudy_field');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder
     *
     * @return array of restore_decode_rule
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule(
            'CASESTUDYVIEWBYID',
            '/mod/casestudy/view.php?id=$1',
            'course_module'
        );
        $rules[] = new restore_decode_rule(
            'CASESTUDYINDEX',
            '/mod/casestudy/index.php?id=$1',
            'course'
        );

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the restore_logs_processor when restoring casestudy logs.
     * It must return one array of restore_log_rule objects
     *
     * @return array of restore_log_rule
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('casestudy', 'add', 'view.php?id={course_module}', '{casestudy}');
        $rules[] = new restore_log_rule('casestudy', 'update', 'view.php?id={course_module}', '{casestudy}');
        $rules[] = new restore_log_rule('casestudy', 'view', 'view.php?id={course_module}', '{casestudy}');
        $rules[] = new restore_log_rule('casestudy', 'submit', 'view.php?id={course_module}', '{casestudy}');
        $rules[] = new restore_log_rule('casestudy', 'grade', 'view.php?id={course_module}', '{casestudy}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied by the restore_logs_processor when restoring
     * course logs. It must return one array of restore_log_rule objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];

        $rules[] = new restore_log_rule('casestudy', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
