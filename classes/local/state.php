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

use tool_extdeps\local\provider\base;

/**
 * Applies the result of a check to a resource: confirmation, history and alerts.
 *
 * Rules:
 * - temporary errors and unknown answers never change the confirmed status;
 * - an available answer confirms at once;
 * - a failure is confirmed after the provider's number of equal failures in a row, or at once with definitive evidence.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class state {
    /**
     * Applies a result and saves the resource.
     *
     * @param \stdClass $resource Record of tool_extdeps_resource.
     * @param result $result Result of the check.
     * @param base $provider Provider of the resource.
     * @param bool $notify Whether to send alerts on confirmed changes.
     * @param int|null $now Current time.
     * @return string|null The new confirmed status when it changed, otherwise null.
     */
    public static function apply(
        \stdClass $resource,
        result $result,
        base $provider,
        bool $notify = true,
        ?int $now = null
    ): ?string {
        global $DB;
        $now = $now ?? time();
        $old = $resource->confirmedstatus;
        $changed = null;

        $resource->status = $result->status;
        $resource->lastcheck = $now;
        $resource->detail = $result->to_json();
        $resource->timemodified = $now;

        if (status::is_inconclusive($result->status) || $result->divergent) {
            $resource->nextcheck = $now + max($result->retryafter, $provider->get_interval('suspect'));
        } else if ($result->status === status::OK) {
            $resource->lastok = $now;
            $resource->failcount = 0;
            $resource->candidatestatus = null;
            if ($old !== status::OK) {
                $changed = status::OK;
            }
            $resource->nextcheck = $now + $provider->get_interval('ok');
        } else {
            if ($resource->candidatestatus === $result->status) {
                $resource->failcount = (int) $resource->failcount + 1;
            } else {
                $resource->candidatestatus = $result->status;
                $resource->failcount = 1;
            }
            $needed = $result->definitive ? 1 : $provider->get_confirmations();
            if ($resource->failcount >= $needed && $old !== $result->status) {
                $changed = $result->status;
            }
            $confirmedbroken = ($changed ?? $old) === $result->status;
            $resource->nextcheck = $now + $provider->get_interval($confirmedbroken ? 'broken' : 'suspect');
        }

        if ($changed !== null) {
            $resource->confirmedstatus = $changed;
            $resource->timeconfirmed = $now;
            $DB->insert_record('tool_extdeps_history', (object) [
                'resourceid' => $resource->id,
                'oldstatus' => $old,
                'newstatus' => $changed,
                'detail' => $resource->detail,
                'timecreated' => $now,
            ]);
        }
        $DB->update_record('tool_extdeps_resource', $resource);

        if ($changed !== null && $notify) {
            notifier::status_changed($resource, $old, $changed);
        }
        return $changed;
    }
}
