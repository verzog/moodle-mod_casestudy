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
 * Field Manager class for Case Study activity
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

namespace mod_casestudy\local;

/**
 * Manages CRUD operations for case study fields
 */
class field_manager {
    /** @var int The case study activity ID */
    private $casestudyid;

    /** @var array Supported field types */
    const SUPPORTED_TYPES = [
        'text',
        'textarea',
        'richtext',
        'dropdown',
        'radio',
        'checkbox',
        'file',
        'sectionheading',
    ];

    protected static $instance = null;

    /**
     * Constructor
     *
     * @param int $casestudyid The case study activity ID
     */
    protected function __construct($casestudyid) {
        $this->casestudyid = $casestudyid;
    }


    public static function instance($casestudyid) {

        if (self::$instance === null && self::$instance?->casestudyid !== $casestudyid) {
            self::$instance = new self($casestudyid);
        }

        return self::$instance;
    }

    /**
     * Create a new field
     *
     * @param string $type Field type
     * @param string $name Field name
     * @param string $description Field description
     * @param array $config Field configuration
     * @return int|false Field ID on success, false on failure
     */
    public function create_field($type, $config = []) {
        global $DB;

        if (!in_array($type, self::SUPPORTED_TYPES)) {
            throw new moodle_exception('error_unsupported_field_type', 'mod_casestudy', '', $type);
        }

        // Get the next sort order
        $sortorder = $this->get_next_sortorder();

        $field = (object) $config;
        $field->casestudyid = $this->casestudyid;
        $field->shortname = $config->shortname ?? '';
        $field->type = $type;
        $field->sortorder = $sortorder;
        $field->timecreated = time();
        $field->timemodified = time();

        return $DB->insert_record('casestudy_fields', $field);
    }

    /**
     * Update an existing field
     *
     * @param int $fieldid Field ID
     * @param object $data Updated field data
     * @return bool Success
     */
    public function update_field($fieldid, $data) {
        global $DB;

        $field = $this->get_field($fieldid);
        if (!$field) {
            return false;
        }

        $data->timemodified = time();

        return $DB->update_record('casestudy_fields', $data);
    }

    /**
     * Delete a field
     *
     * @param int $fieldid Field ID
     * @return bool Success
     */
    public function delete_field($fieldid) {
        global $DB;

        // Check if field exists and belongs to this case study
        $field = $this->get_field($fieldid);
        if (!$field) {
            return false;
        }

        // Delete field content from submissions
        $DB->delete_records('casestudy_content', ['fieldid' => $fieldid]);

        // Delete the field itself
        return $DB->delete_records('casestudy_fields', ['id' => $fieldid]);
    }

    /**
     * Get all fields for this case study
     *
     * @return array Array of field objects
     */
    public function get_fields() {
        global $DB;

        return $DB->get_records('casestudy_fields', ['casestudyid' => $this->casestudyid], 'sortorder ASC');
    }


    public function get_context() {
        $cm = get_coursemodule_from_instance('casestudy', $this->casestudyid);
        return \context_module::instance($cm->id);
    }

    /**
     * Get a specific field
     *
     * @param int $fieldid Field ID
     * @return object|false Field object or false if not found
     */
    public function get_field($fieldid) {
        global $DB;

        return $DB->get_record('casestudy_fields', [
            'id' => $fieldid,
            'casestudyid' => $this->casestudyid,
        ]);
    }

    /**
     * Get field by name (for uniqueness checking)
     *
     * @param string $name Field name
     * @param int $excludeid Field ID to exclude (for editing)
     * @return object|false Field record or false if not found
     */
    public function get_field_by_shortname($name, $excludeid = null) {
        global $DB;

        $sql = 'SELECT * FROM {casestudy_fields} WHERE casestudyid = ? AND shortname = ?';
        $params = [$this->casestudyid, $name];

        if ($excludeid) {
            $sql .= ' AND id != ?';
            $params[] = $excludeid;
        }

        return $DB->get_record_sql($sql, $params);
    }

    /**
     * Get the next sort order for new fields
     *
     * @return int Next sort order value
     */
    public function get_next_sort_order() {
        global $DB;

        // Ensure sort orders are contiguous before getting the next.
        $fields = $this->get_fields();
        $this->reorder_fields(array_column($fields, 'id'));

        $maxorder = $DB->get_field(
            'casestudy_fields',
            'MAX(sortorder)',
            ['casestudyid' => $this->casestudyid]
        );

        return ($maxorder ? $maxorder : 0) + 1;
    }

    /**
     * Move field up in sort order
     *
     * @param int $fieldid Field ID to move up
     * @return bool Success
     */
    public function move_field_up($fieldid) {
        global $DB;

        $field = $this->get_field($fieldid);
        if (!$field || $field->sortorder <= 1) {
            return false;
        }

        // Find the field above this one.
        $prevfield = $DB->get_record('casestudy_fields', [
            'casestudyid' => $this->casestudyid,
            'sortorder' => $field->sortorder - 1,
        ]);

        if ($prevfield) {
            // Swap sort orders.
            $DB->set_field('casestudy_fields', 'sortorder', $field->sortorder, ['id' => $prevfield->id]);
            $DB->set_field('casestudy_fields', 'sortorder', $prevfield->sortorder, ['id' => $field->id]);
        }

        return true;
    }

