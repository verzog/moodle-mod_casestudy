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
 * The main casestudy configuration form
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form
 */
class mod_casestudy_mod_form extends moodleform_mod {
    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('casestudyname', 'mod_casestudy'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'casestudyname', 'mod_casestudy');

        // Adding the standard "intro" and "introformat" fields
        $this->standard_intro_elements();

        // Entries section
        $mform->addElement('header', 'entriessection', get_string('entries', 'mod_casestudy'));

        // Maximum number of submissions per student
        $options = [0 => get_string('unlimited', 'mod_casestudy')];
        for ($i = 1; $i <= 50; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'maxsubmissions', get_string('maxsubmissions', 'mod_casestudy'), $options);
        $mform->setDefault('maxsubmissions', 0);
        $mform->addHelpButton('maxsubmissions', 'maxsubmissions', 'mod_casestudy');

        // Availability section
        $mform->addElement('header', 'availabilitysection', get_string('availability', 'mod_casestudy'));

        // Allow submissions from (timeopen)
        $mform->addElement(
            'date_time_selector',
            'timeopen',
            get_string('allowsubmissionsfromdate', 'mod_casestudy'),
            ['optional' => true]
        );
        $mform->setDefault('timeopen', 0);
        $mform->addHelpButton('timeopen', 'allowsubmissionsfromdate', 'mod_casestudy');

        // Due date (timeclose)
        $mform->addElement(
            'date_time_selector',
            'timeclose',
            get_string('duedate', 'mod_casestudy'),
            ['optional' => true]
        );
        $mform->setDefault('timeclose', 0);
        $mform->addHelpButton('timeclose', 'duedate', 'mod_casestudy');

        // Notifications section
        $mform->addElement('header', 'notificationssection', get_string('notifications', 'mod_casestudy'));

        // Notify graders about submissions
        $mform->addElement('selectyesno', 'notifygraders', get_string('notifygraders', 'mod_casestudy'));
        $mform->setDefault('notifygraders', 1);
        $mform->addHelpButton('notifygraders', 'notifygraders', 'mod_casestudy');

        // Email others
        $mform->addElement('text', 'notifyemail', get_string('notifyemail', 'mod_casestudy'), ['size' => '64']);
        $mform->setType('notifyemail', PARAM_TEXT);
        $mform->addHelpButton('notifyemail', 'notifyemail', 'mod_casestudy');

        // Default for 'Notify student'
        $mform->addElement('selectyesno', 'notifystudentdefault', get_string('notifystudentdefault', 'mod_casestudy'));
        $mform->setDefault('notifystudentdefault', 1);
        $mform->addHelpButton('notifystudentdefault', 'notifystudentdefault', 'mod_casestudy');

