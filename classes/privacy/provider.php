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
 * Data provider.
 *
 * @package    logstore_usage
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_usage\privacy;
defined('MOODLE_INTERNAL') || die();

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

/**
 * Data provider class.
 *
 * @package    logstore_usage
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \tool_log\local\privacy\logstore_provider,
        \tool_log\local\privacy\logstore_userlist_provider {

    use \tool_log\local\privacy\moodle_database_export_and_delete;

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('logstore_usage_log', [
                'contextid' => 'privacy:metadata:log:contextid',
                'userid' => 'privacy:metadata:log:userid',
                'courseid' => 'privacy:metadata:log:courseid',
                'amount' => 'privacy:metadata:log:amount',
                'daycreated' => 'privacy:metadata:log:daycreated',
                'monthcreated' => 'privacy:metadata:log:monthcreated',
                'yearcreated' => 'privacy:metadata:log:yearcreated',
        ], 'privacy:metadata:log');
        return $collection;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        list($db, $table) = static::get_database_and_table();
        if (!$db || !$table) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        list($insql, $inparams) = $db->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "(userid = :userid1) AND contextid $insql";
        $params = array_merge($inparams, [
                'userid1' => $userid
        ]);

        $path = static::get_export_subcontext();
        $flush = function($lastcontextid, $data) use ($path) {
            $context = context::instance_by_id($lastcontextid);
            writer::with_context($context)->export_data($path, (object) ['logs' => $data]);
        };

        $lastcontextid = null;
        $data = [];
        $recordset = $db->get_recordset_select($table, $sql, $params, 'contextid, yearcreated,  id');
        foreach ($recordset as $record) {
            if ($lastcontextid && $lastcontextid != $record->contextid) {
                $flush($lastcontextid, $data);
                $data = [];
            }
            $data[] = static::transform_standard_log_record_for_userid($record, $userid);
            $lastcontextid = $record->contextid;
        }
        if ($lastcontextid) {
            $flush($lastcontextid, $data);
        }
        $recordset->close();
    }

    /**
     * Transform a standard log record for a user.
     *
     * @param object $record The record.
     * @param int $userid The user ID.
     * @return array
     */
    public static function transform_standard_log_record_for_userid($record, $userid) {
        $context = \context::instance_by_id($record->contextid, IGNORE_MISSING);
        $name = $context->get_context_name(false);

        $data = [
                'name' => $name,
                'year' => $record->yearcreated,
                'month' => $record->monthcreated,
                'day' => $record->daycreated,
                'amount' => $record->amount,
                'authorid' => transform::user($record->userid),
                'author_of_the_action_was_you' => transform::yesno(true)
        ];

        return $data;
    }

    /**
     * Add contexts that contain user information for the specified user.
     *
     * @param contextlist $contextlist The contextlist to add the contexts to.
     * @param int $userid The user to find the contexts for.
     * @return void
     */
    public static function add_contexts_for_userid(contextlist $contextlist, $userid) {
        $sql = "
            SELECT l.contextid
              FROM {logstore_usage_log} l
             WHERE l.userid = :userid1";
        $contextlist->add_from_sql($sql, [
                'userid1' => $userid,
        ]);
    }

    /**
     * Add user IDs that contain user information for the specified context.
     *
     * @param \core_privacy\local\request\userlist $userlist The userlist to add the users to.
     * @return void
     */
    public static function add_userids_for_context(\core_privacy\local\request\userlist $userlist) {
        $params = ['contextid' => $userlist->get_context()->id];
        $sql = "SELECT userid
                  FROM {logstore_usage_log}
                 WHERE contextid = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Get the database object.
     *
     * @return array Containing moodle_database, string, or null values.
     */
    protected static function get_database_and_table() {
        global $DB;
        return [$DB, 'logstore_usage_log'];
    }

    /**
     * Get the path to export the logs to.
     *
     * @return array
     */
    protected static function get_export_subcontext() {
        return [get_string('privacy:path:logs', 'tool_log'), get_string('pluginname', 'logstore_usage')];
    }
}
