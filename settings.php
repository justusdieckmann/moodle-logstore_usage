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
 * Standard log store settings.
 *
 * @package    logstore_usage
 * @copyright  2013 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $ADMIN, $settings;

    $settings = new admin_category('logsettingusage', new lang_string('pluginname', 'logstore_usage'));
    $ADMIN->add('logging', $settings);
    $settingspage = new admin_settingpage('logsettingusage-settings', new lang_string('settings'));

    $options = array(
            0 => new lang_string('neverdeletelogs'),
            1000 => new lang_string('numdays', '', 1000),
            365 => new lang_string('numdays', '', 365),
            180 => new lang_string('numdays', '', 180),
            150 => new lang_string('numdays', '', 150),
            120 => new lang_string('numdays', '', 120),
            90 => new lang_string('numdays', '', 90),
            60 => new lang_string('numdays', '', 60),
            35 => new lang_string('numdays', '', 35),
            10 => new lang_string('numdays', '', 10),
            5 => new lang_string('numdays', '', 5),
            2 => new lang_string('numdays', '', 2));
    $settingspage->add(new admin_setting_configselect('logstore_usage/loglifetime',
            new lang_string('loglifetime', 'core_admin'),
            new lang_string('configloglifetime', 'core_admin'), 0, $options));

    $settingspage->add(new admin_setting_configtext('logstore_usage/buffersize',
            get_string('buffersize', 'logstore_usage'),
            '', '50', PARAM_INT));

    $ADMIN->add('logsettingusage', $settingspage);
    $ADMIN->add('logsettingusage', new admin_externalpage('logsettingusage-edit', 'Edit enabled courses',
            new moodle_url('/admin/tool/log/store/usage/manageentries.php')));

    $settings = null;
}
