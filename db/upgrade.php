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
 * Case Study module upgrade script
 *
 * @package    mod_casestudy
 * @copyright  © Skin Cancer College Australasia
 * @license    Proprietary — Skin Cancer College Australasia, all rights reserved
 */

/**
 * Execute mod_casestudy upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_casestudy_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026020201) {
        // Define field formtemplate to be added to casestudy.
        $table = new xmldb_table('casestudy');
        $field = new xmldb_field('formtemplate', XMLDB_TYPE_TEXT, null, null, null, null, null, 'singletemplate');

        // Conditionally launch add field formtemplate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Casestudy savepoint reached.
        upgrade_mod_savepoint(true, 2026020201, 'casestudy');
    }

    if ($oldversion < 2026070320) {
        // Widen casestudy_grades.grade to a decimal column so fractional point grades (e.g. 7.5)
        // are stored without truncation. Existing integer values are preserved by the widening.
        $table = new xmldb_table('casestudy_grades');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null, 'feedbackformat');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        // Casestudy savepoint reached.
        upgrade_mod_savepoint(true, 2026070320, 'casestudy');
    }

    if ($oldversion < 2026070340) {
        // Re-run the formtemplate column addition for sites coming from the vendor stream.
        // The vendor lineage reached version 2026070201 without ever having this column, so the
        // original 2026020201 step above is skipped on those sites (their installed version is
        // already higher) and the column silently never gets created — after which saving a form
        // template, or restoring a backup that contains one, fails with a DB write error.
        // field_exists makes this a no-op on sites that already ran the original step.
        $table = new xmldb_table('casestudy');
        $field = new xmldb_field('formtemplate', XMLDB_TYPE_TEXT, null, null, null, null, null, 'singletemplate');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Casestudy savepoint reached.
        upgrade_mod_savepoint(true, 2026070340, 'casestudy');
    }

    if ($oldversion < 2026091801) {
        // Add the per-activity suppressnotifications flag. Default 1 so existing activities keep
        // suppressing notifications for hidden/completed courses, matching the shipped behaviour.
        $table = new xmldb_table('casestudy');
        $field = new xmldb_field(
            'suppressnotifications',
            XMLDB_TYPE_INTEGER,
            '2',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'notifystudentdefault'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Casestudy savepoint reached.
        upgrade_mod_savepoint(true, 2026091801, 'casestudy');
    }

    return true;
}
