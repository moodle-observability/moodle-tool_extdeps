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
 * False positives accepted by a manager: the resource keeps being checked, but raises no alert.
 *
 * The exception goes away with the resource (cleanup of orphans).
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class exceptions {
    /**
     * Accepts a false positive.
     *
     * @param int $resourceid Resource id.
     * @param int $userid Who accepts it.
     * @param string $reason Reason.
     * @return void
     */
    public static function mark(int $resourceid, int $userid, string $reason): void {
        global $DB;
        $DB->get_record('tool_extdeps_resource', ['id' => $resourceid], 'id', MUST_EXIST);
        $reason = trim($reason);
        if ($reason === '') {
            throw new \invalid_parameter_exception('A reason is required');
        }
        $existing = $DB->get_record('tool_extdeps_exception', ['resourceid' => $resourceid]);
        $record = (object) ['resourceid' => $resourceid, 'userid' => $userid, 'reason' => $reason, 'timecreated' => time()];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('tool_extdeps_exception', $record);
        } else {
            $DB->insert_record('tool_extdeps_exception', $record);
        }
    }

    /**
     * Removes the exception of a resource.
     *
     * @param int $resourceid Resource id.
     * @return void
     */
    public static function clear(int $resourceid): void {
        global $DB;
        $DB->delete_records('tool_extdeps_exception', ['resourceid' => $resourceid]);
    }

    /**
     * Whether a resource has an accepted false positive.
     *
     * @param int $resourceid Resource id.
     * @return bool
     */
    public static function exists(int $resourceid): bool {
        global $DB;
        return $DB->record_exists('tool_extdeps_exception', ['resourceid' => $resourceid]);
    }
}
