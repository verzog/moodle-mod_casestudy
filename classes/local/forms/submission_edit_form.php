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
 * Submission edit form for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for editing Case Study submissions
 */
class submission_edit_form extends \moodleform {
    /** @var array Array of field objects */
    private $fields;

    /** @var \mod_casestudy\field_manager Field manager instance */
    private $fieldmanager;

    /** @var bool Whether we're editing an existing submission */
    private $editing;

    /** @var \cm_info|\stdClass Course module record. */
    private $cm;

    /** @var bool True when the current submission is a resubmission (has a parentid). */
    private $isresubmission;

    /** @var string Cached output of {@see parse_from_template()}; populated by render(). */
    protected $parsedformoutput;

    /**
     * Constructor
     *
     * @param string $action Form action URL
     * @param array $customdata Custom data including fields, field_manager, editing flag
     */
    public function __construct($action, $customdata) {
        $this->fields = $customdata['fields'];
        $this->fieldmanager = $customdata['fieldmanager'];
        $this->editing = $customdata['editing'];
        $this->cm = $customdata['cmdata'];
        $this->isresubmission = !empty($customdata['isresubmission']);

        parent::__construct($action, $customdata);
    }

    /**
     * Expose the underlying MoodleQuickForm so the template renderer can decorate it.
     *
     * @return \MoodleQuickForm
     */
    public function get_form() {
        return $this->_form;
    }

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        // Hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'submissionid');
        $mform->setType('submissionid', PARAM_INT);

        $mform->addElement('hidden', 'sesskey', sesskey());
        $mform->setType('sesskey', PARAM_ALPHANUM);

        // Instructions for students
        if (!$this->editing) {
            $mform->addElement(
                'static',
                'instructions',
                '',
                get_string('submission_instructions', 'mod_casestudy')
            );
        }

        // Add dynamic fields based on configured fields
        foreach ($this->fields as $field) {
            $fieldclass = $this->fieldmanager->get_field_type_class($field->type, $field, null);

            if ($fieldclass) {
                // Render the actual form element
                $fieldclass->render_form_element($mform, 'field_' . $field->id);
                 // Add field description as static element if it exists
                if (!empty($field->description) && $field->type !== 'sectionheading') {
                    $mform->addElement(
                        'static',
                        'desc_' . $field->id,
                        '',
                        '<div class="field-description">' . format_text($field->description) . '</div>'
                    );
                }
            }
        }

        // Acceptance checkbox if required.
        if (!empty($this->cm->requireacceptance)) {
            $mform->addElement('checkbox', 'acceptance', get_string('acceptance', 'mod_casestudy'));
            $mform->setType('acceptance', PARAM_INT);
        }

        // Draft save button (always visible).
        $buttonarray = [];
        $mform->_attributes['id'] = 'frm-finishsubmission';
        $mform->addElement('hidden', 'finish', 0);
        $mform->setType('finish', PARAM_INT);
        $mform->setDefault('finish', 0);
        if (optional_param('mode', '', PARAM_ALPHA) === 'preview') {
            $buttonarray[] = $mform->createElement('cancel');
        } else {
            $savedraftbtn = $mform->createElement('submit', 'savedraft', get_string('savedraft', 'mod_casestudy'));
            $buttonarray[] = $savedraftbtn;

            // "Save and add another" only makes sense on a first attempt; on a
            // resubmission the student is editing the existing attempt, so hide it.
            if (!$this->isresubmission) {
                $saveaddanotherbtn = $mform->createElement('submit', 'saveaddanother', get_string('saveandadd', 'mod_casestudy'));
                $buttonarray[] = $saveaddanotherbtn;
            }

            $finishbtn = $mform->createElement(
                'button',
                'finishbtn',
                get_string('finishandsubmit', 'mod_casestudy'),
                ['type' => 'button', 'class' => 'btn-primary btn-finishsubmission']
            );
            $buttonarray[] = $finishbtn;
            $buttonarray[] = $mform->createElement('cancel');
        }

        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Form validation
     *
     * @param array $data Form data
     * @param array $files Form files
     * @return array Validation errors
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        // Check if this is a draft save - required field validation is skipped for drafts.
        $isdraft = $this->is_draft_submission($data) || $this->is_save_and_add_another($data);

        // Validate each field using its field type class
        foreach ($this->fields as $field) {
            $fieldclass = $this->fieldmanager->get_field_type_class($field->type, $field);
            if ($fieldclass) {
                $fieldname = 'field_' . $field->id;
                $value = isset($data[$fieldname]) ? $data[$fieldname] : '';

                $fielderrors = $fieldclass->validate_input($value, $files, $data, $isdraft);
                if (!empty($fielderrors)) {
                    // For group elements (checkbox, radio), assign error to the group name.
                    $errorkey = in_array($field->type, ['checkbox', 'radio']) ? $fieldname . '_group' : $fieldname;
                    $errors[$errorkey] = implode('<br>', $fielderrors);
                }
            }
        }

        if (!empty($this->cm->requireacceptance) && empty($data['acceptance']) && $this->is_finish_submission($data)) {
            $errors['acceptance'] = get_string('required', 'core');
        }

