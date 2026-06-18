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
 * Case Study module upgrade script
 *
 * @package    mod_casestudy
 * @copyright  2025 Skin Cancer College Australasia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
