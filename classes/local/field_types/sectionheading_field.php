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
 * Section heading field type for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  2025 SCCA
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\local\field_types;

use mod_casestudy\local\field_data;

defined('MOODLE_INTERNAL') || die();

/**
 * Section heading field implementation
 * This field type displays as a heading/section divider and doesn't collect input
 */
class sectionheading_field extends base_field {

    /**
     * Get field type name
     *
     * @return string Field type identifier
     */
    public function get_type() {
        return 'sectionheading';
    }

    /**
     * Get human-readable field type name
     *
     * @return string Display name for field type
     */
    public function get_type_name() {
        return get_string('fieldtype_sectionheading', 'mod_casestudy');
    }

    /**
     * Render field for form input
     * Section headings don't have input elements, just display as heading
     *
     * @param \MoodleQuickForm $mform Form object
     * @param string $elementname Element name
     * @param mixed $value Current value
     * @return void
     */
    public function render_form_element($mform, $elementname, $value = null) {
        // Section headings display as headers in forms, not input elements
        $headingtext = format_string($this->fielddata->name);

        $mform->addElement('header', $elementname . '_heading', $headingtext);

        // Add description if provided
        if (!empty($this->fielddata->description)) {

            $mform->addElement('html',
                \html_writer::tag('p',  format_text($this->fielddata->description, FORMAT_HTML),
                ['class' => 'casestudy-section-heading-description text-muted small mt-1'])
            );
        }
    }

    /**
     * Get the raw input HTML element for template-based forms.
     *
     * Section headings don't have input elements, they display as headings.
     *
     * @param string $fieldname The form field name
     * @param mixed $value Current field value (not used)
     * @param int|null $submissionid Submission ID
     * @param bool $haserrors Whether field has validation errors (not used)
     * @return string HTML for the heading
     */
    public function get_input_html(string $fieldname, $value = null, ?int $submissionid = null, bool $haserrors = false): string {
        // Section headings just display as headings, no input element
        return '';
    }

    /**
     * Override render_form_input to display heading without form group wrapper.
     *
     * @param mixed $value Current field value (not used)
     * @param array $errors Array of error messages (not used)
     * @param int|null $submissionid Submission ID
     * @return string Complete HTML for the section heading
     */
    public function render_form_input($value = null, array $errors = [], ?int $submissionid = null): string {
        $headingtext = format_string($this->fielddata->name);

        $html = '<div class="casestudy-section-heading-wrapper mt-4 mb-3">';
        $html .= '<h4 class="casestudy-section-heading">' . $headingtext . '</h4>';

        if (!empty($this->fielddata->description)) {
            $html .= '<p class="casestudy-section-description text-muted small">' .
                     format_text($this->fielddata->description, FORMAT_HTML) . '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render field for display (read-only)
     *
     * @param mixed $value Field value
     * @param int $submissionid Submission ID (optional)
     * @return string HTML for display
     */
    public function render_display($value, $submissionid = null) {
        $headingtext = format_string($this->fielddata->name);

        $html = \html_writer::tag('h4', $headingtext, [
            'class' => 'casestudy-section-heading field-section-heading mt-4 mb-3'
        ]);

        // Add description if provided
        if (!empty($this->fielddata->description)) {
            $html .= \html_writer::div(
                format_text($this->fielddata->description, FORMAT_HTML),
                'casestudy-section-description text-muted mb-3'
            );
        }

        return $html;
    }

    /**
     * Validate field input
     * Section headings don't require validation as they don't collect input
     *
     * @param mixed $value Input value
     * @param array|null $files Form files
     * @param array|null $data Form data
     * @param bool $isdraft True if saving as draft (skip required field validation)
     * @return array Empty array as no validation needed
     */
    public function validate_input($value, $files = null, $data = null, $isdraft = false) {
        // Section headings don't have input to validate.
        return [];
    }

    /**
     * Check if value is considered empty
     * Section headings are always "empty" as they don't store values
     *
     * @param mixed $value Value to check
     * @return bool Always true for section headings
     */
    protected function is_empty_value($value) {
        return true; // Section headings don't have values
    }

    /**
     * Get field configuration for editing
     *
     * @return array Configuration array
     */
    public function get_config() {
        $config = parent::get_config();

        // Section headings can't be required or used in list views
        $config['required'] = false;
        $config['showlistview'] = false;

        return $config;
    }

    /**
     * Process configuration form data
     *
     * @param array $data Form data
     * @return array Processed configuration
     */
    public function process_config_form($data) {
        $config = parent::process_config_form($data);

        // Override settings that don't apply to section headings
        $config['required'] = false; // Section headings can't be required
        $config['showlistview'] = false; // Don't show in list views

        return $config;
    }

    /**
     * Check if this field type supports categories
     *
     * @return bool False as section headings don't support categories
     */
    public function supports_categories() {
        return false;
    }

    /**
     * Get searchable text for this field value
     *
     * @param mixed $value Field value
     * @return string The field name for search purposes
     */
    public function get_search_content($value) {
        return $this->fielddata->name; // Make the heading searchable
    }

    /**
     * Get field display value for list views
     *
     * @param mixed $value Field value
     * @return string Display text for lists
     */
    public function get_list_display($value, $row) {
        return '-'; // Section headings don't display in lists
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

    /**
     * Additional form elements for field configuration
     * Override base to disable required and showlistview options
     *
     * @param \MoodleQuickForm $mform Form object
     * @return void
     */
    public function additional_form_elements(&$mform) {
        // Add note about section headings
        $mform->addElement('static', 'sectionheading_note', '',
            \html_writer::div(
                get_string('sectionheading_note', 'mod_casestudy'),
                'alert alert-info'
            )
        );
    }

    /**
     * Check if field supports being required
     *
     * @return bool False as section headings can't be required
     */
    public function supports_required(): bool {
        return false;
    }

    /**
     * Check if field supports being shown in list views
     *
     * @return bool False as section headings can't be shown in lists
     */
    public function supports_listview(): bool {
        return false;
    }

    /**
     * Check if field supports content storage
     *
     * @return bool False as section headings don't store content
     */
    public function supports_content() :bool {
        return false;
    }
}
