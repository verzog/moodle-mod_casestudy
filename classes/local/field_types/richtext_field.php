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
 * Rich text field type for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\field_types;

use mod_casestudy\local\field_data;

/**
 * Rich text field implementation using Moodle's HTML editor
 */
class richtext_field extends base_field {
    /**
     * Get field type name
     *
     * @return string Field type identifier
     */
    public function get_type() {
        return 'richtext';
    }

    /**
     * Get human-readable field type name
     *
     * @return string Display name for field type
     */
    public function get_type_name() {
        return get_string('fieldtype_richtext', 'mod_casestudy');
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
        $editoroptions = $this->get_editor_options();

        // Add the editor element
        $mform->addElement(
            'editor',
            $elementname,
            $this->fielddata->name,
            ['rows' => $editoroptions['rows']],
            $editoroptions
        );
        $mform->setType($elementname, PARAM_RAW);

        // Set default value if provided
        if ($value !== null) {
            // Handle both array format (with text and format) and plain text
            if (is_array($value)) {
                $mform->setDefault($elementname, $value);
            } else {
                $mform->setDefault($elementname, ['text' => $value, 'format' => FORMAT_HTML]);
            }
        }
    }

    /**
     * Get the raw input HTML element for template-based forms.
     *
     * Rich text fields require Moodle's editor which needs JavaScript initialization.
     * This method returns a textarea fallback.
     *
     * @param string $fieldname The form field name
     * @param mixed $value Current field value
     * @param int|null $submissionid Submission ID
     * @param bool $haserrors Whether field has validation errors
     * @return string HTML for the input element
     */
    public function get_input_html(string $fieldname, $value = null, ?int $submissionid = null, bool $haserrors = false): string {
        $text = '';
        if (is_array($value)) {
            $text = $value['text'] ?? '';
        } else {
            $text = $value ?? '';
        }

        $escapedvalue = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $editoroptions = $this->get_editor_options();
        $rows = $editoroptions['rows'] ?? 10;
        $errorclass = $haserrors ? ' is-invalid' : '';

        // Return a rich text area with data attributes for JavaScript enhancement
        return '<div class="casestudy-richtext-container" data-fieldname="' . $fieldname . '">' .
               '<textarea name="' . $fieldname . '[text]" id="' . $fieldname . '" ' .
               'class="form-control casestudy-richtext-editor' . $errorclass . '" rows="' . $rows . '">' .
               $escapedvalue . '</textarea>' .
               '<input type="hidden" name="' . $fieldname . '[format]" value="' . FORMAT_HTML . '">' .
               '</div>';
    }

    /**
     * Check if this field type supports template-based form rendering.
     *
     * @return bool True if supports template forms
     */
    public function supports_template_form(): bool {
        return false; // Richtext fields require Moodle's editor with JS
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

        // Handle both array format and plain text
        $text = '';
        $format = FORMAT_HTML;

        if (is_array($value)) {
            $text = $value['text'] ?? '';
            $format = $value['format'] ?? FORMAT_HTML;
        } else {
            $text = $value;
        }

        if ($submissionid) {
            $text = file_rewrite_pluginfile_urls(
                $text,
                'pluginfile.php',
                $this->fieldmanager->get_context()->id,
                'mod_casestudy',
                'submission_richtext',
                $submissionid
            );
        }

        $label = \html_writer::label(
            format_string($this->fielddata->name),
            'field-' . $this->fielddata->id,
            '',
            ['class' => 'casestudy-field-label font-weight-bold field-label']
        );
        $formatted = format_text($text, $format, ['context' => $this->fieldmanager->get_context()]);
        $formatted = $this->embed_image_file_links($formatted);
        $content = \html_writer::div(
            $formatted,
            'casestudy-field-richtext field-' . $this->fielddata->id
        );

        return $content;
    }

