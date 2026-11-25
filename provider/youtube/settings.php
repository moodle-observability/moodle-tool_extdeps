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
 * Settings of extdepsprovider_youtube (the page is created by the plugininfo class).
 *
 * @package    extdepsprovider_youtube
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    \tool_extdeps\local\provider\base::add_common_settings($settings, 'extdepsprovider_youtube', ['rate' => 15]);
    $settings->add(new admin_setting_configpasswordunmask(
        'extdepsprovider_youtube/apikey',
        new lang_string('apikey', 'extdepsprovider_youtube'),
        new lang_string('apikey_desc', 'extdepsprovider_youtube'),
        ''
    ));
}
