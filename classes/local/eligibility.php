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
 * Decides which courses and activities are monitored, from the plugin settings.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class eligibility {
    /**
     * Whether a course is monitored.
     *
     * @param int $courseid Course id.
     * @return bool
     */
    public static function course(int $courseid): bool {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], 'id, category, visible, enddate');
        if (!$course) {
            return false;
        }
        if (in_array($courseid, self::ids('excludedcourses'), true)) {
            return false;
        }
        if (!get_config('tool_extdeps', 'includehidden') && !$course->visible && $courseid != SITEID) {
            return false;
        }
        $path = $course->category ? (string) $DB->get_field('course_categories', 'path', ['id' => $course->category]) : '';
        if (self::in_categories($path, self::ids('excludedcategories'))) {
            return false;
        }
        if (!get_config('tool_extdeps', 'includeended')) {
            if ($course->enddate > 0 && $course->enddate < time()) {
                return false;
            }
            if (self::in_categories($path, self::ids('endedcategories'))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether hidden activities are monitored.
     *
     * @return bool
     */
    public static function include_hidden_activities(): bool {
        return (bool) get_config('tool_extdeps', 'includehiddencm');
    }

    /**
     * Whether a category path is inside one of the categories.
     *
     * @param string $path Category path, e.g. /1/4/9.
     * @param int[] $categoryids Category ids.
     * @return bool
     */
    private static function in_categories(string $path, array $categoryids): bool {
        foreach ($categoryids as $id) {
            if (str_contains($path . '/', '/' . $id . '/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ids stored in a setting (comma separated or multiselect).
     *
     * @param string $name Setting name.
     * @return int[]
     */
    private static function ids(string $name): array {
        $value = (string) get_config('tool_extdeps', $name);
        $ids = array_filter(array_map('intval', preg_split('/[\s,;]+/', $value)));
        return array_values(array_unique($ids));
    }
}
