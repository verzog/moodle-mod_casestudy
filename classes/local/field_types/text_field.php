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
 * Short text field type for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\field_types;

use mod_casestudy\local\field_data;

defined('MOODLE_INTERNAL') || die();

/**
 * Short text field implementation
 */
class text_field extends base_field {
    /**
     * Get field type name
     *
     * @return string Field type identifier
     */
    public function get_type() {
        return 'text';
    }

    /**
     * Get human-readable field type name
     *
     * @return string Display name for field type
     */
    public function get_type_name() {
        return get_string('fieldtype_text', 'mod_casestudy');
    }

    /**
     * Render field for form input
     *
     * @param \MoodleQuickForm $mform Form object
     * @param string $elementname Element name
     * @param mixed $value Current value
     * @return void
     */
    public function render_form_element($mform, $elementname, $value = null) {

        $attributes = [
            'size' => 50,
            'maxlength' => 255,
        ];

        $mform->addElement('text', $elementname, $this->fielddata->name, $attributes);
        $mform->setType($elementname, PARAM_TEXT);

        // Set default value
        if ($value !== null) {
            $mform->setDefault($elementname, $value);
        }
    }

    /**
     * Get the raw input HTML element for template-based forms.
     *
     * @param string $fieldname The form field name
     * @param mixed $value Current field value
     * @param int|null $submissionid Submission ID
     * @param bool $haserrors Whether field has validation errors
     * @return string HTML for the input element
     */
    public function get_input_html(string $fieldname, $value = null, ?int $submissionid = null, bool $haserrors = false): string {
        $escapedvalue = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
        $required = !empty($this->fielddata->required) ? 'required' : '';
        $errorclass = $haserrors ? ' is-invalid' : '';

        return '<input type="text" name="' . $fieldname . '" id="' . $fieldname . '" ' .
               'value="' . $escapedvalue . '" class="form-control' . $errorclass . '" size="50" maxlength="255" ' . $required . '>';
    }

    /**
     * Render field for display (read-only)
     *
     * @param mixed $value Field value
     * @return string HTML for display
     */
    public function render_display($value, $submissionid = null) {
        if ($this->is_empty_value($value)) {
            return \html_writer::span('-', 'text-muted');
        }

        $label = \html_writer::label(format_string($this->fielddata->name), 'field-' . $this->fielddata->id, '', ['class' => 'casestudy-field-label font-weight-bold field-label']);
        $value = \html_writer::span(format_text($value, FORMAT_PLAIN), 'casestudy-field-text field-' . $this->fielddata->id);

        return $value;
    }

    /**
     * Process and clean input value
     *
     * @param mixed $value Raw input value
     * @return mixed Cleaned value
     */
    public function process_input($value, $data): field_data {
        $value = clean_param($value, PARAM_TEXT);
        $fielddata = field_data::create((object) ['content' => $value]);

        return $fielddata;
    }

    /**
     * Validate field input
     *
     * @param mixed $value Input value
     * @param array|null $files Form files
     * @param array|null $data Form data
     * @param bool $isdraft True if saving as draft (skip required field validation)
     * @return array Array of error messages
     */
    public function validate_input($value, $files = null, $data = null, $isdraft = false) {
        $errors = parent::validate_input($value, $files, $data, $isdraft);

        // Additional validation for text fields
        if (!empty($value) && strlen($value) > 255) {
            $errors[] = get_string('error_text_too_long', 'mod_casestudy', 255);
        }

        return $errors;
    }

    /**
     * Get field configuration for editing
     *
     * @return array Configuration array
     */
    public function get_config() {
        $config = parent::get_config();
        return $config;
    }

    /**
     * Process configuration form data
     *
     * @param array $data Form data
     * @return array Processed configuration
     */
    public function process_config_form($data) {
        return parent::process_config_form($data);
    }

    /**
     * Check if this field type supports categories
     *
     * @return bool True if supports categories
     */
    public function supports_categories() {
        return false;
    }

    /**
     * Get searchable text for this field value
     *
     * @param mixed $value Field value
     * @return string Searchable text
     */
    public function get_search_content($value) {
        return strip_tags($value);
    }

    /**
     * Get field display value for list views
     *
     * @param mixed $value Field value
     * @return string Display text for lists
     */
    public function get_list_display($value, $row) {
        if ($this->is_empty_value($value)) {
            return '-';
        }

        // For text fields, truncate if too long
        $display = strip_tags($value);
        if (strlen($display) > 30) {
            $display = substr($display, 0, 27) . '...';
        }

        return $display;
    }

    /**
     * Set field parameters from configuration
     *
     * @param object $field Field object (passed by reference)
     * @param array $config Configuration array
     * @return void
     */
    public function set_field_params(&$field, $config) {
        parent::set_field_params($field, $config);
    }

    /**
     * Get form element names for this field type's parameters
     *
     * @return array Array of form element names
     */
    public function get_param_form_elements() {
        return [];
    }
}
