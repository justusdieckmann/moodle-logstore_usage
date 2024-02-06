<?php
// This file is part of a plugin for Moodle - http://moodle.org/
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
 * Table displaying all course entries.
 *
 * @package    logstore_usage
 * @copyright  2024 Justus Dieckmann, University of Münster
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_usage;

defined('MOODLE_INTERNAL') || die;
global $CFG;

require_once($CFG->libdir . '/tablelib.php');

class course_entry_table extends \table_sql {

    /**
     * Constructor for active_processes_table.
     * @param int $uniqueid Unique id of this table.
     * @param \stdClass|null $filterdata
     */
    public function __construct() {
        parent::__construct('local_marketing-slides-table');
        global $PAGE;

        $this->set_sql('lc.*, c.fullname',
                '{logstore_usage_courses} lc ' .
                'LEFT JOIN {course} c ON c.id = lc.courseid',
                'true');

        $this->define_baseurl($PAGE->url);
        $this->define_columns(['courseid', 'timeuntil', 'tools']);
        $this->define_headers([
                get_string('course'),
                get_string('until', 'logstore_usage'),
                get_string('tools', 'logstore_usage')
        ]);

        $this->column_nosort = ['tools'];
    }

    public function col_courseid($row) {
        return \html_writer::link(course_get_url($row->courseid), $row->fullname);
    }

    public function col_timeuntil($row) {
        return $row->timeuntil ? userdate($row->timeuntil, get_string('strftimedate')) : '-';
    }

    public function col_tools($row) {
        global $OUTPUT;

        return $OUTPUT->action_icon(new \moodle_url('/admin/tool/log/store/usage/editentry.php', ['id' => $row->id]),
                        new \pix_icon('t/edit', get_string('edit'), 'moodle'),
                        null, array('title' => get_string('edit'))) . ' ' .
                $OUTPUT->action_icon(new \moodle_url('/admin/tool/log/store/usage/manageentries.php', ['id' => $row->id, 'action' => 'delete']),
                        new \pix_icon('t/delete', get_string('delete'), 'moodle'),
                        null, array('title' => get_string('delete')));
    }
}
