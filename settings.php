<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings of tool_extdeps and of its providers.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category('tool_extdeps', new lang_string('pluginname', 'tool_extdeps')));

    $settings = new admin_settingpage('tool_extdeps_settings', new lang_string('generalsettings', 'tool_extdeps'));
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'tool_extdeps/scopeheading',
            new lang_string('scopeheading', 'tool_extdeps'),
            new lang_string('scopeheading_desc', 'tool_extdeps')
        ));
        $settings->add(new admin_setting_configcheckbox(
            'tool_extdeps/includeended',
            new lang_string('includeended', 'tool_extdeps'),
            new lang_string('includeended_desc', 'tool_extdeps'),
            1
        ));
        $settings->add(new admin_setting_configtext(
            'tool_extdeps/endedcategories',
            new lang_string('endedcategories', 'tool_extdeps'),
            new lang_string('endedcategories_desc', 'tool_extdeps'),
            '',
            PARAM_SEQUENCE
        ));
        $settings->add(new admin_setting_configcheckbox(
            'tool_extdeps/includehidden',
            new lang_string('includehidden', 'tool_extdeps'),
            new lang_string('includehidden_desc', 'tool_extdeps'),
            1
        ));
        $settings->add(new admin_setting_configcheckbox(
            'tool_extdeps/includehiddencm',
            new lang_string('includehiddencm', 'tool_extdeps'),
            new lang_string('includehiddencm_desc', 'tool_extdeps'),
            1
        ));
        $settings->add(new admin_setting_configtext(
            'tool_extdeps/excludedcategories',
            new lang_string('excludedcategories', 'tool_extdeps'),
            new lang_string('excludedcategories_desc', 'tool_extdeps'),
            '',
            PARAM_SEQUENCE
        ));
        $settings->add(new admin_setting_configtext(
            'tool_extdeps/excludedcourses',
            new lang_string('excludedcourses', 'tool_extdeps'),
            new lang_string('excludedcourses_desc', 'tool_extdeps'),
            '',
            PARAM_SEQUENCE
        ));

        $settings->add(new admin_setting_heading(
            'tool_extdeps/loadheading',
            new lang_string('loadheading', 'tool_extdeps'),
            new lang_string('loadheading_desc', 'tool_extdeps')
        ));
        $settings->add(new admin_setting_configtext(
            'tool_extdeps/scanbudget',
            new lang_string('scanbudget', 'tool_extdeps'),
            new lang_string('scanbudget_desc', 'tool_extdeps'),
            120,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'tool_extdeps/verifybudget',
            new lang_string('verifybudget', 'tool_extdeps'),
            new lang_string('verifybudget_desc', 'tool_extdeps'),
            240,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'tool_extdeps/packagetimebudget',
            new lang_string('packagetimebudget', 'tool_extdeps'),
            new lang_string('packagetimebudget_desc', 'tool_extdeps'),
            300,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configtext(
            'tool_extdeps/packagebytebudget',
            new lang_string('packagebytebudget', 'tool_extdeps'),
            new lang_string('packagebytebudget_desc', 'tool_extdeps'),
            200,
            PARAM_INT
        ));
        $settings->add(new admin_setting_configduration(
            'tool_extdeps/orphanretention',
            new lang_string('orphanretention', 'tool_extdeps'),
            new lang_string('orphanretention_desc', 'tool_extdeps'),
            30 * DAYSECS
        ));
        $settings->add(new admin_setting_configduration(
            'tool_extdeps/historyretention',
            new lang_string('historyretention', 'tool_extdeps'),
            new lang_string('historyretention_desc', 'tool_extdeps'),
            365 * DAYSECS
        ));

        $settings->add(new admin_setting_heading(
            'tool_extdeps/alertheading',
            new lang_string('alertheading', 'tool_extdeps'),
            new lang_string('alertheading_desc', 'tool_extdeps')
        ));
        $choices = [];
        foreach (\tool_extdeps\local\status::broken() as $status) {
            $choices[$status] = new lang_string('status_' . $status, 'tool_extdeps');
        }
        $settings->add(new admin_setting_configmulticheckbox(
            'tool_extdeps/alertstatuses',
            new lang_string('alertstatuses', 'tool_extdeps'),
            new lang_string('alertstatuses_desc', 'tool_extdeps'),
            array_fill_keys(array_keys($choices), 1),
            $choices
        ));
    }
    $ADMIN->add('tool_extdeps', $settings);

    foreach (core_plugin_manager::instance()->get_plugins_of_type('extdepsprovider') as $plugin) {
        /** @var \tool_extdeps\plugininfo\extdepsprovider $plugin */
        $plugin->load_settings($ADMIN, 'tool_extdeps', $hassiteconfig);
    }
}
