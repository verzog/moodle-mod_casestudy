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
 * Field type interface for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */
namespace mod_casestudy\local\field_types;

use mod_casestudy\local\field_manager;

/**
 * Contract implemented by every case study field type (text, file, richtext, etc.).
 *
 * Defines the rendering, validation, persistence and template-fallback hooks the manager
 * calls into. Concrete implementations live alongside this interface and typically extend
 * {@see base_field} for the default behaviours.
 */
interface fieldtype {
    /**
     * Get field type identifier
     *
     * @return string
     */
    public function get_type();

    /**
     * Get human-readable field type name
     *
     * @return string
     */
    public function get_type_name();

    /**
     * Get form for editing field settings
     *
     * @param field_manager $fieldmanager Field manager instance
     * @param bool $editing Whether we're editing an existing field
     * @return \mod_casestudy\local\forms\field_edit_form
     */
    public function get_edit_form(field_manager $fieldmanager, bool $editing);

    /**
     * Render field for form input
     *
     * @param \MoodleQuickForm $mform Form object
     * @param string $elementname Element name
     * @param mixed $value Current value
     * @return void
     */
    public function render_form_element($mform, $elementname, $value = null);
}
