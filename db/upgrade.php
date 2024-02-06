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
 * Standard log store upgrade.
 *
 * @package    logstore_usage
 * @copyright  2019 Justus Dieckmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_logstore_usage_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024020200) {

        // Define table logstore_usage_courses to be created.
        $table = new xmldb_table('logstore_usage_courses');

        // Adding fields to table logstore_usage_courses.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeuntil', XMLDB_TYPE_INTEGER, '11', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table logstore_usage_courses.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid_frk', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for logstore_usage_courses.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $prevcourseids = explode(',', get_config('logstore_usage', 'courses'));
        list($sql, $params) = $DB->get_in_or_equal($prevcourseids);
        $courseids = $DB->get_fieldset_select('course', 'id', "id $sql", $params);
        foreach ($courseids as $courseid) {
            $entry = new \logstore_usage\course_entry();
            $entry->set('courseid', $courseid);
            $entry->save();
        }

        unset_config('courses', 'logstore_usage');

        // Usage savepoint reached.
        upgrade_plugin_savepoint(true, 2024020200, 'logstore', 'usage');
    }


    return true;
}
