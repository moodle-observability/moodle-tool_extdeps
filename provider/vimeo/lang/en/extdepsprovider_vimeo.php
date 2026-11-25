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
 * English strings for extdepsprovider_vimeo.
 *
 * @package    extdepsprovider_vimeo
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Vimeo';
$string['privacy:metadata'] = 'The Vimeo provider stores no personal data. It sends only video identifiers to Vimeo.';
$string['token'] = 'Vimeo access token';
$string['token_desc'] = 'Optional. With a token (public scope), the privacy settings come from the Vimeo API and are definitive. Without it, oEmbed is used with this site as Referer.';