        return $errors;
    }

    /**
     * Get submission data for saving
     *
     * @param object $data Form data
     * @return array Array of field_id => value pairs
     */
    public function get_submission_data($data) {
        $submissiondata = [];

        foreach ($this->fields as $field) {
            $fieldname = 'field_' . $field->id;
            $value = isset($data->$fieldname) ? $data->$fieldname : '';

            // Process the value through the field type
            $fieldclass = $this->fieldmanager->get_field_type_class($field->type, $field);
            if ($fieldclass) {
                $value = $fieldclass->process_input($value, $data);
            }

            $submissiondata[$field->id] = $value;
        }

        return $submissiondata;
    }

    /**
     * Save area files for file fields and richtext fields.
     *
     * @param object $data Form data
     * @param int $submissionid Submission ID
     *
     * @return array Updated field content
     */
    public function save_area_files($data, $submissionid) {
        global $USER, $DB;

        $updatedcontent = [];

        foreach ($this->fields as $field) {
            $fieldname = 'field_' . $field->id;
            if (isset($data->$fieldname)) {
                $fieldclass = $this->fieldmanager->get_field_type_class($field->type, $field);
                if ($fieldclass && method_exists($fieldclass, 'save_area_files')) {
                    $cleanedtext = $fieldclass->save_area_files($data->$fieldname, $submissionid, $fieldname, $this->fieldmanager->get_context());

                    // For richtext fields, update the content with cleaned text.
                    if ($field->type === 'richtext' && $cleanedtext !== null) {
                        $updatedcontent[$field->id] = $cleanedtext;

                        // Update the content in the database
                        $contentrecord = $DB->get_record('casestudy_content', [
                            'submissionid' => $submissionid,
                            'fieldid' => $field->id,
                        ]);

                        if ($contentrecord) {
                            $contentrecord->content = $cleanedtext;
                            $DB->update_record('casestudy_content', $contentrecord);
                        }
                    }
                }
            }
        }

        return $updatedcontent;
    }

    /**
     * Update form data before setting it
     *
     * @param array $submissiondata Submission data
     */
    public function update_formdata_beforeset(&$submissiondata) {
        $mform = $this->_form;

        $formdata = [];
        foreach ($this->fields as $field) {
            $fieldname = 'field_' . $field->id;
            if (array_key_exists($field->id, $submissiondata)) {
                $value = $submissiondata[$field->id];
                $fieldclass = $this->fieldmanager->get_field_type_class($field->type, $field, null);
                if ($fieldclass) {
                    $fieldclass->get_form_value($submissiondata, $value, $fieldname);
                }
            }
        }
    }

    /**
     * Check if this is a draft submission
     *
     * @param array|object $data Form data
     * @return bool True if saving as draft
     */
    public function is_draft_submission($data) {
        if (is_array($data)) {
            return !empty($data['savedraft']);
        }
        return !empty($data->savedraft);
    }

    /**
     * Check if saving and adding another submission
     *
     * @param array|object $data Form data
     * @return bool True if saving and adding another
     */
    public function is_save_and_add_another($data) {
        if (is_array($data)) {
            return !empty($data['saveaddanother']);
        }
        return !empty($data->saveaddanother);
    }

    /**
     * Check if finishing the submission
     *
     * @param array|object $data Form data
     * @return bool True if finishing submission
     */
    public function is_finish_submission($data) {
        if (is_array($data)) {
            return !empty($data['finish']);
        }
        return !empty($data->finish);
    }

    /**
     * Generate the form element html for different shortcodes and replace with template output.
     *
     * @param template $template The template instance to use for parsing the form template.
     * @param array $fields Array of field objects available for rendering the form elements.
     * @param array $templatedata Data to pass to the template for rendering.
     * @param array $errors Array of validation errors to pass to the template.
     * @param int|null $submissionid Optional submission ID for context in rendering.
     */
    public function parse_from_template($template, $fields, $templatedata, $errors, $submissionid = null) {
        global $OUTPUT;

        $formcontent = $template->parse_form_template($fields, $templatedata, $errors, $submissionid);

        $this->parsedformoutput = $formcontent;
    }

    /**
     * Form display, modified version of moodle quickform display to allow for template-based rendering of form elements.
     *
     * Find the default render and renders the form header and any hidden elements,
     * then outputs the parsed template content, and finally renders the button array and form footer.
     *
     * @return void
     */
    public function display() {

        if (empty($this->parsedformoutput)) {
            parent::display();
            return;
        }

        // Finalize the form definition if not yet done
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }

        $renderer =& $this->_form->defaultRenderer();
        $renderer->startForm($this->_form);

        // Include the hidden elements in the form header so they are included in the form but not rendered in the template.
        foreach ($this->_form->_elements as $element) {
            if ($element->getType() === 'hidden') {
                $element->accept($renderer, false, false);
            }
        }

        // Parsed form content from the template is injected here instead of the default form elements rendering.
        $renderer->_html .= $this->parsedformoutput;
        // Submision buttons.
        $this->_form->getElement('buttonar')->accept($renderer, false, false);
        // End of the form.
        $renderer->finishForm($this->_form);

        echo $renderer->toHTML();
    }
}
