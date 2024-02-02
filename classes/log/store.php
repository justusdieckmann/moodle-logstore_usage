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
 * Standard log reader/writer.
 *
 * @package    logstore_usage
 * @copyright  2019 Justus Dieckmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_usage\log;

use Box\Spout\Common\Exception\EncodingConversionException;
use logstore_usage\cache_util;

defined('MOODLE_INTERNAL') || die();

class store implements \tool_log\log\writer {
    use \tool_log\helper\store,
            \tool_log\helper\buffered_writer,
            \tool_log\helper\reader;

    public function __construct(\tool_log\log\manager $manager) {
        $this->helper_setup($manager);
        // Log everything before setting is saved for the first time.
    }

    /**
     * Should the event be ignored (== not logged)?
     *
     * @param \core\event\base $event
     * @return bool
     */
    protected function is_event_ignored(\core\event\base $event) {
        if ((!CLI_SCRIPT or PHPUNIT_TEST)) {
            // Always log inside CLI scripts because we do not login there.
            if (!isloggedin() or isguestuser()) {
                return true;
            }
        }

        $data = $event->get_data();

        if (!(isset($data['courseid']) && $this->is_course_activated($data['courseid']))) {
            return true;
        }

        if (!$this->should_listen_for_event($data['eventname'])) {
            return true;
        }

        return false;

    }

    protected function should_listen_for_event($eventname) {
        $end = '\event\course_module_viewed';
        if (substr_compare($eventname, $end, strlen($eventname) - strlen($end), strlen($end)) === 0) {
            return true;
        }

        if (PHPUNIT_TEST && $eventname == '\logstore_usage\event\unittest_view') {
            return true;
        }

        return false;
    }

    protected function is_course_activated($courseid) {
        return cache_util::has_course($courseid);
    }

    /**
     * Finally store the events into the database.
     *
     * @param array $evententries raw event data
     */
    protected function insert_event_entries($evententries) {
        global $DB;

        foreach ($evententries as $k => $v) {
            // Realuserid is not present in is_event_ignored.
            if (isset($v['realuserid']) && $v['realuserid'] !== '') {
                continue;
            }
            $timestamp = $v['timecreated'];
            $dt = new \DateTime("@$timestamp");
            $dt->setTimezone(\core_date::get_server_timezone_object());
            $day = $dt->format('j');
            $month = $dt->format('n');
            $year = $dt->format('Y');

            $conditions = array(
                    'daycreated' => $day,
                    'monthcreated' => $month,
                    'yearcreated' => $year,
                    'userid' => $v['userid'],
                    'contextid' => $v['contextid']
            );

            $transaction = $DB->start_delegated_transaction();
            try {
                if ($DB->record_exists("logstore_usage_log", $conditions)) {
                    $sql = "UPDATE {logstore_usage_log}
                           SET amount = amount + 1
                         WHERE daycreated = :daycreated
                           AND monthcreated = :monthcreated
                           AND yearcreated = :yearcreated
                           AND userid = :userid
                           AND contextid = :contextid";

                    $DB->execute($sql, $conditions);
                } else {
                    $obj = array(
                            'objecttable' => $v['objecttable'],
                            'objectid' => $v['objectid'],
                            'contextid' => $v['contextid'],
                            'userid' => $v['userid'],
                            'courseid' => $v['courseid'],
                            'daycreated' => $day,
                            'monthcreated' => $month,
                            'yearcreated' => $year,
                            'amount' => 1
                    );
                    $DB->insert_record("logstore_usage_log", $obj);
                }
                $transaction->allow_commit();
            } catch (\Exception $e) {
                $transaction->rollback($e);
            }
        }
    }

    public function get_events_select($selectwhere, array $params, $sort, $limitfrom, $limitnum) {
        global $DB;

        $sort = self::tweak_sort_by_id($sort);

        $events = array();
        $records = $DB->get_recordset_select('logstore_usage_log', $selectwhere, $params, $sort, '*', $limitfrom, $limitnum);

        foreach ($records as $data) {
            if ($event = $this->get_log_event($data)) {
                $events[$data->id] = $event;
            }
        }

        $records->close();

        return $events;
    }

    /**
     * Fetch records using given criteria returning a Traversable object.
     *
     * Note that the traversable object contains a moodle_recordset, so
     * remember that is important that you call close() once you finish
     * using it.
     *
     * @param string $selectwhere
     * @param array $params
     * @param string $sort
     * @param int $limitfrom
     * @param int $limitnum
     * @return \core\dml\recordset_walk|\core\event\base[]
     */
    public function get_events_select_iterator($selectwhere, array $params, $sort, $limitfrom, $limitnum) {
        global $DB;

        $sort = self::tweak_sort_by_id($sort);

        $recordset = $DB->get_recordset_select('logstore_usage_log', $selectwhere, $params, $sort, '*', $limitfrom, $limitnum);

        return new \core\dml\recordset_walk($recordset, array($this, 'get_log_event'));
    }

    /**
     * Returns an event from the log data.
     *
     * @param stdClass $data Log data
     * @return \core\event\base
     */
    public function get_log_event($data) {

        $extra = array('origin' => $data->origin, 'ip' => $data->ip, 'realuserid' => $data->realuserid);
        $data = (array) $data;
        $id = $data['id'];
        $data['other'] = unserialize($data['other']);
        if ($data['other'] === false) {
            $data['other'] = array();
        }
        unset($data['origin']);
        unset($data['ip']);
        unset($data['realuserid']);
        unset($data['id']);

        if (!$event = \core\event\base::restore($data, $extra)) {
            return null;
        }

        return $event;
    }

    public function get_events_select_count($selectwhere, array $params) {
        global $DB;
        return $DB->count_records_select('logstore_usage_log', $selectwhere, $params);
    }

    public function get_internal_log_table_name() {
        return 'logstore_usage_log';
    }

    /**
     * Are the new events appearing in the reader?
     *
     * @return bool true means new log events are being added, false means no new data will be added
     */
    public function is_logging() {
        // Only enabled stpres are queried,
        // this means we can return true here unless store has some extra switch.
        return true;
    }
}
