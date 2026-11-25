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

use tool_extdeps\local\analysis;

/**
 * On demand analysis asked by a person (the only adhoc task of the plugin).
 *
 * One task per request; only one analysis runs at a time (lock). Never queued by events or other tasks.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_analysis extends \core\task\adhoc_task {
    /**
     * Queues the analysis of a run.
     *
     * @param int $runid Run id.
     * @return void
     */
    public static function queue(int $runid): void {
        $task = new self();
        $task->set_custom_data(['runid' => $runid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_run_analysis', 'tool_extdeps');
    }

    /**
     * Runs the task.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $runid = (int) ($this->get_custom_data()->runid ?? 0);
        $run = $DB->get_record('tool_extdeps_run', ['id' => $runid]);
        if (!$run || $run->status !== 'queued') {
            return;
        }
        $factory = \core\lock\lock_config::get_lock_factory('tool_extdeps');
        $lock = $factory->get_lock('analysis', 5);
        if (!$lock) {
            // Another analysis is running: try again in two minutes.
            $task = new self();
            $task->set_custom_data(['runid' => $runid]);
            $task->set_next_run_time(time() + 120);
            \core\task\manager::queue_adhoc_task($task);
            return;
        }
        try {
            analysis::execute($run, new \text_progress_trace());
        } finally {
            $lock->release();
        }
    }
}
