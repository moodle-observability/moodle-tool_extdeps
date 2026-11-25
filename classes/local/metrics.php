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

namespace tool_extdeps\local;

/**
 * Low cardinality metrics: labels are only provider and status, never course ids.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class metrics {
    /**
     * Gauges to export.
     *
     * @return array<int, array{name: string, help: string, value: int|float, labels: array}>
     */
    public static function gauges(): array {
        global $DB;
        $gauges = [];
        $sql = "SELECT r.provider, r.confirmedstatus, COUNT(DISTINCT r.id) AS resources, COUNT(o.id) AS occurrences
                  FROM {tool_extdeps_resource} r
                  JOIN {tool_extdeps_occurrence} o ON o.resourceid = r.id
              GROUP BY r.provider, r.confirmedstatus";
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $row) {
            $labels = ['provider' => $row->provider, 'status' => $row->confirmedstatus];
            $gauges[] = ['name' => 'moodle_tool_extdeps_resources', 'help' => 'Distinct external dependencies in use.',
                'value' => (int) $row->resources, 'labels' => $labels];
            $gauges[] = ['name' => 'moodle_tool_extdeps_occurrences', 'help' => 'Places where external dependencies are used.',
                'value' => (int) $row->occurrences, 'labels' => $labels];
        }
        $rs->close();
        $now = time();
        foreach (registry::enabled() as $name => $provider) {
            $last = (int) $DB->get_field_sql('SELECT MAX(lastcheck) FROM {tool_extdeps_resource} WHERE provider = ?', [$name]);
            $gauges[] = ['name' => 'moodle_tool_extdeps_last_verify_age_seconds',
                'help' => 'Seconds since the last check of the provider.',
                'value' => $last ? $now - $last : -1, 'labels' => ['provider' => $name]];
            $gauges[] = ['name' => 'moodle_tool_extdeps_provider_paused',
                'help' => 'Whether the provider is paused after a rate limit answer.',
                'value' => (int) verifier::is_paused($name), 'labels' => ['provider' => $name]];
        }
        $gauges[] = ['name' => 'moodle_tool_extdeps_dirty_courses', 'help' => 'Courses waiting for a scan.',
            'value' => $DB->count_records('tool_extdeps_coursestate', ['dirty' => 1]), 'labels' => []];
        $gauges[] = ['name' => 'moodle_tool_extdeps_scan_duration_seconds',
            'help' => 'Duration of the last scan run of the dirty courses.',
            'value' => round(((int) get_config('tool_extdeps', 'lastscanduration')) / 1000, 3), 'labels' => []];
        return $gauges;
    }
}
