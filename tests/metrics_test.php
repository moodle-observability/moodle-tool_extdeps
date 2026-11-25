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
use tool_extdeps\local\scanner;

/**
 * Tests of the metrics given to tool_prometheus.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_extdeps\local\metrics
 */
final class metrics_test extends \advanced_testcase {
    /**
     * Names follow the tool_prometheus rule and labels never carry course or resource ids.
     *
     * @return void
     */
    public function test_gauges(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => 'https://youtu.be/AAAAAAAAAAA']);
        scanner::scan_course($course->id);

        $gauges = metrics::gauges();
        $this->assertNotEmpty($gauges);
        $byname = [];
        foreach ($gauges as $gauge) {
            $this->assertStringStartsWith('moodle_tool_extdeps_', $gauge['name']);
            $this->assertEmpty(array_diff(array_keys($gauge['labels']), ['provider', 'status']));
            $byname[$gauge['name']][] = $gauge;
        }
        $resources = array_filter(
            $byname['moodle_tool_extdeps_resources'],
            fn($g) => $g['labels']['provider'] === 'youtube'
        );
        $this->assertSame(1, (int) array_sum(array_column($resources, 'value')));
        $this->assertArrayHasKey('moodle_tool_extdeps_dirty_courses', $byname);
    }
}