        // Submission settings section
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'mod_casestudy'));

        // Require students to click submit button
        $mform->addElement('selectyesno', 'requiresubmit', get_string('requiresubmit', 'mod_casestudy'));
        $mform->setDefault('requiresubmit', 1);
        $mform->addHelpButton('requiresubmit', 'requiresubmit', 'mod_casestudy');

        // Require submission statement
        $mform->addElement('selectyesno', 'requireacceptance', get_string('requireacceptance', 'mod_casestudy'));
        $mform->setDefault('requireacceptance', 0);
        $mform->addHelpButton('requireacceptance', 'requireacceptance', 'mod_casestudy');

        // Maximum attempts
        $attemptsoptions = [0 => get_string('unlimited', 'mod_casestudy')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptsoptions[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', 'mod_casestudy'), $attemptsoptions);
        $mform->setDefault('maxattempts', 10);
        $mform->addHelpButton('maxattempts', 'maxattempts', 'mod_casestudy');

        // Resubmissions based on previous attempt
        $mform->addElement('selectyesno', 'resubmissionbased', get_string('resubmissionbased', 'mod_casestudy'));
        $mform->setDefault('resubmissionbased', 1);
        $mform->addHelpButton('resubmissionbased', 'resubmissionbased', 'mod_casestudy');

        // Grade scale - we'll use a custom scale
        $this->standard_grading_coursemodule_elements();

        // Hide grader identity from students
        $mform->addElement('selectyesno', 'hidegrader', get_string('hidegrader', 'mod_casestudy'));
        $mform->setDefault('hidegrader', 0);
        $mform->addHelpButton('hidegrader', 'hidegrader', 'mod_casestudy');

        // Grader information
        $mform->addElement(
            'editor',
            'graderinfo_editor',
            get_string('graderinfo', 'mod_casestudy'),
            null,
            $this->get_editor_options()
        );
        $mform->addHelpButton('graderinfo_editor', 'graderinfo', 'mod_casestudy');

        // Add standard elements, common to all modules
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules
        $this->add_action_buttons();

        // Add JavaScript for dynamic category value dropdowns (only if editing existing instance).
        if ($this->current->instance) {
            global $PAGE;

            $PAGE->requires->js_amd_inline(<<<'EOT'
                require(['jquery'], function($) {
                    // Get the index-to-value map from the page data.
                    var indexToValue = window.categoryValueMaps ? window.categoryValueMaps.indexToValue : {};

                    // Filter dropdown options based on selected field.
                    function filterValueOptions(fieldSelect, valueSelect, preserveSelection) {
                        var selectedFieldId = fieldSelect.val();
                        var currentValue = valueSelect.val();

                        // Show/hide options based on selected field.
                        valueSelect.find('option').each(function() {
                            var optionIndex = $(this).val();

                            if (optionIndex === '0' || optionIndex === '') {
                                // Always show "Any value" option (index 0).
                                $(this).show();
                            } else if (selectedFieldId && selectedFieldId != '0') {
                                // Check if this index belongs to the selected field.
                                var valueData = indexToValue[optionIndex];
                                if (valueData && valueData.fieldid == selectedFieldId) {
                                    $(this).show();
                                } else {
                                    $(this).hide();
                                }
                            } else {
                                // No field selected, show all options.
                                $(this).show();
                            }
                        });

                        // Only reset if we're not preserving selection AND current selection is now hidden.
                        if (!preserveSelection && currentValue && valueSelect.find('option[value="' + currentValue + '"]:visible').length === 0) {
                            valueSelect.val('0');
                        }
                    }

                    // Initialize on page load.
                    $(document).ready(function() {
                        $('select[id^="id_categoryrule_fieldid"]').each(function() {
                            var fieldSelect = $(this);
                            // Extract the actual index from the element ID (e.g., "id_categoryrule_fieldid_2" -> 2)
                            var elementId = fieldSelect.attr('id');
                            var actualIndex = elementId.replace('id_categoryrule_fieldid_', '');
                            var valueSelect = $('#id_categoryrule_value_' + actualIndex);

                            if (valueSelect.length) {
                                // Bind change event - only filter on field change, not on load.
                                fieldSelect.on('change', function() {
                                    filterValueOptions(fieldSelect, valueSelect, false);
                                });

                                // On initial load, filter but preserve the current selection.
                                filterValueOptions(fieldSelect, valueSelect, true);
                            }
                        });
                    });
                });
            EOT
            );
        }
    }

    /**
     * Add any custom completion rules to the form.
     *
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        global $DB, $PAGE;

        $mform =& $this->_form;
        $suffix = $this->get_suffix();

        $mform->hideIf('conditionsgroup', 'completion', 'eq', COMPLETION_TRACKING_AUTOMATIC);

        $countoptions = [];
        for ($i = 0; $i <= 50; $i++) {
            $countoptions[$i] = $i;
        }

        $group = [];
        $completionaggrsel = 'completionaggr' . $suffix;

        $group[] =& $mform->createElement('static', 'completionsatisfactorydesc', '', get_string('completionsatisfactorydesc', 'mod_casestudy'));
        $group[] =& $mform->createElement(
            'select',
            $completionaggrsel,
            '',
            [
                CASESTUDY_COMPLETION_ALL => get_string('completionsatisfactoryall', 'mod_casestudy'),
                CASESTUDY_COMPLETION_ANY => get_string('completionsatisfactoryany', 'mod_casestudy'),
            ]
        );
        $mform->setType($completionaggrsel, PARAM_INT);
        $mform->setDefault($completionaggrsel, CASESTUDY_COMPLETION_ALL);

        $satisfactoryconditiongroupel = 'satisfactoryconditiongroup' . $suffix;
        $mform->addGroup($group, $satisfactoryconditiongroupel, '', ' ', false);

        // Completion rule: Total satisfactory submissions.
        $group1 = [];
        $completionsatisfactoryenabledel = 'completionsatisfactory' . $suffix;
        $group1[] =& $mform->createElement(
            'checkbox',
            $completionsatisfactoryenabledel,
            '',
            get_string('completionsatisfactorysubmissions', 'mod_casestudy')
        );

        $completionsatisfactoryel = 'cmpsatisfactorysubmissions' . $suffix;
        $group1[] =& $mform->createElement('select', $completionsatisfactoryel, '', $countoptions);
        $mform->setType($completionsatisfactoryel, PARAM_INT);

        $completionsatisfactorygroupel = 'completionsatisfactorygroup' . $suffix;
        $mform->addGroup($group1, $completionsatisfactorygroupel, '', ' ', false);
        $mform->hideIf($completionsatisfactoryel, $completionsatisfactoryenabledel, 'notchecked');
        $mform->addHelpButton($completionsatisfactorygroupel, 'completionsatisfactorysubmissions', 'mod_casestudy');

        $completioncategoryel = 'completioncategory' . $suffix;
        $mform->addElement('hidden', $completioncategoryel, 0);
        $mform->setType($completioncategoryel, PARAM_INT);

        $categoryfields = [0 => get_string('choosedots')];
        $allcategoryvalues = [0 => get_string('anyvalue', 'mod_casestudy')];
        $valuetoindexmap = [];
        $indextovaluemap = [];

        if ($this->current->instance) {
            $fields = $DB->get_records('casestudy_fields', [
                'casestudyid' => $this->current->instance, 'category' => 1], 'sortorder ASC', 'id, name, param1');

            $optionindex = 1;
            foreach ($fields as $field) {
                $categoryfields[$field->id] = format_string($field->name);

                $values = $field->param1 ? json_decode($field->param1, true) : [];
                if (is_array($values)) {
                    foreach ($values as $v) {
                        $allcategoryvalues[$optionindex] = $v;
                        $valuetoindexmap[$field->id . '-' . $v] = $optionindex;
                        $indextovaluemap[$optionindex] = ['fieldid' => $field->id, 'value' => $v];
                        $optionindex++;
                    }
                }
            }

            $PAGE->requires->data_for_js('categoryValueMaps', [
                'valueToIndex' => $valuetoindexmap,
                'indexToValue' => $indextovaluemap,
            ]);
        }

        $existingrules = [];
        if ($this->current->instance) {
            $existingrules = $DB->get_records(
                'casestudy_completion_rules',
                [
                    'casestudyid' => $this->current->instance,
                    'ruletype' => CASESTUDY_COMPLETION_CATEGORY,
                ],
                'sortorder ASC'
            );
        }

        $rulecount = max(1, count($existingrules));

        $repeatarray = [];
        $repeateloptions = [];

        $repeatarray[] = $mform->createElement('advcheckbox', 'categoryrule_enabled', get_string('completioncategorysubmissions', 'mod_casestudy'));
        $repeateloptions['categoryrule_enabled']['type'] = PARAM_INT;
        $repeateloptions['categoryrule_enabled']['hideif'] = ['completion', 'neq', COMPLETION_TRACKING_AUTOMATIC];

        $repeatarray[] = $mform->createElement('select', 'categoryrule_fieldid', get_string('categoryfield', 'mod_casestudy'), $categoryfields);
        $repeateloptions['categoryrule_fieldid']['type'] = PARAM_INT;
        $repeateloptions['categoryrule_fieldid']['hideif'] = ['categoryrule_enabled', 'notchecked'];

        $repeatarray[] = $mform->createElement('select', 'categoryrule_value', get_string('categoryvalue', 'mod_casestudy'), $allcategoryvalues);
        $repeateloptions['categoryrule_value']['type'] = PARAM_INT;
        $repeateloptions['categoryrule_value']['hideif'] = ['categoryrule_enabled', 'notchecked'];

        $repeatarray[] = $mform->createElement('select', 'categoryrule_count', get_string('requiredcount', 'mod_casestudy'), $countoptions);
        $repeateloptions['categoryrule_count']['type'] = PARAM_INT;
        $repeateloptions['categoryrule_count']['hideif'] = ['categoryrule_enabled', 'notchecked'];

        $repeatarray[] = $mform->createElement('hidden', 'categoryrule_id', 0);
        $repeateloptions['categoryrule_id']['type'] = PARAM_INT;

        $this->repeat_elements(
            $repeatarray,
            $rulecount,
            $repeateloptions,
            'categoryrule_repeats',
            'categoryrule_add',
            1,
            get_string('addcategoryrule', 'mod_casestudy'),
            true
        );

        // Hide the add category button when completion is not set to automatic.
        $mform->hideIf('categoryrule_add', 'completion', 'neq', COMPLETION_TRACKING_AUTOMATIC);

        if (empty($this->_cm) || !empty($this->current->completionexpected)) {
            $mform->hideIf('categoryrule_add', 'completionunlocked', 'eq', 0);
        }

        return [$satisfactoryconditiongroupel, $completionsatisfactorygroupel, $completioncategoryel];
    }

    public function add_completiongrade_elements(
        string $modname,
        bool $rating = false
    ): void {
        // No grade-based completion.
    }

    /**
     * Handle form data after it's been set.
     * This is used to freeze repeated category rule elements when completion is locked.
     */
    public function definition_after_data() {
        parent::definition_after_data();

        $mform = $this->_form;

        $completionunlocked = $mform->getElementValue('completionunlocked');

        // If completion is locked and we have category rule elements, disable the add button.
        if (empty($completionunlocked)) {
            if ($mform->elementExists('categoryrule_add')) {
                // Disable the add button when completion is locked.
                $element = $mform->getElement('categoryrule_add');
                $element->updateAttributes(['disabled' => 'disabled']);
            }

            // Also disable all category rule checkboxes and fields when locked.
            $repeatno = optional_param('categoryrule_repeats', 0, PARAM_INT);
            if ($repeatno == 0) {
                // Get the number of existing rules.
                global $DB;
                if (!empty($this->current->instance)) {
                    $existingrules = $DB->get_records(
                        'casestudy_completion_rules',
                        ['casestudyid' => $this->current->instance, 'ruletype' => CASESTUDY_COMPLETION_CATEGORY]
                    );
                    $repeatno = count($existingrules);
                }
            }

            for ($i = 0; $i < $repeatno; $i++) {
                if ($mform->elementExists('categoryrule_enabled[' . $i . ']')) {
                    $mform->freeze('categoryrule_enabled[' . $i . ']');
                }
                if ($mform->elementExists('categoryrule_fieldid[' . $i . ']')) {
                    $mform->freeze('categoryrule_fieldid[' . $i . ']');
                }
                if ($mform->elementExists('categoryrule_value[' . $i . ']')) {
                    $mform->freeze('categoryrule_value[' . $i . ']');
                }
                if ($mform->elementExists('categoryrule_count[' . $i . ']')) {
                    $mform->freeze('categoryrule_count[' . $i . ']');
                }
            }
        }
    }

    /**
     * Called during validation to see whether some module-specific completion rules are selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();

        // Check if total satisfactory rule is enabled.
        $satisfactoryenabled = !empty($data['completionsatisfactory' . $suffix]);

        // Check if any category rules are enabled.
        $categoryenabled = false;
        if (!empty($data['categoryrule_enabled'])) {
            foreach ($data['categoryrule_enabled'] as $index => $enabled) {
                if ($enabled && !empty($data['categoryrule_fieldid'][$index]) && $data['categoryrule_fieldid'][$index] != 0) {
                    $categoryenabled = true;
                    break;
                }
            }
        }

        return $satisfactoryenabled || $categoryenabled;
    }

    /**
     * Get editor options
     * @return array
     */
    private function get_editor_options() {
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true,
            'context' => $this->context,
            'subdirs' => true,
        ];
    }

    /**
     * Enforce defaults here
     *
     * @param array $defaultvalues Form defaults
     * @return void
     **/
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        if ($this->current->instance) {
            // Prepare editor data.
            $draftitemid = file_get_submitted_draft_itemid('graderinfo_editor');
            $defaultvalues['graderinfo_editor']['text'] =
                file_prepare_draft_area(
                    $draftitemid,
                    $this->context->id,
                    'mod_casestudy',
                    'graderinfo',
                    0,
                    $this->get_editor_options(),
                    isset($defaultvalues['graderinfo']) ? $defaultvalues['graderinfo'] : ''
                );
            $defaultvalues['graderinfo_editor']['itemid'] = $draftitemid;
            $defaultvalues['graderinfo_editor']['format'] =
                isset($defaultvalues['graderinfoformat']) ? $defaultvalues['graderinfoformat'] : FORMAT_HTML;

            // Load completion rules from the database.
            $rules = $DB->get_records(
                'casestudy_completion_rules',
                ['casestudyid' => $this->current->instance],
                'sortorder ASC'
            );

            $categoryindex = 0;
            $hascategoryrulesenabled = false;
            foreach ($rules as $rule) {
                if ($rule->ruletype == CASESTUDY_COMPLETION_TOTAL) {
                    $defaultvalues['completionsatisfactory'] = 1;
                    $defaultvalues['cmpsatisfactorysubmissions'] = $rule->count;
                } else if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY) {
                    $defaultvalues['categoryrule_enabled'][$categoryindex] = $rule->enabled;
                    $defaultvalues['categoryrule_fieldid'][$categoryindex] = $rule->fieldid;
                    // The categoryvalue is already stored as the option index, load it directly.
                    $defaultvalues['categoryrule_value'][$categoryindex] = !empty($rule->categoryvalue) ? $rule->categoryvalue : 0;
                    $defaultvalues['categoryrule_count'][$categoryindex] = $rule->count;
                    $defaultvalues['categoryrule_id'][$categoryindex] = $rule->id;

                    // Track if any category rule is enabled.
                    if ($rule->enabled && !empty($rule->fieldid)) {
                        $hascategoryrulesenabled = true;
                    }

                    $categoryindex++;
                }
            }

            // Set completioncategory hidden field if any category rules are enabled.
            if ($hascategoryrulesenabled) {
                $defaultvalues['completioncategory'] = 1;
            }
        }
        $suffix = $this->get_suffix();

        // Apply suffix to basic completion fields.
        $completionfields = ['completionaggr', 'completionsatisfactory', 'cmpsatisfactorysubmissions', 'completioncategory'];
        foreach ($completionfields as $field) {
            if (isset($defaultvalues[$field])) {
                $defaultvalues[$field . $suffix] = $defaultvalues[$field];
            }
        }
    }

    /**
     * Prepare the data after form was submited.
     *
     * @param  mixed $data submitted data
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $suffix = $this->get_suffix();

        // Copy basic completion fields from suffixed versions.
        $completionfields = ['completionaggr', 'completionsatisfactory', 'cmpsatisfactorysubmissions', 'completioncategory'];
        foreach ($completionfields as $field) {
            if (isset($data->{$field . $suffix})) {
                $data->$field = $data->{$field . $suffix};
            }
        }

        // When completion is unlocked, we can modify the settings.
        if (!empty($data->completionunlocked)) {
            $completion = isset($data->{'completion' . $suffix}) ? $data->{'completion' . $suffix} : $data->completion;
            $autocompletion = !empty($completion) && $completion == COMPLETION_TRACKING_AUTOMATIC;

            if (!$autocompletion) {
                // Clear all completion values when not using automatic completion.
                $data->completionaggr = 0;
                $data->{'completionaggr' . $suffix} = 0;
                $data->cmpsatisfactorysubmissions = 0;
                $data->{'cmpsatisfactorysubmissions' . $suffix} = 0;
                unset($data->completionsatisfactory);
                unset($data->{'completionsatisfactory' . $suffix});
                unset($data->completioncategory);
                unset($data->{'completioncategory' . $suffix});
                unset($data->categoryrule_enabled);
                unset($data->categoryrule_fieldid);
                unset($data->categoryrule_value);
                unset($data->categoryrule_count);
            } else {
                // Clear individual rules if their checkboxes are unchecked.
                if (empty($data->{'completionsatisfactory' . $suffix})) {
                    $data->cmpsatisfactorysubmissions = 0;
                    $data->{'cmpsatisfactorysubmissions' . $suffix} = 0;
                    $data->completionsatisfactory = 0;
                    $data->{'completionsatisfactory' . $suffix} = 0;
                }

                // Set completioncategory based on whether any category rules are enabled.
                $categoryenabled = false;
                if (!empty($data->categoryrule_enabled)) {
                    foreach ($data->categoryrule_enabled as $index => $enabled) {
                        if ($enabled && !empty($data->categoryrule_fieldid[$index]) && $data->categoryrule_fieldid[$index] != 0) {
                            $categoryenabled = true;
                            break;
                        }
                    }
                }
                $data->completioncategory = $categoryenabled ? 1 : 0;
                $data->{'completioncategory' . $suffix} = $categoryenabled ? 1 : 0;
            }
        } else {
            // Completion is locked - preserve existing completion rules from the database.
            if (!empty($this->current->instance)) {
                global $DB;

                // Load existing completion rules to preserve them.
                $existingrules = $DB->get_records(
                    'casestudy_completion_rules',
                    ['casestudyid' => $this->current->instance],
                    'sortorder ASC'
                );

                $categoryindex = 0;
                foreach ($existingrules as $rule) {
                    if ($rule->ruletype == CASESTUDY_COMPLETION_TOTAL) {
                        $data->completionsatisfactory = 1;
                        $data->cmpsatisfactorysubmissions = $rule->count;
                    } else if ($rule->ruletype == CASESTUDY_COMPLETION_CATEGORY) {
                        $data->categoryrule_enabled[$categoryindex] = $rule->enabled;
                        $data->categoryrule_fieldid[$categoryindex] = $rule->fieldid;
                        $data->categoryrule_value[$categoryindex] = !empty($rule->categoryvalue) ? $rule->categoryvalue : 0;
                        $data->categoryrule_count[$categoryindex] = $rule->count;
                        $data->categoryrule_id[$categoryindex] = $rule->id;
                        $categoryindex++;
                    }
                }

                // Also preserve the aggregation mode.
                if (!empty($this->current->completionaggr)) {
                    $data->completionaggr = $this->current->completionaggr;
                }
            }
        }
    }

    /**
     * Validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate email addresses if provided
        if (!empty($data['notifyemail'])) {
            $emails = explode(',', $data['notifyemail']);
            $invalidemail = '';
            foreach ($emails as $email) {
                $email = trim($email);
                if (!empty($email) && !validate_email($email)) {
                    $invalidemail = $email;
                    break;
                }
            }
            if ($invalidemail) {
                $errors['notifyemail'] = get_string('invalidemailaddress', 'mod_casestudy', $invalidemail);
            }
        }

        // Validate availability dates
        if (!empty($data['timeopen']) && !empty($data['timeclose'])) {
            if ($data['timeclose'] < $data['timeopen']) {
                $errors['timeclose'] = get_string('closebeforeopen', 'mod_casestudy');
            }
        }

        return $errors;
    }
}
