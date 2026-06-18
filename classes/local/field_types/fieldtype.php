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
 * Field type interface for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_casestudy\local\field_types;

use mod_casestudy\local\field_manager;
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