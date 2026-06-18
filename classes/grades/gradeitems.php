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
 * Grade item mappings for the activity.
 *
 * @package   mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

declare(strict_types=1);

namespace mod_casestudy\grades;

use core_grades\local\gradeitem\itemnumber_mapping;
use core_grades\local\gradeitem\advancedgrading_mapping;

/**
 * Grade item mappings for the activity.
 *
 * @package   mod_casestudy
 * @copyright 2025 Skin Cancer College Australasia
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gradeitems implements advancedgrading_mapping, itemnumber_mapping {
    /**
     * Return the list of grade item mappings for the assign.
     *
     * @return array
     */
    public static function get_itemname_mapping_for_component(): array {
        return [
            0 => 'submissions',
        ];
    }

    /**
     * Get the list of advanced grading item names for this component.
     *
     * @return array
     */
    public static function get_advancedgrading_itemnames(): array {
        return [
            'submissions',
        ];
    }
}
