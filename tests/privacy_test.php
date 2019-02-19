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
 * @category   test
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;

use core_privacy\tests\provider_testcase;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use logstore_usage\privacy\provider;

require_once(__DIR__ . '/fixtures/event.php');

/**
 * Data provider testcase class.
 *
 * @package    logstore_usage
 * @category   test
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group logstore_usage
 */
class logstore_usage_privacy_testcase extends provider_testcase {

    public function setUp() {
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits till the transaction gets committed.
        set_config('enabled_stores', 'logstore_usage', 'tool_log');
        set_config('buffersize', 0, 'logstore_usage');
        set_config('logguests', 1, 'logstore_usage');
    }

    public function test_get_contexts_for_userid() {
        $admin = \core_user::get_user(2);
        $u1 = $this->getDataGenerator()->create_user();

        $c1 = $this->getDataGenerator()->create_course();
        $cm1 = $this->getDataGenerator()->create_module('url', ['course' => $c1]);
        $c2 = $this->getDataGenerator()->create_course();
        $cm2 = $this->getDataGenerator()->create_module('url', ['course' => $c2]);

        set_config("courses", $c1->id, "logstore_usage");

        $sysctx = context_system::instance();
        $c1ctx = context_course::instance($c1->id);
        $c2ctx = context_course::instance($c2->id);
        $cm1ctx = context_module::instance($cm1->cmid);
        $cm2ctx = context_module::instance($cm2->cmid);

        $this->enable_logging();
        $manager = get_log_manager(true);

        // User 1 is the author.
        $this->setUser($u1);
        $this->assert_contextlist_equals($this->get_contextlist_for_user($u1), []);
        $e = \logstore_usage\event\unittest_view::create(['context' => $cm1ctx]);
        $e->trigger();
        $e = \logstore_usage\event\unittest_view::create(['context' => $cm2ctx]);
        $e->trigger();
        $this->assert_contextlist_equals($this->get_contextlist_for_user($u1), [$cm1ctx]);
    }

    public function test_add_userids_for_context() {
        $admin = \core_user::get_user(2);
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        $u4 = $this->getDataGenerator()->create_user();

        $c1 = $this->getDataGenerator()->create_course();

        set_config("courses", $c1->id, "logstore_usage");

        $sysctx = context_system::instance();
        $c1ctx = context_course::instance($c1->id);

        $this->enable_logging();
        $manager = get_log_manager(true);

        $userlist = new \core_privacy\local\request\userlist($c1ctx, 'logstore_usage_log');
        $userids = $userlist->get_userids();
        $this->assertEmpty($userids);
        provider::add_userids_for_context($userlist);
        $userids = $userlist->get_userids();
        $this->assertEmpty($userids);
        // User one should be added (userid).
        $this->setUser($u1);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        // User two (userids)
        $this->setUser($u2);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        // Nothing should be added, because login-as
        $this->setAdminUser();
        \core\session\manager::loginas($u3->id, context_system::instance());
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        // Set off an event in a different context. User 4 should not be returned below.
        $this->setUser($u4);
        $e = \logstore_usage\event\unittest_view::create(['context' => $sysctx]);
        $e->trigger();

        provider::add_userids_for_context($userlist);
        $userids = $userlist->get_userids();
        $this->assertCount(2, $userids);
        $expectedresult = [$u1->id, $u2->id];
        $this->assertEmpty(array_diff($expectedresult, $userids));
    }

    public function test_delete_data_for_user() {
        global $DB;
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $sysctx = context_system::instance();
        $c1ctx = context_course::instance($c1->id);
        $c2ctx = context_course::instance($c2->id);

        set_config("courses", $c1->id . "," . $c2->id, "logstore_usage");

        $this->enable_logging();
        $manager = get_log_manager(true);

        // User 1 is the author.
        $this->setUser($u1);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        $e = \logstore_usage\event\unittest_view::create(['context' => $c2ctx]);
        $e->trigger();

        // User 2 is the author.
        $this->setUser($u2);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        $e = \logstore_usage\event\unittest_view::create(['context' => $c2ctx]);
        $e->trigger();

        // Confirm data present.
        $this->assertTrue($DB->record_exists('logstore_usage_log', ['userid' => $u1->id, 'contextid' => $c1ctx->id]));
        $this->assertEquals(2, $DB->count_records('logstore_usage_log', ['userid' => $u1->id]));
        $this->assertEquals(2, $DB->count_records('logstore_usage_log', ['userid' => $u2->id]));

        // Delete all the things!
        provider::delete_data_for_user(new approved_contextlist($u1, 'logstore_usage', [$c1ctx->id]));
        $this->assertFalse($DB->record_exists('logstore_usage_log', ['userid' => $u1->id, 'contextid' => $c1ctx->id]));
        $this->assertEquals(1, $DB->count_records('logstore_usage_log', ['userid' => $u1->id]));
        $this->assertEquals(2, $DB->count_records('logstore_usage_log', ['userid' => $u2->id]));
    }

    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $sysctx = context_system::instance();
        $c1ctx = context_course::instance($c1->id);
        $c2ctx = context_course::instance($c2->id);

        set_config("courses", $c1->id . "," . $c2->id, "logstore_usage");

