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
 * Local library functions for casestudy module.
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

use mod_casestudy\local\submission_manager;

/**
 * Submission picker form used on grading pages (previous/next + autocomplete jump).
 */
class casestudy_selector_form extends moodleform {
    /**
     * Define the form structure (previous/next buttons, submission picker autocomplete).
     */
    protected function definition() {
        $mform = $this->_form;
        $options = $this->_customdata['options'];

        // Navigation buttons container.
        $navigationgroup = [];

        // Previous / next submission buttons.
        if ($this->has_previous_submission()) {
            $navigationgroup[] = $mform->createElement(
                'html',
                'previoussubmission',
                get_string('previoussubmission', 'mod_casestudy'),
                ['class' => 'btn btn-secondary']
            );
        }

        $navigationgroup[] = $mform->createElement('autocomplete', 'submissionid', '', $options);

        if ($this->has_next_submission()) {
            $navigationgroup[] = $mform->createElement(
                'html',
                'nextsubmission',
                get_string('nextsubmission', 'mod_casestudy'),
                ['class' => 'btn btn-secondary']
            );
        }

        if (!empty($navigationgroup)) {
            $mform->addGroup($navigationgroup, 'navigation', get_string('navigation', 'mod_casestudy'), ' ', false);
        }
    }

    /**
     * Check if there's a previous submission for navigation.
     *
     * @return bool
     */
    protected function has_previous_submission() {
        $casestudyid = $this->optional_param('casestudyid', 0, PARAM_INT);
        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);

        if (!$casestudyid || !$submissionid) {
            return false;
        }

        $manager = submission_manager::instance($casestudyid);
        return $manager->get_previous_submission_for_grading($submissionid) !== null;
    }

    /**
     * Check if there's a next submission for navigation.
     *
     * @return bool
     */
    protected function has_next_submission() {
        $casestudyid = $this->optional_param('casestudyid', 0, PARAM_INT);
        $submissionid = $this->optional_param('submissionid', 0, PARAM_INT);

        if (!$casestudyid || !$submissionid) {
            return false;
        }

        $manager = submission_manager::instance($casestudyid);
        return $manager->get_next_submission_for_grading($submissionid) !== null;
    }
}
