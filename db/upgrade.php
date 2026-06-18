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

defined('MOODLE_INTERNAL') || die();

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

    return true;
}
