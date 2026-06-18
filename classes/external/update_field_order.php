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
 * External web service function for updating case study field order
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_casestudy\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;
use mod_casestudy\local\field_manager;

/**
 * External function for updating field order
 */
class update_field_order extends external_api {

    /**
     * Describes the parameters for update_field_order
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'fieldid' => new external_value(PARAM_INT, 'Field ID to move'),
            'newposition' => new external_value(PARAM_INT, 'New position (1-based index)')
        ]);
    }

    /**
     * Update field order
     *
     * @param int $cmid Course module ID
     * @param int $fieldid Field ID to move
     * @param int $newposition New position (1-based)
     * @return array Result
     */
    public static function execute($cmid, $fieldid, $newposition) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'fieldid' => $fieldid,
            'newposition' => $newposition
        ]);

        // Get course module and context.
        $cm = get_coursemodule_from_id('casestudy', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Check capability.
        require_capability('mod/casestudy:managefields', $context);

        // Get the field and validate it belongs to this case study.
        $field = $DB->get_record('casestudy_fields', [
            'id' => $params['fieldid'],
            'casestudyid' => $cm->instance
        ]);

        if (!$field) {
            throw new \moodle_exception('invalidfieldid', 'mod_casestudy');
        }

        // Validate new position
        $maxorder = $DB->get_field('casestudy_fields', 'MAX(sortorder)',
            ['casestudyid' => $cm->instance]);

        if ($params['newposition'] < 1 || $params['newposition'] > $maxorder) {
            throw new \moodle_exception('invalidposition', 'mod_casestudy');
        }

        // Get field manager instance
        $fieldmanager = field_manager::instance($cm->instance);

        // Update field order
        $success = self::reorder_field($fieldmanager, $field, $params['newposition']);

        return [
            'success' => $success,
            'message' => $success ? get_string('fieldorderupdated', 'mod_casestudy') : get_string('fieldorderupdatefailed', 'mod_casestudy')
        ];
    }

    /**
     * Reorder field to new position
     *
     * @param field_manager $fieldmanager Field manager instance
     * @param object $field Field to move
     * @param int $newposition New position
     * @return bool Success
     */
    private static function reorder_field($fieldmanager, $field, $newposition) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        try {
            $currentposition = $field->sortorder;

            if ($currentposition == $newposition) {
                $transaction->allow_commit();
                return true; // No change needed.
            }

            // Get all fields for this case study ordered by sort order.
            $fields = $DB->get_records('casestudy_fields',
                ['casestudyid' => $field->casestudyid],
                'sortorder ASC'
            );

            // Convert to array indexed by current position.
            $fieldsarray = array_values($fields);

            $movingfield = array_splice($fieldsarray, $currentposition - 1, 1)[0];
            array_splice($fieldsarray, $newposition - 1, 0, [$movingfield]);

            // Update sort order for all fields.
            foreach ($fieldsarray as $index => $fieldobj) {
                $neworder = $index + 1;
                if ($fieldobj->sortorder != $neworder) {
                    $DB->set_field('casestudy_fields', 'sortorder', $neworder, ['id' => $fieldobj->id]);
                    $DB->set_field('casestudy_fields', 'timemodified', time(), ['id' => $fieldobj->id]);
                }
            }

            $transaction->allow_commit();

            return true;
        } catch (\Exception $e) {
            $transaction->rollback($e);

            return false;
        }
    }

    /**
     * Describes the return value for update_field_order
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'message' => new external_value(PARAM_TEXT, 'Success or error message')
        ]);
    }
}