    /**
     * Turn links that point straight at an image file into inline images.
     *
     * Images inserted through the editor as file links (for example the Bootstrap
     * card/snippet markup the editor produces) otherwise render as just the
     * filename link and never display the picture. Converting those anchors to
     * inline <img> tags makes the image show, and the existing field_file lightbox
     * AMD then makes it clickable to enlarge. Anchors that already wrap an <img>
     * (the thumbnail → full-size pattern) and SVG targets (which can carry script)
     * are left untouched.
     *
     * @param string $html Formatted field HTML.
     * @return string HTML with standalone image-file links replaced by images.
     */
    protected function embed_image_file_links(string $html): string {
        $pattern = '~<a\b[^>]*?\bhref="([^"]+\.(?:png|jpe?g|gif|webp|bmp|avif)(?:\?[^"]*)?)"[^>]*>(.*?)</a>~is';

        return preg_replace_callback($pattern, function ($matches) {
            // Leave anchors that already contain an image (thumbnail markup) alone.
            if (stripos($matches[2], '<img') !== false) {
                return $matches[0];
            }
            $alt = trim(html_entity_decode(strip_tags($matches[2]), ENT_QUOTES, 'UTF-8'));

            return \html_writer::img($matches[1], $alt, ['class' => 'img-fluid casestudy-richtext-image']);
        }, $html);
    }

    /**
     * Process and clean input value
     *
     * @param mixed $value Raw input value
     * @return mixed Cleaned value
     */
    public function process_input($value, $data): field_data {

        $fielddata = field_data::create((object) ['content' => $value]);

        if (is_array($value) && isset($value['text'])) {
            $fielddata->set_content($value['text']);
            $fielddata->set_content_format($value['format'] ?? FORMAT_HTML);
        }

        return $fielddata;
    }

    /**
     * Check if value is considered empty
     *
     * @param mixed $value Value to check
     * @return bool True if empty
     */
    protected function is_empty_value($value) {
        if (is_array($value)) {
            $text = $value['text'] ?? '';
        } else {
            // Try to decode JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text'])) {
                $text = $decoded['text'];
            } else {
                $text = $value;
            }
        }

