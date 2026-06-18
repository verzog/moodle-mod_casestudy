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
 * Template manager class for case study
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy;

use mod_casestudy\local\casestudy;
use stdClass;

class template_manager {
    /**
     * List of supported template types.
     */
    const TEMPLATES_LIST = ['singletemplate', 'formtemplate', 'csstemplate'];

    /**
     * The case study instance.
     * @var stdClass $casestudy The case study instance
     */
    private $casestudy;

    /**
     * The course module instance.
     * @var cm_info $cm The course module instance
     */
    private $cm;

    /**
     * The context instance.
     * @var context $context The context instance
     */
    private $context;

    /**
     * Constructor
     *
     * @param stdClass $casestudy The case study instance
     * @param cm_info $cm The course module instance
     * @param context $context The context instance
     *
     * @throws \coding_exception
     */
    public function __construct(casestudy $casestudy, $cm, $context) {

        $this->casestudy = $casestudy;
        $this->cm = $cm;
        $this->context = $context;
    }

    /**
     * Get the case study instance.
     *
     * @return stdClass The case study record
     */
    public function get_casestudy() {
        return $this->casestudy;
    }

    /**
     * Get the course module of the case study instance.
     *
     * @return cm_info The course module instance
     */
    public function get_cm() {
        return $this->cm;
    }

    /**
     * Get the context of the case study instance.
     *
     * @return context The context instance
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Get the template content. If not set, return the default template.
     *
     * @param string $type The type of template to retrieve ('singletemplate' or 'csstemplate')
     * @return string The template content
     */
    public function get_template($type = 'singletemplate') {
        global $DB;

        $casestudy = $DB->get_record('casestudy', ['id' => $this->casestudy->casestudyid], $type);

        if (empty($casestudy->$type)) {
            return $this->get_default_template($type);
        }

        return $casestudy->$type;
    }

    /**
     * Get the default CSS template.
     *
     * @return string The default CSS template content
     */
    public function update_template($type, $content) {
        global $DB;

        $record = new stdClass();
        $record->id = $this->casestudy->casestudyid;
        $record->$type = $content;
        $record->timemodified = time();

        $DB->update_record('casestudy', $record);

        return true;
    }

    /**
     * Get the default CSS template.
     *
     * @return string The default CSS template content
     */
    public function reset_template($type = 'singletemplate') {
        global $DB;

        $defaulttemplate = $this->get_default_template($type);

        $record = new stdClass();
        $record->id = $this->casestudy->casestudyid;
        $record->$type = $defaulttemplate;
        $record->timemodified = time();

        $DB->update_record('casestudy', $record);

        return true;
    }

    /**
     * Reset all templates to their default values.
     *
     * @return bool True on success
     */
    public function reset_all_templates() {
        foreach (self::TEMPLATES_LIST as $templatetype) {
            $this->reset_template($templatetype);
        }

        return true;
    }

    /**
     * Get the default template for the specified type.
     *
     * @param string $type The type of template ('singletemplate', 'formtemplate', or 'csstemplate')
     * @return string The default template content
     */
    private function get_default_template($type) {
        $fields = $this->get_fields();
        $template = new \mod_casestudy\template($this->casestudy, $this->cm, $this->context);

        switch ($type) {
            case 'csstemplate':
                return $this->get_default_css();

            case 'formtemplate':
                return $template->get_default_form_template($fields);

            case 'singletemplate':
            default:
                return $template->get_default_template($fields);
        }
    }

    /**
     * Get the default CSS template.
     *
     * @return string The default CSS template content
     */
    private function get_default_css(): string {
        return '/* Custom CSS for Case Study submissions */
            .casestudy-submission-single {
                padding: 1rem;
            }

            .casestudy-section-heading {
                border-bottom: 2px solid #dee2e6;
                padding-bottom: 0.5rem;
                margin-top: 1.5rem;
            }

            .field-wrapper {
                margin-bottom: 1rem;
            }

            .field-label {
                font-weight: bold;
                margin-bottom: 0.25rem;
            }

            .field-value {
                padding: 0.5rem;
                background-color: #f8f9fa;
                border-radius: 0.25rem;
            }
        ';
    }

    /**
     * Get available tags for the specified template type.
     *
     * @param string $type Template type
     * @return array Available tags
     */
    public function get_available_tags(string $type): array {
        $fields = $this->get_fields();
        $template = new \mod_casestudy\template($this->casestudy, $this->cm, $this->context);

        if ($type === 'formtemplate') {
            return $template->get_available_form_tags($fields);
        }

        return $template->get_available_tags($fields);
    }

    /**
     * Check if there are any fields configured.
     *
     * @return bool True if fields exist
     */
    public function has_fields() {
        return !empty($this->get_fields());
    }

    /**
     * Get all fields for this case study.
     *
     * @return array Array of field objects
     */
    public function get_fields() {
        $manager = \mod_casestudy\local\field_manager::instance($this->casestudy->casestudyid);
        $fields = $manager->get_fields();

        return $fields;
    }
}
