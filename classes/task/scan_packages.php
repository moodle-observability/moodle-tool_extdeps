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

use tool_extdeps\local\provider\package_scanner;
use tool_extdeps\local\registry;

/**
 * Reads the content of packages (SCORM) looking for external references, within a time and byte budget.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scan_packages extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_scan_packages', 'tool_extdeps');
    }

    /**
     * Runs the task.
     *
     * @return void
     */
    public function execute() {
        $timelimit = (int) get_config('tool_extdeps', 'packagetimebudget') ?: 300;
        $bytelimit = ((int) get_config('tool_extdeps', 'packagebytebudget') ?: 200) * 1024 * 1024;
        $trace = new \text_progress_trace();
        foreach (registry::enabled() as $provider) {
            if ($provider instanceof package_scanner) {
                $count = $provider->scan_packages($timelimit, $bytelimit, $trace);
                $trace->output($provider->get_name() . ": {$count} packages read.");
            }
        }
    }
}
