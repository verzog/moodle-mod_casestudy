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
 * Template class for case study submissions
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy;

use stdClass;
use html_writer;
use moodle_url;
use mod_casestudy\local\casestudy;
use mod_casestudy\local\submission;

defined('MOODLE_INTERNAL') || die();

class template {

    private $casestudyinstance;

    protected $casestudy;

    private $cm;

    protected $mform;

    private $context;

    public function __construct(casestudy $casestudy, $cm, $context) {
        $this->casestudyinstance = $casestudy;
        $this->casestudy = $this->casestudyinstance->get_casestudy_record();
        $this->cm = $cm;
        $this->context = $context;
    }

    public function render_submission(submission $submission, $fields = null, $contents = null, $grade = null) {
        global $DB, $OUTPUT, $USER;

        $templatecontent = $this->casestudy->singletemplate;

        if (empty($templatecontent)) {
            return null; // Signal to use default rendering.
        }

        // Get the formatted data from submission's export_for_template
        $submissiondata = $submission->export_for_template($OUTPUT);

        $submissionrecord = $submission->get_submission();
        $user = $DB->get_record('user', ['id' => $submissionrecord->userid]);

        // Build replacements using the export_for_template data
        $replacements = $this->get_replacements_from_export($submissiondata, $submissionrecord, $user, $grade);
        $output = $this->parse_template($templatecontent, $replacements);

        if (!empty($this->casestudy->csstemplate)) {
            $output = html_writer::tag('style', $this->casestudy->csstemplate) . $output;
        }

        return $output;
    }

    /**
     * Update the field html for the template replacements.
     *
     * @param array $fielddata The field data from export_for_template
     * @return string The HTML for the field value
     */
    protected function update_field_html($fielddata) {
        $attributes = [
            'class' => 'field-display mt-2 field-' . $fielddata['type']
        ];

        $valuehtml = '';
        if (!empty($fielddata['hasvalue'])) {
            $valuehtml = $fielddata['value'];
        } else {
            $valuehtml = html_writer::tag(
            'em',
            get_string('novalue', 'mod_casestudy'),
            ['class' => 'text-muted']
            );
        }

        return \html_writer::div(
            \html_writer::div($valuehtml, 'field-value'), null, $attributes);
    }

    /**
     * Get replacements for template tags using export_for_template data.
     *
     * @param array $submissiondata The data from submission->export_for_template()
     * @param stdClass $submission The submission record
     * @param stdClass $user The user record
     * @param stdClass|null $grade The grade record, if any
     *
     * @return array The replacements for the template tags.
     */
    private function get_replacements_from_export($submissiondata, $submission, $user, $grade) {
        global $OUTPUT;

        $replacements = [];

        // User information from casestudyinfo.
        $casestudyinfo = $submissiondata['casestudyinfo'];
        $userpicture = $OUTPUT->user_picture($user, ['size' => 50, 'class' => 'userpicture']);
        $replacements['[[userpicture]]'] = $userpicture;
        $replacements['[[user]]'] = $casestudyinfo['student'];
        $replacements['[[userid]]'] = $user->id;

        // Submission information.
        $replacements['[[timesubmitted]]'] = $casestudyinfo['timecreated'] ?: get_string('notsubmitted', 'mod_casestudy');
        $replacements['[[timecreated]]'] = $casestudyinfo['timecreated'];
        $replacements['[[timemodified]]'] = $casestudyinfo['timemodified'];
        $replacements['[[status]]'] = $casestudyinfo['statuslabel'];
        $replacements['[[attempt]]'] = $submission->attempt;

        // Field data from export_for_template - already formatted!
        if (isset($submissiondata['fieldsbyshortname'])) {

            foreach ($submissiondata['fieldsbyshortname'] as $shortname => $fielddata) {
                // Use the already rendered value from export_for_template.
                $fieldcontent = $this->update_field_html($fielddata);// ['value'];

                // Support both field name and shortname.
                $replacements['[[' . $fielddata['name'] . ']]'] = $fieldcontent;
                $replacements['[[' . $fielddata['name'] . '#name]]'] = $fielddata['name'];
                $replacements['[[' . $fielddata['name'] . '#id]]'] = $fielddata['id'];
                $replacements['[[' . $shortname . ']]'] = $fieldcontent;

                // Also support lowercase without spaces version.
                $replacements['[[' . strtolower(str_replace(' ', '', $fielddata['name'])) . ']]'] = $fieldcontent;
                $replacements['[[' . strtolower(str_replace(' ', '', $fielddata['name'])) . '#name]]'] = $fielddata['name'];
                $replacements['[[' . strtolower(str_replace(' ', '', $fielddata['name'])) . '#id]]'] = $fielddata['id'];
            }
        }

        // Grade information.
        if ($grade) {
            $replacements['[[grade]]'] = $this->get_grade_display($grade);
            $replacements['[[feedback]]'] = format_text($grade->feedback, $grade->feedbackformat);
            $replacements['[[grader]]'] = $this->get_grader_name($grade->graderid);
            $replacements['[[gradetime]]'] = userdate($grade->timemodified);
        } else {
            $replacements['[[grade]]'] = '';
            $replacements['[[feedback]]'] = '';
            $replacements['[[grader]]'] = '';
            $replacements['[[gradetime]]'] = '';
        }

        // Action buttons.
        $replacements['##edit##'] = $this->get_edit_button($submission);
        $replacements['##delete##'] = $this->get_delete_button($submission);
        $replacements['##view##'] = $this->get_view_button($submission);

        return $replacements;
    }

