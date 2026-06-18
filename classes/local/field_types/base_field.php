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
 * Base field type class for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\field_types;

use mod_casestudy\local\field_data;
use mod_casestudy\local\field_types\fieldtype;
use stdClass;
use tool_admin_presets\local\action\export;

/**
 * Abstract base class for all field types
 */
abstract class base_field implements fieldtype {
    /** @var object Field definition from database */
    protected $fielddata;

    /** @var object Current submission object */
    protected $submission;

    /** @var array Parsed field parameters */
    protected $params;

    /** @var int|null Field ID, if editing an existing field */
    protected $fieldid;

    /** @var \mod_casestudy\local\field_manager Owning field manager (gives access to context + sibling fields). */
    protected $fieldmanager;
    /**
     * Constructor
     *
     * @param int|object $field Field definition
     * @param object $submission Current submission (optional)
     */
    public function __construct($casestudyid = null, $field = null, $submission = null) {

        if (is_object($field)) {
            $fieldid = $field->id;
            $this->fielddata = $field;
        } else {
            $fieldid = $field;
        }

        $this->fieldid = $fieldid;

        $this->fieldmanager = \mod_casestudy\local\field_manager::instance($casestudyid);

        if ($fieldid && empty($this->fielddata)) {
            $this->fielddata = \mod_casestudy\local\field_manager::instance($casestudyid)->get_field($fieldid);
        }

        $this->submission = $submission;
    }

    /**
     * Get field type name
     *
     * @return string Field type identifier
     */
    abstract public function get_type();

    /**
     * Get human-readable field type name
     *
     * @return string Display name for field type
     */
    abstract public function get_type_name();

    /**
     * Render field for form input
     *
     * @param \MoodleQuickForm $mform Form object
     * @param string $elementname Element name
     * @param mixed $value Current value
     * @return void
     */
    abstract public function render_form_element($mform, $elementname, $value = null);

    /**
     * Render field for display.
     *
     * @param mixed $value Field value
     * @return string HTML for display
     */
    abstract public function render_display($value, $submissionid = null);

    /**
     * Render field as raw HTML input for template-based forms.
     *
     * This method returns the complete HTML for the form input element,
     * including label, input, description, and error display.
     * Used when rendering submission forms via custom templates.
     *
     * @param mixed $value Current field value
     * @param array $errors Array of error messages for this field
     * @param int|null $submissionid Submission ID (for file handling)
     * @return string Complete HTML for the form field
     */
    public function render_form_input($value = null, array $errors = [], ?int $submissionid = null): string {
        $fieldid = $this->fielddata->id;
        $fieldname = 'field_' . $fieldid;
        $required = !empty($this->fielddata->required);
        $requiredmark = $required ? '<span class="text-danger">*</span>' : '';

        $errorhtml = '';
        if (!empty($errors)) {
            $errorhtml = '<div class="form-control-feedback invalid-feedback d-block">' .
                         implode('<br>', $errors) . '</div>';
        }

        $descriptionhtml = '';
        if (!empty($this->fielddata->description)) {
            $descriptionhtml = '<small class="form-text text-muted">' .
                             format_text($this->fielddata->description, FORMAT_HTML) . '</small>';
        }

        // Get the input element HTML - to be overridden by subclasses
        $haserrors = !empty($errors);
        $inputhtml = $this->get_input_html($fieldname, $value, $submissionid, $haserrors);

        $errorclass = $haserrors ? ' has-danger' : '';

        return '<div class="form-group fitem' . $errorclass . '" data-fieldid="' . $fieldid . '">' .
               '<label for="' . $fieldname . '" class="col-form-label d-block">' .
               format_string($this->fielddata->name) . ' ' . $requiredmark . '</label>' .
               $inputhtml .
               $descriptionhtml .
               $errorhtml .
               '</div>';
    }

    /**
     * Get the raw input HTML element.
     *
     * Subclasses should override this method to provide their specific input HTML.
     * This returns just the input element(s), not the wrapper/label/description.
     *
     * @param string $fieldname The form field name (e.g., 'field_123')
     * @param mixed $value Current field value
     * @param int|null $submissionid Submission ID
     * @param bool $haserrors Whether field has validation errors
     * @return string HTML for the input element
     */
    public function get_input_html(string $fieldname, $value = null, ?int $submissionid = null, bool $haserrors = false): string {
        // Default implementation for text-like fields
        $escapedvalue = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
        $required = !empty($this->fielddata->required) ? 'required' : '';
        $errorclass = $haserrors ? ' is-invalid' : '';

        return '<input type="text" name="' . $fieldname . '" id="' . $fieldname . '" ' .
               'value="' . $escapedvalue . '" class="form-control' . $errorclass . '" ' . $required . '>';
    }

    /** Export field data for templating
     *
     * @param \renderer_base $output Renderer instance
     * @return array Data for template
     */
    public function export_for_template(\renderer_base $output) {
        return [];
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

        // Check required field - only enforce on final submission, not drafts.
        if (!$isdraft && $this->fielddata->required && $this->is_empty_value($value)) {
            $errors[] = get_string('required');
        }

        return $errors;
    }

    /**
     * Process and clean input value
     *
     * @param mixed $value Raw input value
     * @return mixed Cleaned value
     */
    public function process_input($value, $data): field_data {
        return field_data::create((object) ['content' => $value]);
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
        return empty(trim($value));
    }

    /**
     * Parse field parameters from database
     *
     * @return array Parsed parameters
     */
    protected function update_params() {
    }

    /**
     * Update for submission data for form set_data
     */
    public function update_submission_formdata_beforeset(&$formdata, $contentdata, $fieldname) {
        // Default implementation does nothing.
    }

