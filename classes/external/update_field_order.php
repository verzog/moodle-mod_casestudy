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
 * External web service function for updating case study field order
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
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
            'newposition' => new external_value(PARAM_INT, 'New position (1-based index)'),
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
            'newposition' => $newposition,
        ]);

        // Get course module and context.
        $cm = get_coursemodule_from_id('casestudy', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Check capability.
        require_capability('mod/casestudy:managefields', $context);

        // Get the field and validate it belongs to this case study.
        $field = $DB->get_record('casestudy_fields', [
            'id' => $params['fieldid'],
            'casestudyid' => $cm->instance,
        ]);

        if (!$field) {
            throw new \moodle_exception('invalidfieldid', 'mod_casestudy');
        }

        // Validate new position
        $maxorder = $DB->get_field(
            'casestudy_fields',
            'MAX(sortorder)',
            ['casestudyid' => $cm->instance]
        );

        if ($params['newposition'] < 1 || $params['newposition'] > $maxorder) {
            throw new \moodle_exception('invalidposition', 'mod_casestudy');
        }

        // Get field manager instance
        $fieldmanager = field_manager::instance($cm->instance);

        // Update field order
        $success = self::reorder_field($fieldmanager, $field, $params['newposition']);

        return [
            'success' => $success,
            'message' => $success ? get_string('fieldorderupdated', 'mod_casestudy') : get_string('fieldorderupdatefailed', 'mod_casestudy'),
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
            $fields = $DB->get_records(
                'casestudy_fields',
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
            'message' => new external_value(PARAM_TEXT, 'Success or error message'),
        ]);
    }
}
