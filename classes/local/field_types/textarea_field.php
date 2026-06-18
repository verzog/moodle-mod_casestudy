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
 * Text area field type for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\field_types;

defined('MOODLE_INTERNAL') || die();

/**
 * Text area field implementation - Example of parameter handling
 */
class textarea_field extends base_field {
    /**
     * Get field type name
     *
     * @return string Field type identifier
     */
    public function get_type() {
        return 'textarea';
    }

    /**
     * Get human-readable field type name
     *
     * @return string Display name for field type
     */
    public function get_type_name() {
        return get_string('fieldtype_textarea', 'mod_casestudy');
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

        $dimensions = $this->get_param('param1', []);

        $attributes = [
            'rows' => isset($dimensions['height']) ? $dimensions['height'] : 4,
            'cols' => isset($dimensions['width']) ? $dimensions['width'] : 50,
        ];

        $mform->addElement('textarea', $elementname, $this->fielddata->name, $attributes);
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

        $dimensions = $this->get_param('param1', []);
        $rows = isset($dimensions['height']) ? (int)$dimensions['height'] : 4;
        $cols = isset($dimensions['width']) ? (int)$dimensions['width'] : 50;

        return '<textarea name="' . $fieldname . '" id="' . $fieldname . '" ' .
               'class="form-control' . $errorclass . '" rows="' . $rows . '" cols="' . $cols . '" ' . $required . '>' .
               $escapedvalue . '</textarea>';
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

        $label = \html_writer::label(format_string($this->fielddata->name), 'field-' . $this->fielddata->id, '', [
            'class' => 'casestudy-field-label font-weight-bold field-label']);

        $value = \html_writer::div(format_text($value, FORMAT_MOODLE), 'casestudy-field-textarea field-' . $this->fielddata->id);

        return $value;
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
        if (isset($config['param1']) && is_array($config['param1'])) {
            $dimensions = [
                'width' => (int) $config['param1']['width'] ?? null,
                'height' => (int) $config['param1']['height'] ?? null,
            ];
            $field->param1 = json_encode($dimensions);
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

        $groupname = 'dimensions_group';

        // Grouped elements for width and height.
        $group = [];
        $group[] = $mform->createElement('text', 'param1[width]', '', ['size' => 5]);
        $group[] = $mform->createElement('static', '', '', ' x ');
        $group[] = $mform->createElement('text', 'param1[height]', '', ['size' => 5]);

        $mform->addGroup($group, $groupname, get_string('dimensions', 'mod_casestudy'), '', false);

        $mform->setType('param1[width]', PARAM_INT);
        $mform->setType('param1[height]', PARAM_INT);

        $mform->addHelpButton($groupname, 'dimensions', 'mod_casestudy');
    }

    /**
     * Get form element names for this field type's parameters
     *
     * @return array Array of form element names
     */
    public function get_param_form_elements() {

        return [
            'param1[width]',
            'param1[height]',
        ];
    }


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

        $dimensions = $this->get_param('param1', [], $defaults);

        if (isset($dimensions['width'])) {
            $defaults[$prefix . 'param1[width]'] = $dimensions['width'];
        }
        if (isset($dimensions['height'])) {
            $defaults[$prefix . 'param1[height]'] = $dimensions['height'];
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

        if (isset($data['param1'])) {
            $config['param1'] = $data['param1'];
        }

        return $config;
    }
}
