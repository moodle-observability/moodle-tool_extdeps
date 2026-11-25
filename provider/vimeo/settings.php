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
 * Settings of extdepsprovider_vimeo (the page is created by the plugininfo class).
 *
 * @package    extdepsprovider_vimeo
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    \tool_extdeps\local\provider\base::add_common_settings($settings, 'extdepsprovider_vimeo', ['rate' => 30]);
    $settings->add(new admin_setting_configpasswordunmask(
        'extdepsprovider_vimeo/token',
        new lang_string('token', 'extdepsprovider_vimeo'),
        new lang_string('token_desc', 'extdepsprovider_vimeo'),
        ''
    ));
}
