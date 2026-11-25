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
use tool_extdeps\task\run_analysis;

/**
 * Queues the analysis of a course, a provider or the whole site.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_analysis extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'scope' => new external_value(PARAM_ALPHA, 'course, provider or site'),
            'id' => new external_value(PARAM_INT, 'Course id (0 for provider and site)'),
            'provider' => new external_value(PARAM_ALPHANUMEXT, 'Provider name when the scope is provider', VALUE_DEFAULT, ''),
            'dryrun' => new external_value(PARAM_BOOL, 'Report only', VALUE_DEFAULT, false),
            'strict' => new external_value(PARAM_BOOL, 'Use a second method when there is one', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Queues the analysis.
     *
     * @param string $scope Scope.
     * @param int $id Course id.
     * @param string $provider Provider.
     * @param bool $dryrun Dry run.
     * @param bool $strict Strict mode.
     * @return array
     */
    public static function execute(
        string $scope,
        int $id,
        string $provider = '',
        bool $dryrun = false,
        bool $strict = false
    ): array {
        global $DB, $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['scope' => $scope, 'id' => $id, 'provider' => $provider, 'dryrun' => $dryrun, 'strict' => $strict]
        );
        if (!in_array($params['scope'], ['course', 'provider', 'site'], true)) {
            throw new \invalid_parameter_exception('scope must be course, provider or site');
        }
        if ($params['scope'] === 'course') {
            $DB->get_record('course', ['id' => $params['id']], 'id', MUST_EXIST);
            $context = \context_course::instance($params['id']);
        } else {
            $context = \context_system::instance();
        }
        self::validate_context($context);
        require_capability('tool/extdeps:analyze', $context);
        if ($params['scope'] !== 'course') {
            require_capability('tool/extdeps:manage', $context);
        }

        $existing = $DB->get_record_select(
            'tool_extdeps_run',
            "scope = :scope AND scopeid = :scopeid AND status IN ('queued', 'running')",
            ['scope' => $params['scope'], 'scopeid' => $params['scope'] === 'course' ? $params['id'] : 0],
            '*',
            IGNORE_MULTIPLE
        );
        if ($existing) {
            return get_run::export($existing);
        }
        $run = analysis::create_run(
            $params['scope'],
            $params['scope'] === 'course' ? $params['id'] : 0,
            $params['scope'] === 'provider' ? $params['provider'] : null,
            (int) $USER->id,
            $params['dryrun'],
            $params['strict']
        );
        run_analysis::queue((int) $run->id);
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
