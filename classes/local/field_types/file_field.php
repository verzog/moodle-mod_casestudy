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
 * File field type for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\local\field_types;

use core\output\html_writer;
use mod_casestudy\local\field_data;

defined('MOODLE_INTERNAL') || die();

/**
 * File field implementation - File parameters stored in param1, param2, param3
 */
class file_field extends base_field {
    /**
     * Get field type name
     *
     * @return string Field type identifier
     */
    public function get_type() {
        return 'file';
    }

    /**
     * Get human-readable field type name
     *
     * @return string Display name for field type
     */
    public function get_type_name() {
        return get_string('fieldtype_file', 'mod_casestudy');
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
        $fileconfig = $this->get_file_config();

        $options = [
            'subdirs' => 0,
            'multiple' => $fileconfig['maxfiles'] != 1,
            'maxfiles' => $fileconfig['maxfiles'],
            'maxbytes' => $fileconfig['maxbytes'],
            'accepted_types' => $fileconfig['acceptedtypes'],
        ];

        $mform->addElement('filemanager', $elementname, $this->fielddata->name, null, $options);

        // Set default value
        if ($value !== null) {
            $mform->setDefault($elementname, $value);
        }
    }

    /**
     * Get the raw input HTML element for template-based forms.
     *
     * File fields require Moodle's filemanager which needs JavaScript initialization.
     * This method returns a placeholder that will be replaced by the actual filemanager.
     *
     * @param string $fieldname The form field name
     * @param mixed $value Current field value (draft item ID)
     * @param int|null $submissionid Submission ID
     * @param bool $haserrors Whether field has validation errors
     * @return string HTML for the input element
     */
    public function get_input_html(string $fieldname, $value = null, ?int $submissionid = null, bool $haserrors = false): string {
        // File fields require special handling - they need filemanager which requires
        // Moodle form initialization. Return a placeholder div that will be populated
        // by JavaScript or indicate this field should use standard form rendering.
        $fileconfig = $this->get_file_config();

        return '<div class="casestudy-filemanager-container" ' .
               'data-fieldname="' . $fieldname . '" ' .
               'data-maxfiles="' . $fileconfig['maxfiles'] . '" ' .
               'data-maxbytes="' . $fileconfig['maxbytes'] . '" ' .
               'data-itemid="' . ($value ?? 0) . '">' .
               '<input type="hidden" name="' . $fieldname . '" value="' . ($value ?? 0) . '">' .
               '<div class="alert alert-info">' .
               get_string('fileuploadrequiresform', 'mod_casestudy') . '</div>' .
               '</div>';
    }

    /**
     * Check if this field type supports template-based form rendering.
     *
     * @return bool True if supports template forms
     */
    public function supports_template_form(): bool {
        return false; // File fields require Moodle's filemanager with JS
    }

    /**
     * Render field for display
     *
     * @param mixed $value Field value
     * @return string HTML for display
     */
    public function render_display($value, $submissionid = null) {
        global $PAGE;

        static $jsincluded = false;

        if ($this->is_empty_value($value)) {
            return \html_writer::span('-', 'text-muted');
        }

        $label = \html_writer::label(format_string($this->fielddata->name), 'field-' . $this->fielddata->id, '', [
            'class' => 'casestudy-field-label font-weight-bold field-label']);

        $valuespan = \html_writer::start_div('casestudy-files-wrapper d-flex flex-direction-row');

        $includeinstruction = false;
        $areafiles = $this->get_areafiles('field_'.$this->fieldid, $submissionid, 'mod_casestudy', $this->fieldmanager->get_context(), true);
        foreach ($areafiles as $file) {

            if ($file['image']) {

                $image = \html_writer::img($file['url'], basename($file['url']), ['class' => 'responsive-img']);

                $background = \html_writer::span('<i class="icon fa fa-magnifying-glass-plus fa-fw "></i>',
                    'casestudy-file-enlarge-image', ['data-toggle' => 'casestudy-file-modal']);

                $valuespan .= \html_writer::tag('div', $background . $image, ['class' => 'casestudy-field-file-image mb-2 mr-2',
                    'data-modal' => 'lightbox', 'data-modal-content' => $image, 'data-modal-title' => format_string($this->fielddata->name)
                ]);
                $includeinstruction = true;

            } else {
                $valuespan .= \html_writer::link($file['url'], urldecode(basename($file['url'])), ['target' => '_blank']) . '<br>';
            }
        }
        $valuespan .= \html_writer::end_div();
        $valuespan .= $includeinstruction ? html_writer::span(get_string('clicktoopen', 'mod_casestudy'), 'text-muted') : '';

        // JS included for modal lightbox.
        if (!$jsincluded) {
            $jsincluded = true;
            $PAGE->requires->js_call_amd('mod_casestudy/field_file', 'init');
        }

        return $valuespan;
    }

