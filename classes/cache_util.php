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
 *
 *
 * @package    logstore_usage
 * @copyright  2019 Justus Dieckmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_usage;

defined('MOODLE_INTERNAL') || die();

class cache_util {

    private static function get_cache() : \cache {
        return \cache::make('logstore_usage', 'courses');
    }

    public static function reset_courses_cache() : void {
        $cache = self::get_cache();
        $cache->delete('courses');
    }

    public static function has_course($courseid) : bool {
        return in_array($courseid, self::get_courses());
    }

    public static function build_courses() : array {
        global $DB;
        return $DB->get_fieldset_select('logstore_usage_courses', 'courseid',
                'timeuntil IS NULL OR timeuntil > :time', ['time' => time()]);
    }

    public static function get_courses() : array {
        $cache = self::get_cache();
        $courses = $cache->get('courses');
        if ($courses === false || $cache->get('until') < time()) {
            $courses = self::build_courses();
            $cache->set('courses', $courses);
            $cache->set('until', time() + 24 * 60 * 60);
        }
        return $courses;
    }

}