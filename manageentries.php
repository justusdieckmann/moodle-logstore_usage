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
 * Displays table for managing entries.
 *
 * @package    logstore_usage
 * @copyright  2024 Justus Dieckmann, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');

global $CFG, $OUTPUT, $PAGE, $DB;
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('logsettingusage-edit');

$title = get_string('manageentries', 'logstore_usage');
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/admin/tool/log/store/usage/manageentries.php'));
$PAGE->set_title($title);
$PAGE->set_heading($title);

$action = optional_param('action', null, PARAM_ALPHAEXT);
if ($action) {
    require_sesskey();

    if ($action == 'delete') {
        $id = required_param('id', PARAM_INT);
        $DB->delete_records('logstore_usage_courses', ['id' => $id]);
    }
    redirect($PAGE->url);
}

$table = new \logstore_usage\course_entry_table();

echo $OUTPUT->header();
$table->out(48, false);
echo $OUTPUT->footer();