    /**
     * Get field parameter value
     *
     * @param string $param Parameter name (param1, param2, etc.)
     * @param mixed $default Default value
     * @return mixed Parameter value
     */
    protected function get_param($paramname, $default = null, array $defaults = []) {
        if (!isset($this->fielddata->$paramname) && !array_key_exists($paramname, $defaults)) {
            return $default;
        }

        $paramvalue = isset($this->fielddata->$paramname) ? $this->fielddata->$paramname : $defaults[$paramname];
        return $paramvalue;
    }

    /**
     * Get param value with defaults.
     */
    public function get_param_decode($paramname, $default = null, array $defaults = []) {

        if (!isset($this->fielddata->$paramname) && !array_key_exists($paramname, $defaults)) {
            return $default;
        }

        $paramvalue = isset($this->fielddata->$paramname) ? $this->fielddata->$paramname : $defaults[$paramname];

        return json_decode($paramvalue, true);
    }

    /**
     * Get field configuration for editing
     *
     * @return array Configuration array
     */
    public function get_config() {

        return [
            'required' => $this->fielddata->required,
            'showlistview' => $this->fielddata->showlistview,
            'category' => $this->fielddata->category,
        ];
    }

    /**
     * Get field edit form
     *
     * @param \mod_casestudy\local\field_manager $fieldmanager Field manager
     * @param bool $editing True if editing existing field
     * @return \mod_casestudy\local\forms\field_edit_form Field edit form
     */
    public function get_edit_form($fieldmanager, $editing) {

        return new \mod_casestudy\local\forms\field_edit_form(null, [
            'fieldmanager' => $fieldmanager,
            'fieldclass' => $this,
            'editing' => $editing,
            'fieldtype' => $this->get_type(),
        ]);
    }

    /**
     * Get configuration form elements for this field type
     *
     * @param \MoodleQuickForm $mform Form object
     * @return void
     */
    public function additional_form_elements(&$mform) {
        // Additional form elements can be added.
    }

    /**
     * Process configuration form data
     *
     * @param array $data Form data
     * @return array Processed configuration
     */
    public function process_config_form($data) {

        return [
            'required' => !empty($data['required']),
            'showlistview' => !empty($data['showlistview']),
            'category' => !empty($data['category']),
        ];
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

        // Default: truncate long text
        $display = strip_tags($this->render_display($value));
        if (strlen($display) > 50) {
            $display = substr($display, 0, 47) . '...';
        }

        return $display;
    }

    /**
     * Get searchable text for this field value
     *
     * @param mixed $value Field value
     * @return string Searchable text
     */
    public function get_search_content($value) {
        return strip_tags($this->render_display($value));
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
     * Get category values for completion criteria
     *
     * @return array Array of possible category values
     */
    public function get_category_values() {
        return [];
    }

    /**
     * Export field value for backup/export
     *
     * @param mixed $value Field value
     * @return mixed Exported value
     */
    public function export_value($value) {
        return $value;
    }

    /**
     * Import field value from backup/import
     *
     * @param mixed $value Imported value
     * @return mixed Processed value
     */
    public function import_value($value) {
        return $value;
    }

    /**
     * Set field parameters from configuration
     * Each field type implements its own parameter storage strategy
     *
     * @param object $field Field object (passed by reference)
     * @param array $config Configuration array
     * @return void
     */
    public function set_field_params(&$field, $config) {
        // Clear all params first
        $field->param1 = null;
        $field->param2 = null;
        $field->param3 = null;
        $field->param4 = null;
        $field->param5 = null;
    }

    /**
     * Process form data and convert param[key] format to config array
     *
     * @param array $formdata Raw form data
     * @return array Processed configuration
     */
    public function process_form_params($formdata) {
        $config = [];
        for ($i = 1; $i <= 5; $i++) {
            $paramname = "param{$i}";
            if (isset($formdata[$paramname])) {
                if (is_array($formdata[$paramname])) {
                    $config[$paramname] = $formdata[$paramname];
                } else {
                    $config[$paramname] = $formdata[$paramname];
                }
            }
        }

        return $config;
    }

    /**
     * Get form value for submission
     *
     * @param object $submissiondata Submission data object (passed by reference)
     * @param object $value Field value
     * @param string $fieldname Field name
     */
    public function get_form_value(&$submissiondata, $value, $fieldname) {
        $submissiondata[$fieldname] = $value->content;
    }

    /**
     * Get form element names for this field type's parameters
     * Used to identify which form elements belong to this field
     *
     * @return array Array of form element names
     */
    public function get_param_form_elements() {
        return [];
    }

    /**
     * Add parameter-specific form elements
     *
     * @param \MoodleQuickForm $mform Form object
     * @param string $prefix Element name prefix
     * @return void
     */
    public function add_param_form_elements(&$mform, $prefix = '') {
        // Default implementation - subclasses should override
    }

    /**
     * Set default values for parameter form elements
     *
     * @param array $defaults Defaults array (passed by reference)
     * @param string $prefix Element name prefix
     * @return void
     */
    public function set_param_form_defaults(&$defaults, $prefix = '') {
        // Default implementation - subclasses should override
    }

    /**
     * Validate field configuration data
     *
     * @param array $data Form data
     * @return array Array of field name => error message
     */
    public function validate_config_data($data) {
        // Default implementation - subclasses should override for specific validation
        return [];
    }

    /**
     * Check if field supports required setting
     *
     * @return bool True if supports required
     */
    public function supports_required(): bool {
        return true;
    }

    /**
     * Check if field supports list view display
     *
     * @return bool True if supports list view
     */
    public function supports_listview(): bool {
        return true;
    }

    /**
     * Check if field supports content storage
     *
     * @return bool True if supports content
     */
    public function supports_content(): bool {
        return true;
    }
}
