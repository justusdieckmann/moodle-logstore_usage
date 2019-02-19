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
 * Standard log store tests.
 *
 * @package    logstore_usage
 * @copyright  2014 Petr Skoda {@link http://skodak.org/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/event.php');
require_once(__DIR__ . '/fixtures/restore_hack.php');

/**
 * Class logstore_usage_store_testcase
 *
 * @group logstore_usage
 */
class logstore_usage_store_testcase extends advanced_testcase {
    /**
     * @var bool Determine if we disabled the GC, so it can be re-enabled in tearDown.
     */
    private $wedisabledgc = false;

    public function test_log_writing() {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits till the transaction gets committed.

        $this->setAdminUser();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();
        $module1 = $this->getDataGenerator()->create_module('resource', array('course' => $course1));
        $course2 = $this->getDataGenerator()->create_course();
        $module2 = $this->getDataGenerator()->create_module('resource', array('course' => $course2));

        // Enable logging plugin.
        set_config('enabled_stores', 'logstore_usage', 'tool_log');
        set_config('buffersize', 0, 'logstore_usage');
        set_config('logguests', 1, 'logstore_usage');
        set_config('courses', $course1->id . "," . $course2->id, "logstore_usage");
        $manager = get_log_manager(true);

        $logs = $DB->get_records('logstore_usage_log', array(), 'id ASC');
        $this->assertCount(0, $logs);

        $this->setCurrentTimeStart();

        $this->setUser(0);

        $eventparams = array(
                'context' => context_module::instance($module1->cmid),
                'objectid' => $module1->id
        );
        $e = \mod_resource\event\course_module_viewed::create($eventparams);
        $e->trigger();
        $this->assertEquals(0, $DB->count_records('logstore_usage_log'));

        $this->setUser($user1);
        $e = \mod_resource\event\course_module_viewed::create($eventparams);
        $e->trigger();
        $logs = $DB->get_records('logstore_usage_log', array(), 'id ASC');
        $this->assertCount(1, $logs);

        $log1 = reset($logs);
        unset($log1->id);
        $expected = array(
                'objecttable' => $e->objecttable,
                'objectid' => $e->objectid,
                'contextid' => $e->contextid,
                'userid' => $e->userid,
                'courseid' => $e->courseid,
                'amount' => '1',
                'daycreated' => date('j', $e->timecreated),
                'monthcreated' => date('n', $e->timecreated),
                'yearcreated' => date('Y', $e->timecreated),
        );
        $this->assertEquals($expected, (array) $log1);

        $this->setUser($user1);
        $e = \mod_resource\event\course_module_viewed::create($eventparams);
        $e->trigger();
        $logs = $DB->get_records('logstore_usage_log', array(), 'id ASC');
        $this->assertCount(1, $logs);
        $log1 = reset($logs);
        unset($log1->id);
        $expected['amount'] = '2';
        $this->assertEquals($expected, (array) $log1);

        $eventparams2 = array(
                'context' => context_module::instance($module2->cmid),
                'objectid' => $module2->id
        );
        $e = \mod_resource\event\course_module_viewed::create($eventparams2);
        $e->trigger();
        $this->assertEquals(2, $DB->count_records('logstore_usage_log'));

        $this->setUser($user2);
        $e = \mod_resource\event\course_module_viewed::create($eventparams);
        $e->trigger();
        $this->assertEquals(3, $DB->count_records('logstore_usage_log'));

    }

    /**
     * Verify that gc disabling works
     */
    public function test_gc_enabled_as_expected() {
        if (!gc_enabled()) {
            $this->markTestSkipped('Garbage collector (gc) is globally disabled.');
        }

        $this->disable_gc();
        $this->assertTrue($this->wedisabledgc);
        $this->assertFalse(gc_enabled());
    }

    /**
     * Disable the garbage collector if it's enabled to ensure we don't adjust memory statistics.
     */
    private function disable_gc() {
        if (gc_enabled()) {
            $this->wedisabledgc = true;
            gc_disable();
        }
    }

    /**
     * Reset any garbage collector changes to the previous state at the end of the test.
     */
    public function tearDown() {
        if ($this->wedisabledgc) {
            gc_enable();
        }
        $this->wedisabledgc = false;
    }
}
