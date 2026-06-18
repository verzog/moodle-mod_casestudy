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
 * Checkbox field type for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\field_types;

use mod_casestudy\local\field_data;

/**
 * Checkbox field implementation - Options stored in param1
 */
class checkbox_field extends base_field {
    /**
     * Get field type name
     *
     * @return string Field type identifier
     */
    public function get_type() {
        return 'checkbox';
    }

    /**
     * Get human-readable field type name
     *
     * @return string Display name for field type
     */
    public function get_type_name() {
        return get_string('fieldtype_checkbox', 'mod_casestudy');
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

        if (empty($options)) {
            // If no options, show a note
            $mform->addElement(
                'static',
                $elementname . '_note',
                $this->fielddata->name,
                get_string('no_options_configured', 'mod_casestudy')
            );
            return;
        }

        $checkboxarray = [];
        foreach ($options as $key => $option) {
            $checkboxarray[] = $mform->createElement('checkbox', $elementname . '[' . $key . ']', '', $option);
        }

        $mform->addGroup($checkboxarray, $elementname . '_group', $this->fielddata->name, ['<br/>'], false);
    }

    /**
     * Set default values for submission form
     *
     * @param \MoodleQuickForm $mform Form object
     * @param mixed $value Current value
     * @param string $fieldname Field name
     * @return void
     */
    public function submission_definition_after_data($mform, $value, $fieldname) {

        // Parse existing values (stored as JSON array)
        $selectedvalues = [];
        if ($value) {
            $selectedvalues = is_array($value) ? $value : json_decode($value, true);
            if (!is_array($selectedvalues)) {
                $selectedvalues = [$value]; // Handle single value
            }
        }

        foreach ($selectedvalues as $key) {
            $mform->setDefault($fieldname . '[' . $key . ']', 1);
        }
    }

    /**
     * Update for submission data for form set_data
     * @param array $formdata Form data (passed by reference)
     * @param field_data $contentdata Field content data
     * @param string $fieldname Field name
     */
    public function get_form_value(&$submissiondata, $contentdata, $fieldname) {

        $selectedvalues = [];
        if (!empty($contentdata->content)) {
            $selectedvalues = json_decode($contentdata->content, true);
            if (is_array($selectedvalues)) {
                array_map(function ($v) use (&$submissiondata, $fieldname) {
                    $submissiondata[$fieldname . '[' . $v . ']'] = 1;
                }, $selectedvalues);
            }
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

        if (empty($options)) {
            return '<div class="alert alert-warning">' .
                   get_string('no_options_configured', 'mod_casestudy') . '</div>';
        }

        // Parse selected values
        $selectedvalues = [];
        if ($value) {
            if (is_array($value)) {
                $selectedvalues = array_keys(array_filter($value));
            } else {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $selectedvalues = $decoded;
                }
            }
        }

        $errorclass = $haserrors ? ' is-invalid' : '';

        $html = '<div class="form-check-group">';
        foreach ($options as $key => $option) {
            $checked = in_array($key, $selectedvalues) ? ' checked' : '';
            $escapedoption = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
            $escapedkey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $inputid = $fieldname . '_' . $escapedkey;

            $html .= '<div class="form-check">';
            $html .= '<input type="checkbox" name="' . $fieldname . '[' . $escapedkey . ']" ' .
                     'id="' . $inputid . '" value="1" class="form-check-input' . $errorclass . '"' . $checked . '>';
            $html .= '<label class="form-check-label" for="' . $inputid . '">' . $escapedoption . '</label>';
            $html .= '</div>';
        }
        $html .= '</div>';

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

        $selectedvalues = [];
        if ($value) {
            $selectedvalues = is_array($value) ? $value : json_decode($value, true);
            if (!is_array($selectedvalues)) {
                $selectedvalues = [$value];
            }
        }

        $options = $this->get_options();
        $displayvalues = [];
        foreach ($selectedvalues as $selectedvalue) {
            if (isset($options[$selectedvalue])) {
                $displayvalues[] = $options[$selectedvalue];
            }
        }

        $displaytext = !empty($displayvalues) ? implode(', ', $displayvalues) : '-';
        $valuespan = \html_writer::span(format_string($displaytext), 'casestudy-field-checkbox field-' . $this->fielddata->id);
        return $valuespan;
    }

    /**
     * Process and clean input value
     *
     * @param mixed $value Raw input value
     * @return mixed Cleaned value
     */
    public function process_input($value, $data): field_data {

        if (is_array($value)) {
            // Filter out unchecked boxes and get keys
            $selectedvalues = array_keys(array_filter($value));
            $value = json_encode($selectedvalues);
        }

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
        $errors = [];

        // For checkboxes, check if at least one is selected for required fields
        if (!$isdraft && $this->fielddata->required) {
            $selectedvalues = [];
            if ($value) {
                if (is_array($value)) {
                    $selectedvalues = array_keys(array_filter($value));
                } else {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        $selectedvalues = $decoded;
                    }
                }
            }

            if (empty($selectedvalues)) {
                $errors[] = get_string('required');
            }
        }

        return $errors;
    }

    /**
     * Check if value is considered empty
     *
     * @param mixed $value Value to check
     * @return bool True if empty
     */
    protected function is_empty_value($value) {
        if (is_array($value)) {
            return empty(array_filter($value));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return empty($decoded);
            }
        }

        return empty($value);
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
     * Get param value with defaults.
     */
    public function get_param($paramname, $default = null, array $defaults = []) {

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
        return true;
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

        // Parse stored values
        $selectedvalues = [];
        if ($value) {
            $selectedvalues = is_array($value) ? $value : json_decode($value, true);
            if (!is_array($selectedvalues)) {
                $selectedvalues = [$value];
            }
        }

        // Get options to display labels
        $options = $this->get_options();
        $displayvalues = [];
        foreach ($selectedvalues as $selectedvalue) {
            if (isset($options[$selectedvalue])) {
                $displayvalues[] = $options[$selectedvalue];
            }
        }

        $displaytext = implode(', ', $displayvalues);
        if (strlen($displaytext) > 30) {
            $displaytext = substr($displaytext, 0, 27) . '...';
        }

        return $displaytext;
    }

    /**
     * Get options array from param1
     *
     * @return array Options array
     */
    private function get_options() {
        $optionsarray = $this->get_param('param1', []);

        if (is_array($optionsarray)) {
            return array_combine(array_map(fn($v) => base64_encode($v), $optionsarray), $optionsarray);
        }

        return [];
    }
}