    /**
     * Process and clean input value
     *
     * @param mixed $value Raw input value
     * @return mixed Cleaned value
     */
    public function process_input($value, $data) : field_data {
        // File processing would typically involve handling the filemanager data
        // and storing files in the appropriate file area
        return field_data::create((object) ['content' => $value]);
    }

    /**
     * Validate field input
     *
     * @param mixed $value Input value (draft item ID for file fields)
     * @param array|null $files Form files
     * @param array|null $data Form data
     * @param bool $isdraft True if saving as draft (skip required field validation)
     * @return array Array of error messages
     */
    public function validate_input($value, $files = null, $data = null, $isdraft = false) {
        global $USER;

        // Don't call parent - file fields handle validation differently.
        // The $value is a draft item ID, not file content.
        $errors = [];

        $fs = get_file_storage();
        $fileconfig = $this->get_file_config();
        $context = \context_user::instance($USER->id);
        $elementname = 'field_' . $this->fielddata->id;

        $draftitemid = isset($data[$elementname]) ? $data[$elementname] : 0;
        $uploadedfiles = $fs->get_area_files($context->id, 'user', 'draft', $draftitemid, '', false);
        $filecount = count($uploadedfiles);

        // Only validate on final submission, not drafts.
        if (!$isdraft) {
            // Check required field - must have at least one file.
            if ($this->fielddata->required && $filecount < 1) {
                $errors[] = get_string('required');
            }

            // Check minimum files setting (if configured and greater than required).
            if ($fileconfig['minfiles'] >= 1 && $filecount < $fileconfig['minfiles']) {
                $errors[] = get_string('requiredminfiles', 'mod_casestudy', $fileconfig['minfiles']);
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

        // Handle param1 for file count
        if (isset($config['param1']) && is_array($config['param1'])) {
            $field->param1 = json_encode([
                'min' => (int)($config['param1']['min'] ?? 0),
                'max' => (int)($config['param1']['max'] ?? 1)
            ]);
        }

        // Handle param2 for max file size
        if (isset($config['param2'])) {
            $field->param2 = (int)$config['param2'];
        }

        // Handle param3 for accepted file types
        if (isset($config['param3']) && is_array($config['param3'])) {
            $field->param3 = json_encode(array_values($config['param3']));
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
        // File count configuration
        $countgroup = [];
        $mform->addElement('text', 'param1[min]', get_string('minfiles', 'mod_casestudy'), ['size' => 3]);
        $mform->addElement('text', 'param1[max]', get_string('maxfiles', 'mod_casestudy'), ['size' => 3]);
        $mform->setType('param1[min]', PARAM_INT);
        $mform->setType('param1[max]', PARAM_INT);

        // Max file size
        $sizeoptions = [
            1048576 => '1 MB',
            5242880 => '5 MB',
            10485760 => '10 MB',
            52428800 => '50 MB',
            104857600 => '100 MB'
        ];
        $mform->addElement('select', 'param2', get_string('maxfilesize', 'mod_casestudy'), $sizeoptions);
        $mform->addHelpButton('param2', 'maxfilesize', 'mod_casestudy');

        $name = get_string('accepted_filetypes', 'mod_casestudy');
        $mform->addElement('filetypes', 'param3', $name);
    }

    /**
     * Get form element names for this field type's parameters
     *
     * @return array Array of form element names
     */
    public function get_param_form_elements() {
        return ['param1[min]', 'param1[max]', 'param2', 'param3'];
    }

    /**
     * Set default values for parameter form elements
     *
     * @param array $defaults Defaults array (passed by reference)
     * @param string $prefix Element name prefix
     * @return void
     */
    public function set_param_form_defaults(&$defaults, $prefix = '') {

        $fileconfig = $this->get_file_config($defaults);

        $param1 = isset($defaults['param1']) ? json_decode($defaults['param1'], true) : [];

        $defaults[$prefix . 'param1[min]'] = $fileconfig['minfiles'];
        $defaults[$prefix . 'param1[max]'] = $fileconfig['maxfiles'];
        $defaults[$prefix . 'param2'] = $fileconfig['maxbytes'];

        if (!empty($fileconfig['acceptedtypes']) && is_array($fileconfig['acceptedtypes'])) {
            $defaults[$prefix . 'param3'] = implode("\n", $fileconfig['acceptedtypes']);
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

        // Process file count
        if (isset($data['param1'])) {
            $config['param1'] = $data['param1'];
        }

        // Process max file size
        if (isset($data['param2'])) {
            $config['param2'] = $data['param2'];
        }

        // Process accepted file types
        if (isset($data['param3'])) {
            $types = array_filter(array_map('trim', explode("\n", $data['param3'])));
            $config['param3'] = $types;
        }

        return $config;
    }

    /**
     * Check if this field type supports categories
     *
     * @return bool True if supports categories
     */
    public function supports_categories() {
        return false; // Files don't work well as categories
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

        // For list view, show the attachment icon.
        $files = $this->get_areafiles('field_' . $row->fieldid, $row->id, 'mod_casestudy', $this->fieldmanager->get_context(), true);
        if (empty($files)) {
            return '-';
        }

        $images = [];
        $links = [];
        foreach ($files as $file) {
            if ($file['image']) {
                $images[] = \html_writer::img($file['url'], basename($file['url']), ['class' => 'responsive-img', 'style' => 'height:30px;']);
            } else {
                $links[] = \html_writer::link($file['url'], urldecode(basename($file['url'])), ['target' => '_blank']);
            }
        }

        $output = '';
        if (!empty($images)) {
            $output .= implode(' ', $images) . '<br>';
        }
        if (!empty($links)) {
            $output .= implode(', ', $links);
        }

        return $output;
    }

    /**
     * Get file configuration from parameters
     *
     * @return array File configuration
     */
    private function get_file_config($defaults = []) {

        $filecount = $this->get_param_decode('param1', [], $defaults);
        $maxbytes = $defaults['param2'] ?? 10485760; // Default to 10 MB.
        $acceptedtypes = $this->get_param_decode('param3', ['*'], $defaults);

        if (!empty($acceptedtypes)) {
            $acceptedtypes = array_map(fn($type) => explode(",", $type), $acceptedtypes);
        }
        $acceptedtypes = $this->update_file_typesets($acceptedtypes[0]);

        return [
            'minfiles' => isset($filecount['min']) ? (int)$filecount['min'] : 0,
            'maxfiles' => isset($filecount['max']) ? (int)$filecount['max'] : 1,
            'maxbytes' => (int)$maxbytes,
            'acceptedtypes' => is_array($acceptedtypes) ? $acceptedtypes : ['*']
        ];
    }

    /**
     * Get the type sets configured for this assignment.
     *
     * @return array('groupname', 'mime/type', ...)
     */
    private function update_file_typesets($filetypes) {

        $util = new \core_form\filetypes_util();
        $sets = $util->normalize_file_types($filetypes);

        return $sets;
    }

    /**
     * Fetch the files from for the filearea.
     *
     * @param string $filearea Name of the filearea.
     * @param int $itemid Id for the filearea.
     * @param string $component Plugin component name.
     * @param context_module $context Course module instance object.
     * @param bool $multiple If true then returns array of file urls, else returns single file url.
     * @return string|array File Path of the given fileareas, If not false.
     */
    public function get_areafiles($filearea, $itemid=0, $component='mod_casestudy', $context=null, $multiple=false) {
        $files = get_file_storage()->get_area_files(
            $context->id, $component, $filearea, $itemid, 'itemid, filepath, filename', false);

        if (empty($files) ) {
            return $multiple ? [] : '';
        }

        $fileurls = [];
        foreach ($files as $file) {
            $fileurl = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                false
            );
            $fileurls[] = ['image' => $file->is_valid_image(), 'url' => $fileurl->out(false)];
        }
        return $fileurls;
    }

    /**
     * Save files from draft area to the field's file area
     *
     * @param int $draftitemid Draft item ID
     * @param int $itemid Item ID for the file area
     * @param string $filearea File area name
     * @param context_module $context Context instance
     * @return void
     */
    public function save_area_files($draftitemid, $itemid=0, $filearea='submission_files', $context=null) {
        $fileconfig = $this->get_file_config();

        $options = [
            'subdirs' => 0,
            'maxfiles' => $fileconfig['maxfiles'],
            'maxbytes' => $fileconfig['maxbytes'],
            'accepted_types' => $fileconfig['acceptedtypes'],
        ];

        // Save the files from draft area to the filearea
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'mod_casestudy',
            $filearea,
            $itemid,
            $options
        );
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
}
