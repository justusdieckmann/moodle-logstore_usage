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
 * Displays form for editing an entry.
 *
 * @package    logstore_usage
 * @copyright  2024 Justus Dieckmann, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');

global $CFG, $OUTPUT, $PAGE;
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('logsettingusage-edit');

// Check if we go an ID.
$id = optional_param('id', null, PARAM_INT);
// Set the PAGE URL (and mandatory context). Note the ID being recorded, this is important.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/admin/tool/log/store/usage/editentry.php', ['id' => $id]));

$title = get_string('editentry', 'logstore_usage');
$PAGE->set_title($title);
$PAGE->set_heading($title);

// Instantiate a persistent object if we received an ID. Typically receiving an ID
// means that we are going to be updating an object rather than creating a new one.
$persistent = null;
if (!empty($id)) {
    $persistent = new \logstore_usage\course_entry($id);
}

$redirecturl = new moodle_url('/admin/tool/log/store/usage/manageentries.php');

// Create the form instance. We need to use the current URL and the custom data.
$form = new \logstore_usage\course_entry_form($PAGE->url->out(false), ['persistent' => $persistent]);

if ($form->is_cancelled()) {
    redirect($redirecturl);
}

// Get the data. This ensures that the form was validated.
if (($data = $form->get_data())) {
    try {
        if (empty($data->id)) {
            $persistent = new \logstore_usage\course_entry(0, $data);
            $persistent->create();
        } else {
            $persistent->from_record($data);
            $persistent->update();
        }
        \core\notification::success(get_string('changessaved'));
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }

    // We are done, so let's redirect somewhere.
    redirect($redirecturl);
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