        return empty(trim(strip_tags($text)));
    }

    /**
     * Update for submission data for form set_data
     */
    public function update_submission_formdata_beforeset(&$formdata, $contentdata, $fieldname) {
        if (!empty($contentdata->content)) {
            $formdata[$fieldname . '[text]'] = $contentdata->content;
            $formdata[$fieldname . '[format]'] = $contentdata->contentformat;
        }
    }

    /**
     * Override get_form_value to properly format editor data
     *
     * @param array $submissiondata Form data array (passed by reference)
     * @param object $value Content data object
     * @param string $fieldname Field name
     */
    public function get_form_value(&$submissiondata, $value, $fieldname) {
        if (!empty($value->content)) {
            // Prepare editor with draft files
            $editoroptions = $this->get_editor_options();
            $draftideditor = file_get_submitted_draft_itemid($fieldname);

            // Prepare text with draft files
            $text = file_prepare_draft_area(
                $draftideditor,
                $this->fieldmanager->get_context()->id,
                'mod_casestudy',
                'submission_richtext',
                $value->submissionid ?? 0,
                $editoroptions,
                $value->content
            );

            $submissiondata[$fieldname] = [
                'text' => $text,
                'format' => $value->contentformat ?? FORMAT_HTML,
                'itemid' => $draftideditor,
            ];
        }
    }

    /**
     * Get editor options for this field
     *
     * @return array Editor options
     */
    private function get_editor_options() {
        $config = $this->get_param('param1', []);

        $options = [
            'subdirs' => false,
            'maxbytes' => $config['maxbytes'] ?? 0,
            'maxfiles' => $config['maxfiles'] ?? -1,
            'changeformat' => true,
            'context' => $this->fieldmanager->get_context(),
            'noclean' => false,
            'trusttext' => false,
            'rows' => $config['rows'] ?? 10,
        ];

        return $options;
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

        // Handle param1 for editor options
        if (isset($config['param1']) && is_array($config['param1'])) {
            $options = [
                'rows' => (int) ($config['param1']['rows'] ?? 10),
                'maxbytes' => (int) ($config['param1']['maxbytes'] ?? 0),
                'maxfiles' => (int) ($config['param1']['maxfiles'] ?? -1),
            ];

            $field->param1 = json_encode($options);
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
        // Editor rows
        $mform->addElement('text', 'param1[rows]', get_string('editorrows', 'mod_casestudy'), ['size' => 5]);
        $mform->setType('param1[rows]', PARAM_INT);
        $mform->setDefault('param1[rows]', 10);
        $mform->addHelpButton('param1[rows]', 'editorrows', 'mod_casestudy');

        // Max bytes for file uploads in editor
        $mform->addElement('text', 'param1[maxbytes]', get_string('editormaxbytes', 'mod_casestudy'), ['size' => 10]);
        $mform->setType('param1[maxbytes]', PARAM_INT);
        $mform->setDefault('param1[maxbytes]', 0);
        $mform->addHelpButton('param1[maxbytes]', 'editormaxbytes', 'mod_casestudy');

        // Max files for editor
        $mform->addElement('text', 'param1[maxfiles]', get_string('editormaxfiles', 'mod_casestudy'), ['size' => 5]);
        $mform->setType('param1[maxfiles]', PARAM_INT);
        $mform->setDefault('param1[maxfiles]', -1);
        $mform->addHelpButton('param1[maxfiles]', 'editormaxfiles', 'mod_casestudy');
    }

    /**
     * Get form element names for this field type's parameters
     *
     * @return array Array of form element names
     */
    public function get_param_form_elements() {
        return [
            'param1[rows]',
            'param1[maxbytes]',
            'param1[maxfiles]',
        ];
    }

    /**
     * Get parameter value with JSON decoding
     *
     * @param string $paramname Parameter name
     * @param mixed $default Default value
     * @param array $defaults Default values array
     * @return mixed Parameter value
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

        if (isset($options['rows'])) {
            $defaults[$prefix . 'param1[rows]'] = $options['rows'];
        }
        if (isset($options['maxbytes'])) {
            $defaults[$prefix . 'param1[maxbytes]'] = $options['maxbytes'];
        }
        if (isset($options['maxfiles'])) {
            $defaults[$prefix . 'param1[maxfiles]'] = $options['maxfiles'];
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

        // Process editor options
        if (isset($data['param1'])) {
            $config['param1'] = $data['param1'];
        }

        return $config;
    }

    /**
     * Validate field configuration data
     *
     * @param array $data Form data
     * @return array Array of field name => error message
     */
    public function validate_config_data($data) {
        $errors = [];

        if (isset($data['param1']['rows']) && $data['param1']['rows'] < 1) {
            $errors['param1[rows]'] = get_string('error_invalid_rows', 'mod_casestudy');
        }

        if (isset($data['param1']['maxbytes']) && $data['param1']['maxbytes'] < 0) {
            $errors['param1[maxbytes]'] = get_string('error_invalid_maxbytes', 'mod_casestudy');
        }

        if (isset($data['param1']['maxfiles']) && $data['param1']['maxfiles'] < -1) {
            $errors['param1[maxfiles]'] = get_string('error_invalid_maxfiles', 'mod_casestudy');
        }

        return $errors;
    }

    /**
     * Save editor files from draft area to permanent storage
     *
     * @param mixed $value Editor value (array with 'text', 'format', 'itemid')
     * @param int $submissionid Submission ID
     * @param string $fieldname Field name
     * @param object $context Context
     * @return string Updated text
     */
    public function save_area_files($value, $submissionid, $fieldname, $context) {
        if (!is_array($value) || !isset($value['itemid'])) {
            return null;
        }

        $editoroptions = $this->get_editor_options();

        // Save files from draft area to permanent storage
        $text = file_save_draft_area_files(
            $value['itemid'], // Draft item ID from editor
            $context->id, // Context ID
            'mod_casestudy', // Component
            'submission_richtext', // File area
            $submissionid, // Final item ID (submission ID)
            $editoroptions, // Editor options
            $value['text']                        // Text content
        );

        // file_save_draft_area_files only tokenises draft URLs. Absolute pluginfile URLs that
        // already point at this submission's own rich-text area (e.g. an image inserted as a
        // file link) stay absolute and would 404 after a course backup/restore. Replace them
        // with the @@PLUGINFILE@@ placeholder so the stored content is portable.
        if (is_string($text) && $text !== '') {
            $prefix = '/pluginfile\.php(?:\?file=)?/' . (int) $context->id
                . '/mod_casestudy/submission_richtext/' . (int) $submissionid . '/';
            $text = preg_replace('~https?://[^"\'\s<>]+?' . $prefix . '~i', '@@PLUGINFILE@@/', $text);
        }

        return $text;
    }

    /**
     * Get field display value for list views
     *
     * @param mixed $value Field value
     * @param object $row Row data
     * @return string Display text for lists
     */
    public function get_list_display($value, $row) {
        if ($this->is_empty_value($value)) {
            return '-';
        }

        // Extract text content for list display
        $text = '';
        if (is_array($value)) {
            $text = $value['text'] ?? '';
        } else {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text'])) {
                $text = $decoded['text'];
            } else {
                $text = $value;
            }
        }

        // Strip HTML and truncate for list view
        $display = strip_tags($text);
        if (strlen($display) > 50) {
            $display = substr($display, 0, 47) . '...';
        }

        return $display;
    }
}
