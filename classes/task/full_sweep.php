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

namespace tool_extdeps\task;

use tool_extdeps\local\scanner;

/**
 * Safety net for missed events: flags courses never scanned or changed after the last scan.
 *
 * Only flags; the scan itself is done by scan_dirty_courses, a few courses at a time.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class full_sweep extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_full_sweep', 'tool_extdeps');
    }

    /**
     * Runs the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $sql = "SELECT c.id
                  FROM {course} c
             LEFT JOIN {tool_extdeps_coursestate} s ON s.courseid = c.id
                 WHERE s.id IS NULL
                    OR (s.dirty = 0 AND (c.timemodified > s.lastscan OR s.lastscan < :old
                        OR EXISTS (SELECT 1 FROM {course_modules} cm
                                    WHERE cm.course = c.id AND cm.added > s.lastscan)))";
        $count = 0;
        // Courses not scanned for 30 days are scanned again, for changes made without events (e.g. in the database).
        $rs = $DB->get_recordset_sql($sql, ['old' => time() - 30 * DAYSECS]);
        foreach ($rs as $course) {
            scanner::mark_dirty((int) $course->id, 'full_sweep');
            $count++;
        }
        $rs->close();
        mtrace("tool_extdeps: {$count} courses flagged for scanning.");
    }
}
