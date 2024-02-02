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
 * Persistent course entry.
 *
 * @package    logstore_usage
 * @copyright  2024 Justus Dieckmann, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_usage;

defined('MOODLE_INTERNAL') || die();

class course_entry_form extends \core\form\persistent {

    protected static $persistentclass = '\logstore_usage\course_entry';

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('course', 'courseid', get_string('course'));
        $mform->addRule('courseid', null, 'required');

        $mform->addElement('date_selector', 'timeuntil', get_string('until', 'logstore_usage'), ['optional' => true]);

        $this->add_action_buttons();
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data && (!isset($data->timeuntil) || !$data->timeuntil)) {
            $data->timeuntil = null;
        }
        return $data;
    }
}