    /**
     * Parse the template content and replace tags with actual values.
     *
     * @param string $template The template content
     * @param array $replacements The replacements for the template tags
     *
     * @return string The parsed template with replacements
     */
    private function parse_template($template, $replacements) {
        $output = $template;

        foreach ($replacements as $tag => $value) {
            $output = str_replace($tag, $value, $output);
        }

        $output = preg_replace('/\[\[.*?\]\]/', '', $output);
        $output = preg_replace('/##.*?##/', '', $output);

        return $output;
    }

    public function get_default_template($fields) {
        $template = '<div class="casestudy-submission-single">';
        $template .= '<div class="submission-header">';
        $template .= '<h3>[[userpicture]] [[user]]</h3>';
        $template .= '<div class="submission-meta">';
        $template .= '<span class="status">Status: [[status]]</span> | ';
        $template .= '<span class="attempt">Attempt: [[attempt]]</span> | ';
        $template .= '<span class="submitted">Submitted: [[timesubmitted]]</span>';
        $template .= '</div>';
        $template .= '</div>';

        $template .= '<div class="submission-content">';
        foreach ($fields as $field) {
            $template .= '<div class="field-wrapper field-' . $field->id . '">';
            $template .= '<b> [[' . $field->name . '#name]] </b>';
            $template .= '[[' . $field->name . ']]';
            $template .= '</div>';
        }
        $template .= '</div>';

        $template .= '<div class="submission-grade">';
        $template .= '[[grade]]';
        $template .= '[[feedback]]';
        $template .= '</div>';

        $template .= '</div>';

        return $template;
    }

    private function get_grade_display($grade) {
        global $OUTPUT;

        $gradevalue = $grade->grade;
        if ($gradevalue == 0) {
            $gradetext = get_string('unsatisfactory', 'mod_casestudy');
            $gradeclass = 'badge badge-danger';
        } else if ($gradevalue == 1) {
            $gradetext = get_string('satisfactory', 'mod_casestudy');
            $gradeclass = 'badge badge-success';
        } else {
            return '';
        }

        return html_writer::span($gradetext, $gradeclass);
    }

    private function get_grader_name($graderid) {
        global $DB;

        if ($this->casestudy->hidegrader) {
            return get_string('grader', 'mod_casestudy');
        }

        $grader = $DB->get_record('user', ['id' => $graderid]);
        return fullname($grader);
    }

    private function get_edit_button($submission) {
        global $USER;

        if ($submission->userid != $USER->id) {
            return '';
        }

        $canresubmit = in_array($submission->status, ['draft', 'awaiting_resubmission']);
        if (!$canresubmit) {
            return '';
        }

        $url = new moodle_url('/mod/casestudy/submission.php', [
            'id' => $this->cm->id,
            'submissionid' => $submission->id,
            'action' => 'edit'
        ]);

        return html_writer::link($url, get_string('edit'), ['class' => 'btn btn-secondary']);
    }

