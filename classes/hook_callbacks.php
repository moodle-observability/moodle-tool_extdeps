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

use tool_extdeps\local\metrics;

/**
 * Hook callbacks.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Adds the metrics of the plugin to tool_prometheus.
     *
     * @param \tool_prometheus\hook\collect_metrics $hook Hook.
     * @return void
     */
    public static function collect_metrics($hook): void {
        if (during_initial_install() || !empty($GLOBALS['CFG']->upgraderunning)) {
            return;
        }
        foreach (metrics::gauges() as $gauge) {
            $hook->add_gauge('tool_extdeps', $gauge['name'], $gauge['help'], $gauge['value'], $gauge['labels']);
        }
    }
}
