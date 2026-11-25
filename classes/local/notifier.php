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
 * Sends alerts about confirmed changes through local_alerthub, when it is installed.
 *
 * One alert per resource and course, so that the course editors can be the recipients. Only course and activity
 * identifiers and names are sent, never personal data.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier {
    /** @var string Class of the alert hub API. */
    private const HUB = '\local_alerthub\api';

    /**
     * Whether alerts can be sent.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists(self::HUB);
    }

    /**
     * Statuses that raise alerts.
     *
     * @return string[]
     */
    public static function alert_statuses(): array {
        $value = get_config('tool_extdeps', 'alertstatuses');
        if ($value === false || $value === null) {
            return status::broken();
        }
        return array_values(array_filter(explode(',', (string) $value)));
    }

    /**
     * Resolves the alerts of a resource in courses that no longer use it.
     *
     * @param \stdClass $resource Resource.
     * @param int[] $courseids Courses that were re-read.
     * @return void
     */
    public static function removed_from_courses(\stdClass $resource, array $courseids): void {
        global $DB;
        if (!self::available()) {
            return;
        }
        $hub = self::HUB;
        foreach ($courseids as $courseid) {
            if (!$DB->record_exists('tool_extdeps_occurrence', ['resourceid' => $resource->id, 'courseid' => $courseid])) {
                $hub::resolve('tool_extdeps', $resource->id . ':' . $courseid);
            }
        }
    }

    /**
     * Raises or resolves the alerts of a resource after a confirmed change.
     *
     * @param \stdClass $resource Resource.
     * @param string $old Old confirmed status.
     * @param string $new New confirmed status.
     * @return void
     */
    public static function status_changed(\stdClass $resource, string $old, string $new): void {
        global $DB;
        if (!self::available()) {
            return;
        }
        if ($DB->record_exists('tool_extdeps_exception', ['resourceid' => $resource->id])) {
            return;
        }
        $hub = self::HUB;
        $sql = "SELECT DISTINCT o.courseid, o.cmid, c.fullname
                  FROM {tool_extdeps_occurrence} o
                  JOIN {course} c ON c.id = o.courseid
                 WHERE o.resourceid = :resourceid";
        $occurrences = $DB->get_records_sql($sql, ['resourceid' => $resource->id]);
        $courses = [];
        foreach ($occurrences as $occurrence) {
            $courses[$occurrence->courseid] = $occurrence->fullname;
        }
        foreach ($courses as $courseid => $fullname) {
            $dedupkey = $resource->id . ':' . $courseid;
            if ($new === status::OK || !in_array($new, self::alert_statuses(), true)) {
                $hub::resolve('tool_extdeps', $dedupkey);
                continue;
            }
            $a = (object) [
                'course' => format_string($fullname, true, ['context' => \context_course::instance($courseid)]),
                'provider' => $resource->provider,
                'url' => $resource->url,
                'status' => get_string('status_' . $new, 'tool_extdeps'),
            ];
            $hub::raise([
                'source' => 'tool_extdeps',
                'dedupkey' => $dedupkey,
                'severity' => 'warning',
                'category' => 'externaldependency',
                'title' => get_string('alert_title', 'tool_extdeps', $a),
                'body' => get_string('alert_body', 'tool_extdeps', $a),
                'url' => (new \moodle_url('/report/extdeps/course.php', ['id' => $courseid]))->out(false),
                'contextid' => \context_course::instance($courseid)->id,
                'payload' => ['courseid' => $courseid, 'resourceid' => (int) $resource->id, 'provider' => $resource->provider,
                    'status' => $new, 'previous' => $old],
            ]);
        }
    }
}
