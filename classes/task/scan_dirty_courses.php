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
 * Scans the courses flagged as changed, within a time budget.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scan_dirty_courses extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_scan_dirty_courses', 'tool_extdeps');
    }

    /**
     * Runs the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $start = microtime(true);
        $budget = max(10, (int) get_config('tool_extdeps', 'scanbudget') ?: 120);
        $courses = 0;
        $occurrences = 0;
        do {
            $ids = $DB->get_fieldset_select('tool_extdeps_coursestate', 'courseid', 'dirty = 1', [], 'id ASC', 0, 20);
            foreach ($ids as $courseid) {
                if (!$DB->record_exists('course', ['id' => $courseid])) {
                    scanner::forget_course((int) $courseid);
                    continue;
                }
                $occurrences += scanner::scan_course((int) $courseid);
                $courses++;
                if (microtime(true) - $start > $budget) {
                    break 2;
                }
            }
        } while (count($ids) === 20);
        set_config('lastscanduration', (int) round((microtime(true) - $start) * 1000), 'tool_extdeps');
        mtrace("tool_extdeps: {$courses} courses scanned, {$occurrences} occurrences.");
    }
}
