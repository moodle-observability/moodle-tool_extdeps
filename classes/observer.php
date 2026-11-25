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

namespace tool_extdeps;

use tool_extdeps\local\scanner;

/**
 * Event observers: they only flag the course for the next scan.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * Content of a course changed.
     *
     * @param \core\event\base $event Event.
     * @return void
     */
    public static function content_changed(\core\event\base $event): void {
        $courseid = (int) $event->courseid;
        if ($courseid <= 0) {
            return;
        }
        $parts = explode('\\', $event->eventname);
        scanner::mark_dirty($courseid, end($parts));
    }

    /**
     * A course was deleted, or its content was removed.
     *
     * @param \core\event\base $event Event.
     * @return void
     */
    public static function course_deleted(\core\event\base $event): void {
        $courseid = (int) ($event->courseid ?: $event->objectid);
        if ($courseid <= 0) {
            return;
        }
        $deleted = $event instanceof \core\event\course_deleted;
        scanner::forget_course($courseid, $deleted);
        if (!$deleted) {
            scanner::mark_dirty($courseid, 'course_content_deleted');
        }
    }
}
