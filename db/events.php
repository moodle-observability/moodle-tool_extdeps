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
 * Event observers of tool_extdeps.
 *
 * Observers only flag the course for the next scan; they never do heavy work nor queue adhoc tasks.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$contentevents = [
    '\core\event\course_module_created',
    '\core\event\course_module_updated',
    '\core\event\course_module_deleted',
    '\core\event\course_updated',
    '\core\event\course_section_updated',
    '\core\event\course_restored',
    '\core\event\question_created',
    '\core\event\question_updated',
    '\mod_book\event\chapter_created',
    '\mod_book\event\chapter_updated',
    '\mod_book\event\chapter_deleted',
    '\mod_lesson\event\page_created',
    '\mod_lesson\event\page_updated',
    '\mod_lesson\event\page_deleted',
];

$observers = [];
foreach ($contentevents as $eventname) {
    $observers[] = [
        'eventname' => $eventname,
        'callback' => '\tool_extdeps\observer::content_changed',
    ];
}
foreach (['\core\event\course_deleted', '\core\event\course_content_deleted'] as $eventname) {
    $observers[] = [
        'eventname' => $eventname,
        'callback' => '\tool_extdeps\observer::course_deleted',
    ];
}
