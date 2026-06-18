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
 * Definition backup-activity-task
 *
 * @package   mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die('No direct access !');

require_once($CFG->dirroot . '/mod/casestudy/backup/moodle2/backup_casestudy_stepslib.php');

/**
 * Step to perform instance database backup.
 */
class backup_casestudy_activity_task extends backup_activity_task {
    /**
     * No specific settings for this activity
     */
    public function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define backup structure steps to store the instance data in the casestudy.xml.
     */
    public function define_my_steps() {
        // Only single structure step.
        $this->add_step(new backup_casestudy_activity_structure_step('casestudy_structure', 'casestudy.xml'));
    }

    /**
     * No content encoding needed for this activity
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the same content with no changes
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        $search = "/(" . $base . "\/mod\/casestudy\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@CASESTUDYINDEX*$2@$', $content);

        $search = "/(" . $base . "\/mod\/casestudy\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@CASESTUDYVIEWBYID*$2@$', $content);

        return $content;
    }
}