    private function get_delete_button($submission) {
        global $USER;

        // Use submission_manager to check if user can delete
        $submissionmanager = \mod_casestudy\local\submission_manager::instance(
            $this->casestudy->id,
            $this->casestudy,
            $this->cm
        );

        if (!$submissionmanager->can_delete_submission($submission, $USER->id)) {
            return '';
        }

        $url = new moodle_url('/mod/casestudy/submission.php', [
            'id' => $this->cm->id,
            'submissionid' => $submission->id,
            'action' => 'delete',
            'sesskey' => sesskey()
        ]);

        return html_writer::link($url, get_string('delete'), ['class' => 'btn btn-danger']);
    }

    private function get_view_button($submission) {
        $url = new moodle_url('/mod/casestudy/view_casestudy.php', [
            'id' => $this->cm->id,
            'submissionid' => $submission->id
        ]);

        return html_writer::link($url, get_string('view'), ['class' => 'btn btn-primary']);
    }

    public function get_available_tags($fields) {
        $tags = [
            'fields' => [],
            'fieldattr' => [],
            'user' => [
                '[[userpicture]]' => get_string('tag_userpicture', 'mod_casestudy'),
                '[[user]]' => get_string('tag_user', 'mod_casestudy'),
                '[[userid]]' => get_string('tag_userid', 'mod_casestudy'),
            ],
            'submission' => [
                '[[timesubmitted]]' => get_string('tag_timesubmitted', 'mod_casestudy'),
                '[[timecreated]]' => get_string('tag_timecreated', 'mod_casestudy'),
                '[[timemodified]]' => get_string('tag_timemodified', 'mod_casestudy'),
                '[[status]]' => get_string('tag_status', 'mod_casestudy'),
                '[[attempt]]' => get_string('tag_attempt', 'mod_casestudy'),
            ],
            'grade' => [
                '[[grade]]' => get_string('tag_grade', 'mod_casestudy'),
                '[[feedback]]' => get_string('tag_feedback', 'mod_casestudy'),
                '[[grader]]' => get_string('tag_grader', 'mod_casestudy'),
                '[[gradetime]]' => get_string('tag_gradetime', 'mod_casestudy'),
            ],
        ];


        foreach ($fields as $field) {
            $tags['fields']['[[' . $field->name . ']]'] = $field->name;
            $tags['fieldattr']['[[' . $field->name . '#title]]'] = $field->name . ' ' . get_string('tag_title', 'mod_casestudy');
            $tags['fieldattr']['[[' . $field->name . '#id]]'] = $field->name . ' ' . get_string('tag_id', 'mod_casestudy');
        }

        return $tags;
    }

    /**
     * Check if a custom form template has been configured.
     *
     * @return bool True if a form template exists
     */
    public function has_form_template(): bool {
        return !empty($this->casestudy->formtemplate);
    }

    /**
     * Parse and render form template with field inputs.
     *
     * This is an alias for render_form() for backward compatibility.
     *
     * @param array $fields Array of field objects
     * @param array $values Current field values (field_id => value)
     * @param array $errors Validation errors (field_id => error message)
     * @param int|null $submissionid Current submission ID
     * @return string|null Rendered form HTML, or null if no template
     */
    public function parse_form_template($fields, $values = [], $errors = [], $submissionid = null): ?string {
        return $this->render_form($fields, $values, $errors, $submissionid);
    }

    /**
     * Get available tags for form templates.
     *
     * Form templates use field tags that get replaced with actual form input elements.
     *
     * @param array $fields Array of field objects
     * @return array Available tags organised by category
     */
    public function get_available_form_tags($fields) {
        $tags = [
            'fields' => [],
            'fieldattr' => [],
        ];

        foreach ($fields as $field) {
            // Main field tag - replaced with the form input element
            $tags['fields']['[[' . $field->name . ']]'] = get_string('tag_form_field', 'mod_casestudy', $field->name);

            // Field attribute tags
            $tags['fieldattr']['[[' . $field->name . '#label]]'] = get_string('tag_form_label', 'mod_casestudy', $field->name);
            $tags['fieldattr']['[[' . $field->name . '#description]]'] = get_string('tag_form_description', 'mod_casestudy', $field->name);
            $tags['fieldattr']['[[' . $field->name . '#input]]'] = get_string('tag_form_input', 'mod_casestudy', $field->name);
            $tags['fieldattr']['[[' . $field->name . '#required]]'] = get_string('tag_form_required', 'mod_casestudy', $field->name);
            $tags['fieldattr']['[[' . $field->name . '#id]]'] = get_string('tag_form_id', 'mod_casestudy', $field->name);
        }

        return $tags;
    }