    /**
     * Move field down in sort order
     *
     * @param int $fieldid Field ID to move down
     * @return bool Success
     */
    public function move_field_down($fieldid) {
        global $DB;

        $field = $this->get_field($fieldid);
        if (!$field) {
            return false;
        }

        $maxorder = $DB->get_field(
            'casestudy_fields',
            'MAX(sortorder)',
            ['casestudyid' => $this->casestudyid]
        );

        if ($field->sortorder >= $maxorder) {
            return false;
        }

        // Find the field below this one.
        $nextfield = $DB->get_record('casestudy_fields', [
            'casestudyid' => $this->casestudyid,
            'sortorder' => $field->sortorder + 1,
        ]);

        if ($nextfield) {
            // Swap sort orders.
            $DB->set_field('casestudy_fields', 'sortorder', $field->sortorder, ['id' => $nextfield->id]);
            $DB->set_field('casestudy_fields', 'sortorder', $nextfield->sortorder, ['id' => $field->id]);
        }

        return true;
    }

    /**
     * Reorder fields
     *
     * @param array $fieldids Array of field IDs in new order
     * @return bool Success
     */
    public function reorder_fields($fieldids) {
        global $DB;

        $sortorder = 1;
        foreach ($fieldids as $fieldid) {
            $DB->set_field('casestudy_fields', 'sortorder', $sortorder, [
                'id' => $fieldid,
                'casestudyid' => $this->casestudyid,
            ]);
            $sortorder++;
        }

        return true;
    }

    /**
     * Get fields marked as categories
     *
     * @return array Array of category fields
     */
    public function get_category_fields() {
        global $DB;

        return $DB->get_records('casestudy_fields', [
            'casestudyid' => $this->casestudyid,
            'category' => 1,
        ], 'sortorder ASC');
    }

    /**
     * Get field type instance for a given type
     *
     * @param string $type Field type
     * @param object $field Field object (optional)
     * @return base_field Field type instance
     * @throws moodle_exception If field type not found
     */
    public function get_field_type_instance($type, $field = null) {
        $classname = "\\mod_casestudy\\field_types\\{$type}_field";

        if (!class_exists($classname)) {
            throw new moodle_exception('error_field_type_not_found', 'mod_casestudy', '', $type);
        }

        return new $classname($field);
    }

    /**
     * Get field type class without instantiation
     *
     * @param string $type Field type
     * @return object|false Field class instance or false if not found
     */
    public function get_field_type_class($type, $fieldid = null) {
        $classname = "\\mod_casestudy\\local\\field_types\\{$type}_field";

        if (!class_exists($classname)) {
            return false;
        }

        return new $classname($this->casestudyid, $fieldid);
    }

    /**
     * Get the next sort order for a new field
     *
     * @return int Next sort order
     */
    private function get_next_sortorder() {
        global $DB;

        $max = $DB->get_field('casestudy_fields', 'MAX(sortorder)', [
            'casestudyid' => $this->casestudyid,
        ]);

        return $max ? $max + 1 : 1;
    }

    /**
     * Clone an existing field
     *
     * @param int $fieldid Field ID to clone
     * @return int|false New field ID on success, false on failure
     */
    public function clone_field($fieldid) {
        global $DB;

        // Get the original field
        $originalfield = $this->get_field($fieldid);
        if (!$originalfield) {
            return false;
        }

        // Create a clone of the field
        $clonefield = clone $originalfield;

        // Remove the ID and update timestamps
        unset($clonefield->id);
        $clonefield->name = $clonefield->name . ' (Copy)';
        // Generate a unique shortname by appending a number if needed
        $baseshortname = $clonefield->shortname . '_copy';
        $shortname = $baseshortname;
        $counter = 1;

        // Check if shortname exists and increment until we find a unique one
        while (
            $DB->record_exists('casestudy_fields', [
            'casestudyid' => $clonefield->casestudyid,
            'shortname' => $shortname,
            ])
        ) {
            $counter++;
            $shortname = $baseshortname . $counter;
        }

        $clonefield->shortname = $shortname;
        $clonefield->sortorder = $this->get_next_sortorder();
        $clonefield->timecreated = time();
        $clonefield->timemodified = time();

        // Insert the cloned field
        return $DB->insert_record('casestudy_fields', $clonefield);
    }

    /**
     * Validate field configuration
     *
     * @param string $type Field type
     * @param array $config Field configuration
     * @return array Array of validation errors
     */
    public function validate_field_config($type, $config) {
        $errors = [];

        switch ($type) {
            case 'dropdown':
            case 'radio':
            case 'checkbox':
                if (empty($config['options']) || !is_array($config['options'])) {
                    $errors[] = get_string('error_options_required', 'mod_casestudy');
                } else {
                    foreach ($config['options'] as $option) {
                        if (empty(trim($option))) {
                            $errors[] = get_string('error_empty_option', 'mod_casestudy');
                            break;
                        }
                    }
                }
                break;

            case 'file':
                if (isset($config['maxfilesize']) && $config['maxfilesize'] <= 0) {
                    $errors[] = get_string('error_invalid_filesize', 'mod_casestudy');
                }
                if (isset($config['minfiles']) && $config['minfiles'] < 0) {
                    $errors[] = get_string('error_invalid_minfiles', 'mod_casestudy');
                }
                if (isset($config['maxfiles']) && $config['maxfiles'] <= 0) {
                    $errors[] = get_string('error_invalid_maxfiles', 'mod_casestudy');
                }
                if (
                    isset($config['minfiles']) && isset($config['maxfiles']) &&
                    $config['minfiles'] > $config['maxfiles']
                ) {
                    $errors[] = get_string('error_minfiles_greater_maxfiles', 'mod_casestudy');
                }
                break;
        }

        return $errors;
    }
}
