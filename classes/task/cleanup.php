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

/**
 * Removes orphan resources, old history and old analyses.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup', 'tool_extdeps');
    }

    /**
     * Runs the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $now = time();
        $orphandays = (int) get_config('tool_extdeps', 'orphanretention') ?: 30 * DAYSECS;
        $historydays = (int) get_config('tool_extdeps', 'historyretention') ?: 365 * DAYSECS;

        $select = "timemodified < :limit
                   AND NOT EXISTS (SELECT 1 FROM {tool_extdeps_occurrence} o WHERE o.resourceid = {tool_extdeps_resource}.id)";
        $orphans = $DB->get_fieldset_select('tool_extdeps_resource', 'id', $select, ['limit' => $now - $orphandays]);
        foreach (array_chunk($orphans, 500) as $chunk) {
            $DB->delete_records_list('tool_extdeps_history', 'resourceid', $chunk);
            $DB->delete_records_list('tool_extdeps_exception', 'resourceid', $chunk);
            $DB->delete_records_list('tool_extdeps_resource', 'id', $chunk);
        }

        $DB->delete_records_select('tool_extdeps_history', 'timecreated < ?', [$now - $historydays]);

        $runs = $DB->get_fieldset_select(
            'tool_extdeps_run',
            'id',
            'timequeued < ? AND status <> ?',
            [$now - $historydays, 'running']
        );
        foreach (array_chunk($runs, 500) as $chunk) {
            $DB->delete_records_list('tool_extdeps_runitem', 'runid', $chunk);
            $DB->delete_records_list('tool_extdeps_run', 'id', $chunk);
        }
        mtrace('tool_extdeps: ' . count($orphans) . ' orphan resources and ' . count($runs) . ' old analyses removed.');
    }
}
