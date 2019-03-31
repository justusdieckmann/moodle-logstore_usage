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
 * Data provider tests.
 *
 * @package    logstore_usage
 * @copyright  2019 Justus Dieckmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use logstore_usage\task\cleanup_task;

defined('MOODLE_INTERNAL') || die();

/**
 * Data provider testcase class.
 *
 * @package    logstore_usage
 * @copyright  2019 Justus Dieckmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group logstore_usage
 */
class logstore_usage_cleanup_test extends advanced_testcase {

    public function test_log_cleanup() {
        global $DB;
        $this->resetAfterTest();

        set_config('enabled_stores', 'logstore_usage', 'tool_log');
        set_config('loglifetime', 1, "logstore_usage");

        $records = [];
        $date = new DateTime("now", core_date::get_server_timezone_object());

        for ($i = 0; $i < 5; $i++) {
            $records[] = array('objecttable' => 'foo',
                'objectid' => 10,
                'contextid' => 20,
                'userid' => 0,
                'courseid' => 1,
                'amount' => 1,
                'daycreated' => $date->format('d'),
                'monthcreated' => $date->format('m'),
                'yearcreated' => $date->format('Y')
            );
            $date->sub(new DateInterval("P1D"));
        }

        $DB->insert_records("logstore_usage_log", $records);
        $this->assertEquals(5, $DB->count_records("logstore_usage_log"));
        $this->run_cron();

        /* There should be logs of today and yesterday. If loglifetime is 1 day,
         * logs should be kept at least 1 day. (If event is logged a bit before midnight,
         * it should no be deleted if cron runs shortly after midnight).
         */
        $this->assertEquals(2, $DB->count_records("logstore_usage_log"));
    }

    /**
     * Creates a cron task and executes it.
     */
    private function run_cron() {
        $task = new cleanup_task();
        $this->setAdminUser();
        $task->execute();
    }

}