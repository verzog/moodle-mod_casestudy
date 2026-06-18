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
 * Dropdown field type for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\field_types;

use mod_casestudy\local\field_data;

defined('MOODLE_INTERNAL') || die();

/**
 * Dropdown field implementation - Options stored in param1
 */
class dropdown_field extends base_field {

    /**
     * Get field type name
     *
     * @return string Field type identifier
     */
    public function get_type() {
        return 'dropdown';
    }

    /**
     * Get human-readable field type name
     *
     * @return string Display name for field type
     */
    public function get_type_name() {
        return get_string('fieldtype_dropdown', 'mod_casestudy');
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
        $options = $this->get_options();

        // Add empty option for non-required fields
        if (!$this->fielddata->required) {
            $options = ['' => get_string('choose')] + $options;
        }

        $mform->addElement('select', $elementname, $this->fielddata->name, $options);
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
        $options = $this->get_options();
        $required = !empty($this->fielddata->required) ? 'required' : '';
        $errorclass = $haserrors ? ' is-invalid' : '';

        $html = '<select name="' . $fieldname . '" id="' . $fieldname . '" class="form-control custom-select' . $errorclass . '" ' . $required . '>';

        // Add empty option for non-required fields
        if (!$this->fielddata->required) {
            $html .= '<option value="">' . get_string('choose') . '</option>';
        }

        foreach ($options as $optionkey => $optionlabel) {
            $selected = ($value !== null && $value == $optionkey) ? ' selected' : '';
            $escapedlabel = htmlspecialchars($optionlabel, ENT_QUOTES, 'UTF-8');
            $escapedkey = htmlspecialchars($optionkey, ENT_QUOTES, 'UTF-8');
            $html .= '<option value="' . $escapedkey . '"' . $selected . '>' . $escapedlabel . '</option>';
        }

        $html .= '</select>';

        return $html;
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

        // Get options to display the label instead of value
        $options = $this->get_options();
        $displayvalue = isset($options[$value]) ? $options[$value] : $value;

        $label = \html_writer::label(
            format_string($this->fielddata->name),
            'field-' . $this->fielddata->id,
            '', ['class' => 'casestudy-field-label font-weight-bold field-label']);

        $valuespan = \html_writer::span(format_string($displayvalue), 'casestudy-field-dropdown field-' . $this->fielddata->id);

        return $valuespan;
    }

    /**
     * Process and clean input value
     *
     * @param mixed $value Raw input value
     * @return field_data Cleaned value
     */
    public function process_input($value, $data) : field_data {
        $value = clean_param($value, PARAM_TEXT);
        return field_data::create((object) ['content' => $value]);
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

        // Check if value is in allowed options
        if (!empty($value)) {
            $options = $this->get_options();
            if (!array_key_exists($value, $options)) {
                $errors[] = get_string('error_invalid_option', 'mod_casestudy');
            }
        }

        return $errors;
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

        // Handle param1 for options
        if (isset($config['param1']) && is_array($config['param1'])) {
            // Convert array of options to JSON
            $field->param1 = json_encode(array_values($config['param1']));
        }
    }

    /**
     * Add parameter-specific form elements
     *
     * @param \MoodleQuickForm $mform Form object
     * @param string $prefix Element name prefix
     * @return void
     */
    public function additional_form_elements(&$mform) {
        // Options textarea
        $mform->addElement('textarea', 'param1', get_string('options', 'mod_casestudy'), ['rows' => 5, 'cols' => 50]);
        $mform->setType('param1', PARAM_TEXT);
        $mform->addHelpButton('param1', 'options', 'mod_casestudy');
    }

    /**
     * Get form element names for this field type's parameters
     *
     * @return array Array of form element names
     */
    public function get_param_form_elements() {
        return ['param1'];
    }

    /**
     * Get parameter value with defaults
     *
     * @param string $paramname Parameter name
     * @param mixed $default Default value if not set
     * @param array $defaults Array of default values
     * @return mixed Parameter value
     */
    public function get_param($paramname, $default = null, array $defaults=[]) {

        if (!isset($this->fielddata->$paramname) && !array_key_exists($paramname, $defaults)) {
            return $default;
        }

        $paramvalue = isset($this->fielddata->$paramname) ? $this->fielddata->$paramname : $defaults[$paramname];

        return json_decode($paramvalue, true);
    }

    /**
     * Set default values for parameter form elements
     *
     * @param array $defaults Defaults array (passed by reference)
     * @param string $prefix Element name prefix
     * @return void
     */
    public function set_param_form_defaults(&$defaults, $prefix = '') {
        $options = $this->get_param('param1', [], $defaults);

        if (!empty($options)) {
            $defaults[$prefix . 'param1'] = implode("\n", $options);
        }
    }

    /**
     * Process configuration form data
     *
     * @param array $data Form data
     * @return array Processed configuration
     */
    public function process_config_form($data) {
        $config = parent::process_config_form($data);

        // Process options from textarea
        if (isset($data['param1'])) {
            $options = array_filter(array_map('trim', explode("\n", $data['param1'])));
            $config['param1'] = $options;
        }

        return $config;
    }

    /**
     * Check if this field type supports categories
     *
     * @return bool True if supports categories
     */
    public function supports_categories() {
        return true; // Dropdown options can be used as categories
    }

    /**
     * Get category values for completion criteria
     *
     * @return array Array of possible category values
     */
    public function get_category_values() {
        return array_keys($this->get_options());
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

        $options = $this->get_options();
        return isset($options[$value]) ? $options[$value] : $value;
    }

    /**
     * Get options array from param1
     *
     * @return array Options array
     */
    private function get_options() {

        $options = $this->get_param('param1', []);

        // Convert array to key-value pairs (value => value)
        if (is_array($options)) {
            return array_combine($options, $options);
        }

        return [];
    }
}