    /**
     * Get default form template for submission entry.
     *
     * @param array $fields Array of field objects
     * @return string Default form template HTML
     */
    public function get_default_form_template($fields) {
        $template = '<div class="casestudy-submission-form">';

        foreach ($fields as $field) {
            if ($field->type === 'sectionheading') {
                $template .= '[[' . $field->name . ']]';
                if (!empty($field->description)) {
                    $template .= '<div class="section-description text-muted">[[' . $field->name . '#description]]</div>';
                }
                // $template .= '</div>';
            } else {
                $template .= '<div class="form-group field-wrapper mb-3" data-field="' . $field->shortname . '">';
                $template .= '[[' . $field->name . ']]';
                $template .= '</div>';
            }
        }

        $template .= '</div>';

        return $template;
    }

    public function set_form($form) {
        $this->mform = $form;
    }

    /**
     * Render a submission form using the form template.
     *
     * Parses the formtemplate and replaces field tags with actual form input elements.
     *
     * @param array $fields Array of field objects
     * @param array $values Current field values (field_id => value)
     * @param array $errors Validation errors (field_id => error message)
     * @param int|null $submissionid Current submission ID
     * @return string Rendered form HTML
     */
    public function render_form($fields, $values = [], $errors = [], $submissionid = null) {
        global $OUTPUT, $PAGE;

        $templatecontent = $this->casestudy->formtemplate;

        if (empty($templatecontent)) {
            $templatecontent = $this->get_default_form_template($fields);
        }

        $fieldmanager = \mod_casestudy\local\field_manager::instance($this->casestudy->id);
        $replacements = [];

        foreach ($fields as $field) {
            $fieldclass = $fieldmanager->get_field_type_class($field->type, $field);

            if (empty($fieldclass)) {
                continue;
            }

            $fieldid = $field->id;

            $rawvalue = isset($values[$fieldid]) ? $values[$fieldid] : '';

            // Handle object values (like field_data objects) by extracting the content property
            if (is_object($rawvalue) && isset($rawvalue->content)) {
                $value = $rawvalue->content;
            } else {
                $value = $rawvalue;
            }

            // Check for errors - errors may be keyed by field name (field_123) or field_123_group for checkbox/radio
            $fieldname = 'field_' . $fieldid;
            $errorkey = in_array($field->type, ['checkbox', 'radio']) ? $fieldname . '_group' : $fieldname;
            $fielderrors = [];
            if (isset($errors[$errorkey])) {
                $fielderrors = (array)$errors[$errorkey];
            } else if (isset($errors[$fieldid])) {
                // Also check by field ID for backward compatibility
                $fielderrors = (array)$errors[$fieldid];
            }


            if (!$this->mform->get_form()->elementExists($fieldname)) {

                $existingroup = $this->mform->get_form()->elementExists($fieldname . '_group');
                $existinheading = $this->mform->get_form()->elementExists($fieldname . '_heading');
                if (!$existingroup && !$existinheading) {
                    continue;
                }

                if ($existingroup) {
                    $fieldname .= '_group';
                } else if ($existinheading) {
                    $fieldname .= '_heading';
                }
            }

            // Main field tag - complete form element with label, input, description, errors
            $mformelement = $this->mform->get_form()->getElement($fieldname);

            // Get the configuration for the field to determine if it's required, etc.
            $fieldconfig = $fieldclass->get_config();

            // Update the element id, otherwise mform throw a preg_replace error.
            $mformelement->updateAttributes(['id' => 'field_' . $fieldid]);
            $error = $this->mform->get_form()->_errors[$fieldname] ?? null;  // Confirm the element has any errors.

            try {

                // For the filemanager element, render will include the js for filemanager.
                // When renderering for inputhtml and field html separetly, loads the js twice but only one element is included using template.
                if ($mformelement instanceof \MoodleQuickForm_filemanager) {
                    // For section headings, we want to render them without the form-group wrapper that mform adds.
                    [$fieldcontext, $fieldhtml] = $PAGE->get_renderer('mod_casestudy')->mform_element_filemanager($mformelement, $fieldconfig['required'], false, $error, false);
                    $inputhtml = $fieldcontext['element']['html'];
                } else {
                    // Render the form element using mform's rendering to ensure consistency with Moodle's form styles and error handling.
                    $fieldhtml = $OUTPUT->mform_element($mformelement, $fieldconfig['required'], false, $error, false);
                }

                // Add form-control class to the element.
                if (!in_array($field->type, ['file', 'textarea'])) {
                    $mformelement->_attributes['class'] = isset($mformelement->_attributes['class'])
                        ? $mformelement->_attributes['class'] . ' form-control' : 'form-control';
                }

            } catch (\Exception $e) {
                // Fallback to default rendering if element not found or any error occurs.
                $fieldhtml = "";
            }

            if (empty($fieldhtml) && $mformelement instanceof \HTML_QuickForm_header) {
                $renderer =& $this->mform->get_form()->defaultRenderer();
                $renderer->renderHeader($mformelement);
                $fieldhtml = $renderer->toHtml();
                $renderer->_html = ''; // Clear the renderer buffer after rendering the header.
                $PAGE->requires->js_amd_inline(<<<EOT
                    require([], function() {
                        const fieldcontainer = document.querySelector('#field_{$fieldid}container');
                        if (fieldcontainer) {
                            fieldcontainer.closest('fieldset').querySelectorAll('.form-group.field-wrapper')?.forEach((e) => {
                                fieldcontainer.append(e);
                            });
                        }
                    });
                EOT);
            }

            if (empty($fieldhtml)) {
                $fieldhtml = $mformelement ? $mformelement->toHtml() : '';
            }

            try {

                $mformelement->updateAttributes(['class' => str_replace('form-control', '', $mformelement->_attributes['class'] ?? '')]);

                if ($mformelement instanceof \templatable && !($mformelement instanceof \MoodleQuickForm_filemanager)) {
                    // Remove the formcontrol class, mform add the class to the template([[*:input]]) wrapper div
                    // It cause some issue with the layout, so we need to remove it from the element before render the template.

                    $templatename = 'core_form/element-' . $mformelement->getType() . '-inline';

                    $params = ['element' => $mformelement->export_for_template($OUTPUT)];
                    $params['error'] = $error; // Include the error message in the template context.

                    $inputhtml = $OUTPUT->render_from_template($templatename, $params);

                } else {
                    $inputhtml = $inputhtml ?: $mformelement->toHtml();
                }

            } catch (\Exception $e) {
                // Fallback to default rendering if specific template not found.
                $inputhtml = $mformelement->toHtml();
            }

            $replacements['[[' . $field->name . ']]'] = $fieldhtml;

            // Individual component tags for advanced template customization
            $required = !empty($field->required);
            $requiredmark = $required ? '<span class="text-danger">*</span>' : '';

            // Label tag.
            $replacements['[[' . $field->name . '#label]]'] = format_string($field->name) . ' ' . $requiredmark;

            // Description tag.
            $descriptionhtml = '';
            if (!empty($field->description)) {
                $descriptionhtml = format_text($field->description, FORMAT_HTML);
            }

            $replacements['[[' . $field->name . '#description]]'] = $descriptionhtml;

            // Input only tag (just the input element without wrapper)
            $haserrors = !empty($fielderrors);
            // $fieldclass->get_input_html($fieldname, $value, $submissionid, $haserrors);
            $replacements['[[' . $field->name . '#input]]'] = $inputhtml;

            // Required indicator tag
            $replacements['[[' . $field->name . '#required]]'] = $requiredmark;

            // Field ID tag
            $replacements['[[' . $field->name . '#id]]'] = $fieldname;

            // Also support shortname-based tags
            if (!empty($field->shortname) && $field->shortname !== $field->name) {
                $replacements['[[' . $field->shortname . ']]'] = $fieldhtml;
                $replacements['[[' . $field->shortname . '#label]]'] = format_string($field->name) . ' ' . $requiredmark;
                $replacements['[[' . $field->shortname . '#description]]'] = $descriptionhtml;
                $replacements['[[' . $field->shortname . '#input]]'] = $inputhtml;
                $replacements['[[' . $field->shortname . '#required]]'] = $requiredmark;
                $replacements['[[' . $field->shortname . '#id]]'] = $fieldname;
            }
        }

        // Parse the template with replacements
        $output = $this->parse_template($templatecontent, $replacements);

        // Add CSS if defined
        if (!empty($this->casestudy->csstemplate)) {
            $output = html_writer::tag('style', $this->casestudy->csstemplate) . $output;
        }

        return $output;
    }
}