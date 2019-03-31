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
 * @copyright  2014 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_usage\task;

use core\task\scheduled_task;
use core_date;
use DateInterval;
use DateTime;

defined('MOODLE_INTERNAL') || die();

class cleanup_task extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcleanup', 'logstore_usage');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $DB;

        $loglifetime = (int)get_config('logstore_usage', 'loglifetime');

        if (empty($loglifetime) || $loglifetime < 0) {
            return;
        }

        $minloglifetimedt = new DateTime('now', core_date::get_server_timezone_object());
        $minloglifetimedt->setTimestamp(time() - ($loglifetime * 3600 * 24));
        $minloglifetime = (int) $minloglifetimedt->format("Ymd");
        $lifetimep = array($minloglifetime);
        $start = time();

        while ($min = $DB->get_field_select("logstore_usage_log",
            "MIN(yearcreated * 10000 + monthcreated * 100 + daycreated)",
            "yearcreated * 10000 + monthcreated * 100 + daycreated < ?", $lifetimep)) {
            // Break this down into chunks to avoid transaction for too long and generally thrashing database.
            // Experiments suggest deleting one day takes up to a few seconds; probably a reasonable chunk size usually.
            // If the cleanup has just been enabled, it might take e.g a month to clean the years of logs.
            $mindt = DateTime::createFromFormat("Ymd", $min, core_date::get_server_timezone_object());
            $mindt->add(new DateInterval('P1D'));
            $params = array(min($mindt->format("Ymd"), $minloglifetime));
            $DB->delete_records_select("logstore_usage_log",
                "yearcreated * 10000 + monthcreated * 100 + daycreated < ?", $params);
            if (time() > $start + 300) {
                // Do not churn on log deletion for too long each run.
                break;
            }
        }

        mtrace(" Deleted old log records from standard store.");
    }
}