        $this->enable_logging();
        $manager = get_log_manager(true);

        // User 1 is the author.
        $this->setUser($u1);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        $e = \logstore_usage\event\unittest_view::create(['context' => $c2ctx]);
        $e->trigger();

        // User 2 is the author.
        $this->setUser($u2);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        $e = \logstore_usage\event\unittest_view::create(['context' => $c2ctx]);
        $e->trigger();

        // Confirm data present.
        $this->assertTrue($DB->record_exists('logstore_usage_log', ['contextid' => $c1ctx->id]));
        $this->assertEquals(2, $DB->count_records('logstore_usage_log', ['userid' => $u1->id]));
        $this->assertEquals(2, $DB->count_records('logstore_usage_log', ['userid' => $u2->id]));

        // Delete all the things!
        provider::delete_data_for_all_users_in_context($c1ctx);
        $this->assertFalse($DB->record_exists('logstore_usage_log', ['contextid' => $c1ctx->id]));
        $this->assertEquals(1, $DB->count_records('logstore_usage_log', ['userid' => $u1->id]));
        $this->assertEquals(1, $DB->count_records('logstore_usage_log', ['userid' => $u2->id]));
    }

    public function test_delete_data_for_userlist() {
        global $DB;

        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        $u4 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course();
        $sysctx = context_system::instance();
        $c1ctx = context_course::instance($course->id);

        set_config("courses", $course->id, "logstore_usage");

        $this->enable_logging();
        $manager = get_log_manager(true);

        $this->setUser($u1);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        $this->setUser($u2);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        $this->setUser($u3);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();
        //Not in course
        $this->setUser($u4);
        $e = \logstore_usage\event\unittest_view::create(['context' => $sysctx]);
        $e->trigger();

        // Check that three records were created.
        $this->assertEquals(3, $DB->count_records('logstore_usage_log'));

        $userlist = new \core_privacy\local\request\approved_userlist($c1ctx, 'logstore_usage_log', [$u1->id, $u3->id]);
        provider::delete_data_for_userlist($userlist);
        // We should have a record for u2
        $this->assertEquals(1, $DB->count_records('logstore_usage_log'));

        $records = $DB->get_records('logstore_usage_log', ['contextid' => $c1ctx->id]);
        $this->assertCount(1, $records);
        $currentrecord = array_shift($records);
        $this->assertEquals($u2->id, $currentrecord->userid);
    }

    public function test_export_data_for_user() {
        $admin = \core_user::get_user(2);
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $u3 = $this->getDataGenerator()->create_user();
        $u4 = $this->getDataGenerator()->create_user();
        $c1 = $this->getDataGenerator()->create_course();
        $cm1 = $this->getDataGenerator()->create_module('url', ['course' => $c1]);
        $c2 = $this->getDataGenerator()->create_course();
        $cm2 = $this->getDataGenerator()->create_module('url', ['course' => $c2]);
        $sysctx = context_system::instance();
        $c1ctx = context_course::instance($c1->id);
        $c2ctx = context_course::instance($c2->id);
        $cm1ctx = context_module::instance($cm1->cmid);
        $cm2ctx = context_module::instance($cm2->cmid);

        set_config("courses", $c1->id . "," . $c2->id, "logstore_usage");

        $path = [get_string('privacy:path:logs', 'tool_log'), get_string('pluginname', 'logstore_usage')];
        $this->enable_logging();
        $manager = get_log_manager(true);

        // User 1 is the author.
        $this->setUser($u1);
        $e = \logstore_usage\event\unittest_view::create(['context' => $c1ctx]);
        $e->trigger();


        // Confirm data present for u1.
        provider::export_user_data(new approved_contextlist($u1, 'logstore_usage', [$c2ctx->id, $c1ctx->id]));
        $data = writer::with_context($c2ctx)->get_data($path);
        $this->assertEmpty($data);
        $data = writer::with_context($c1ctx)->get_data($path);
        $this->assertCount(1, $data->logs);
        $this->assertEquals(transform::yesno(true), $data->logs[0]['author_of_the_action_was_you']);
    }

    /**
     * Assert the content of a context list.
     *
     * @param contextlist $contextlist The collection.
     * @param array $expected List of expected contexts or IDs.
     * @return void
     */
    protected function assert_contextlist_equals($contextlist, array $expected) {
        $expectedids = array_map(function($context) {
            if (is_object($context)) {
                return $context->id;
            }
            return $context;
        }, $expected);
        $contextids = array_map('intval', $contextlist->get_contextids());
        sort($contextids);
        sort($expectedids);
        $this->assertEquals($expectedids, $contextids);
    }

    /**
     * Enable logging.
     *
     * @return void
     */
    protected function enable_logging() {
        set_config('enabled_stores', 'logstore_usage', 'tool_log');
        set_config('buffersize', 0, 'logstore_usage');
        set_config('logguests', 1, 'logstore_usage');
    }

    /**
     * Get the contextlist for a user.
     *
     * @param object $user The user.
     * @return contextlist
     */
    protected function get_contextlist_for_user($user) {
        $contextlist = new contextlist();
        provider::add_contexts_for_userid($contextlist, $user->id);
        return $contextlist;
    }
}
