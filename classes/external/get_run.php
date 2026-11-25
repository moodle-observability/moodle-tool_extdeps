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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Progress and verdicts of an on demand analysis.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_run extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'runid' => new external_value(PARAM_INT, 'Run id'),
        ]);
    }

    /**
     * Returns a run.
     *
     * @param int $runid Run id.
     * @return array
     */
    public static function execute(int $runid): array {
        global $DB, $USER;
        ['runid' => $runid] = self::validate_parameters(self::execute_parameters(), ['runid' => $runid]);
        $run = $DB->get_record('tool_extdeps_run', ['id' => $runid], '*', MUST_EXIST);
        $context = self::run_context($run);
        self::validate_context($context);
        require_capability('tool/extdeps:analyze', $context);
        if ((int) $run->userid !== (int) $USER->id) {
            require_capability('tool/extdeps:manage', \context_system::instance());
        }
        return self::export($run);
    }

    /**
     * Context that protects a run.
     *
     * @param \stdClass $run Run.
     * @return \context
     */
    public static function run_context(\stdClass $run): \context {
        global $DB;
        if ($run->scope === 'course') {
            return \context_course::instance($run->scopeid);
        }
        if ($run->scope === 'cm') {
            $courseid = $DB->get_field('course_modules', 'course', ['id' => $run->scopeid], MUST_EXIST);
            return \context_course::instance($courseid);
        }
        return \context_system::instance();
    }

    /**
     * Exports a run with its verdicts.
     *
     * @param \stdClass $run Run.
     * @return array
     */
    public static function export(\stdClass $run): array {
        global $DB;
        $items = [];
        foreach ($DB->get_records('tool_extdeps_runitem', ['runid' => $run->id], 'id') as $item) {
            $items[] = [
                'resourceid' => (int) $item->resourceid,
                'provider' => $item->provider,
                'resourcekey' => $item->resourcekey,
                'verdict' => $item->verdict,
                'status' => $item->status,
                'evidence' => (string) $item->evidence,
            ];
        }
        return [
            'id' => (int) $run->id,
            'scope' => $run->scope,
            'scopeid' => (int) $run->scopeid,
            'status' => $run->status,
            'dryrun' => (bool) $run->dryrun,
            'strict' => (bool) $run->strict,
            'processed' => (int) $run->processed,
            'total' => (int) $run->total,
            'summary' => (string) $run->summary,
            'items' => $items,
        ];
    }

    /**
     * Returned structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Run id'),
            'scope' => new external_value(PARAM_ALPHA, 'Scope'),
            'scopeid' => new external_value(PARAM_INT, 'Scope id'),
            'status' => new external_value(PARAM_ALPHA, 'queued, running, done or failed'),
            'dryrun' => new external_value(PARAM_BOOL, 'Dry run'),
            'strict' => new external_value(PARAM_BOOL, 'Strict mode'),
            'processed' => new external_value(PARAM_INT, 'Resources processed'),
            'total' => new external_value(PARAM_INT, 'Resources to process'),
            'summary' => new external_value(PARAM_RAW, 'JSON counters per verdict'),
            'items' => new external_multiple_structure(new external_single_structure([
                'resourceid' => new external_value(PARAM_INT, 'Resource id, 0 when not stored'),
                'provider' => new external_value(PARAM_ALPHANUMEXT, 'Provider'),
                'resourcekey' => new external_value(PARAM_RAW, 'Resource key'),
                'verdict' => new external_value(PARAM_ALPHANUMEXT, 'Verdict'),
                'status' => new external_value(PARAM_ALPHANUMEXT, 'Status observed'),
                'evidence' => new external_value(PARAM_RAW, 'JSON evidence'),
            ])),
        ]);
    }
}
