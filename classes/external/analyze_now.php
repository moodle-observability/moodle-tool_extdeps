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

namespace tool_extdeps\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_extdeps\local\analysis;

/**
 * Re-reads and checks one activity or one resource synchronously.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class analyze_now extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope' => new external_value(PARAM_ALPHA, 'cm or resource'),
            'id' => new external_value(PARAM_INT, 'Course module id or resource id'),
            'strict' => new external_value(PARAM_BOOL, 'Use a second method when there is one', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Runs the analysis.
     *
     * @param string $scope cm or resource.
     * @param int $id Id.
     * @param bool $strict Strict mode.
     * @return array
     */
    public static function execute(string $scope, int $id, bool $strict = false): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['scope' => $scope, 'id' => $id, 'strict' => $strict]);
        if (!in_array($params['scope'], analysis::SYNC_SCOPES, true)) {
            throw new \invalid_parameter_exception('scope must be cm or resource');
        }
        if ($params['scope'] === 'cm') {
            $courseid = $DB->get_field('course_modules', 'course', ['id' => $params['id']], MUST_EXIST);
            $context = \context_course::instance($courseid);
        } else {
            $DB->get_record('tool_extdeps_resource', ['id' => $params['id']], 'id', MUST_EXIST);
            $context = \context_system::instance();
        }
        self::validate_context($context);
        require_capability('tool/extdeps:analyze', $context);

        $run = analysis::create_run($params['scope'], $params['id'], null, (int) $USER->id, false, $params['strict']);
        $run = analysis::execute($run);
        return get_run::export($run);
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return get_run::execute_returns();
    }
}
