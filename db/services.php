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
 * External functions of tool_extdeps.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_extdeps_analyze_now' => [
        'classname' => \tool_extdeps\external\analyze_now::class,
        'description' => 'Re-reads and checks the external dependencies of one activity or one resource right now.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/extdeps:analyze',
    ],
    'tool_extdeps_queue_analysis' => [
        'classname' => \tool_extdeps\external\queue_analysis::class,
        'description' => 'Queues an on demand analysis of a course, a provider or the whole site.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'tool/extdeps:analyze',
    ],
    'tool_extdeps_get_run' => [
        'classname' => \tool_extdeps\external\get_run::class,
        'description' => 'Returns the progress and the verdicts of an on demand analysis.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'tool/extdeps:analyze',
    ],
];
