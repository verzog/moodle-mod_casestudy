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
 * Field edit form for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\local\forms;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for editing Case Study fields
 */
class field_edit_form extends \moodleform {

    /** @var \mod_casestudy\field_manager Field manager instance */
    private $fieldmanager;

    /** @var \mod_casestudy\field_types\base_field Field type class instance */
    private $field;

    /** @var bool Whether we're editing an existing field */
    private $editing;

    /** @var string Field type */
    private $fieldtype;

    /**
     * Constructor
     *
     * @param string $action Form action URL
     * @param array $customdata Custom data including field_manager, field_class, editing flag
     */
    public function __construct($action, $customdata) {

        $this->fieldmanager = $customdata['fieldmanager'];
        $this->field = $customdata['fieldclass'];
        $this->editing = $customdata['editing'];
        $this->fieldtype = $customdata['fieldtype'];

        parent::__construct($action, $customdata);
    }

    /**
     * Form definition
     */
    protected function definition() {
        $mform = $this->_form;

        // Hidden fields
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'fieldid');
        $mform->setType('fieldid', PARAM_INT);

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_ALPHA);

        // Field type display (read-only)
        $mform->addElement('static', 'fieldtype_display', get_string('fieldtype', 'mod_casestudy'),
                          get_string('fieldtype_' . $this->fieldtype, 'mod_casestudy'));

        // Basic field properties
        $mform->addElement('text', 'name', get_string('fieldname', 'mod_casestudy'), array('size' => 50));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('name', 'fieldname', 'mod_casestudy');

        // Shortname field description
        $mform->addElement('text', 'shortname', get_string('fieldshortname', 'mod_casestudy'), array('size' => 50));
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('shortname', 'fieldshortname', 'mod_casestudy');

        $mform->addElement('textarea', 'description', get_string('fielddescription', 'mod_casestudy'),
                          array('rows' => 3, 'cols' => 50));
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'fielddescription', 'mod_casestudy');

        // Required checkbox
        if ($this->field->supports_required()) {
            $mform->addElement('advcheckbox', 'required', get_string('required'));
            $mform->addHelpButton('required', 'fieldrequired', 'mod_casestudy');
        }
        // Category checkbox.
        if ($this->field->supports_categories()) {
            $mform->addElement('advcheckbox', 'category', get_string('iscategory', 'mod_casestudy'));
            $mform->addHelpButton('category', 'category', 'mod_casestudy');
        }

        // Show in list view checkbox
        if ($this->field->supports_listview()) {
            $mform->addElement('advcheckbox', 'showlistview', get_string('showlistview', 'mod_casestudy'));
            $mform->addHelpButton('showlistview', 'showlistview', 'mod_casestudy');
        }

        // Add field-specific configuration elements
        if ($this->field) {
            $this->field->additional_form_elements($mform);
        }

        // Action buttons
        $this->add_action_buttons(true,
            $this->editing ? get_string('updatefield', 'mod_casestudy') : get_string('addfield', 'mod_casestudy'));
    }

    /**
     * Form validation
     *
     * @param array $data Form data
     * @param array $files Form files
     * @return array Validation errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate field name uniqueness
        if (!empty($data['shortname'])) {
            $existing = $this->fieldmanager->get_field_by_shortname($data['shortname'],
                                                              $this->editing ? $data['fieldid'] : null);
            if ($existing) {
                $errors['shortname'] = get_string('error_field_name_exists', 'mod_casestudy');
            }
        }

        // Let the field type class validate its parameters
        if ($this->field) {
            $fielderrors = $this->field->validate_config_data($data);
            $errors = array_merge($errors, $fielderrors);
        }

        return $errors;
    }

    /**
     * Get the field data for saving
     *
     * @param object $data Form data
     * @return object Field data ready for saving
     */
    public function get_field_data($data) {

        // Prepare basic field data
        $fielddata = new \stdClass();
        $fielddata->name = $data->name;
        $fielddata->shortname = $data->shortname;
        $fielddata->description = $data->description;
        $fielddata->type = $this->fieldtype;
        $fielddata->required = !empty($data->required) ? 1 : 0;
        $fielddata->category = !empty($data->category) ? 1 : 0;
        $fielddata->showlistview = !empty($data->showlistview) ? 1 : 0;

        // Let the field type class process its configuration
        if ($this->field) {
            $config = $this->field->process_config_form((array)$data);
            $this->field->set_field_params($fielddata, $config);
        }

        return $fielddata;
    }

    /**
     * Set form defaults from field data
     *
     * @param object $field Field data
     */
    public function set_field_defaults($field) {
        $defaults = (array) $field;

        // Let the field type class set its parameter defaults
        if ($this->field && $field) {
            $this->field->set_param_form_defaults($defaults);
        }

        $this->set_data($defaults);
    }
}
