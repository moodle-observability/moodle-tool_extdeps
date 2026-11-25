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

namespace tool_extdeps\local\source;

/**
 * Texts of a course where external references may appear.
 *
 * Covers the course and section summaries, the intro of every activity that has one, page content, URL addresses,
 * book chapters, lesson pages and the questions of the question banks of the activities. Reads one course at a time.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class text_sources {
    /** @var array<string, bool> Whether each module table has an intro field. */
    private static array $hasintro = [];

    /**
     * Texts of a course, one fragment at a time.
     *
     * Each fragment has sectionid, cmid, modname, source, itemid, text and cmvisible.
     *
     * @param int $courseid Course id.
     * @return \Generator<\stdClass>
     */
    public static function fragments(int $courseid): \Generator {
        global $DB;

        $summary = $DB->get_field('course', 'summary', ['id' => $courseid]);
        if ($summary !== false) {
            yield self::fragment(null, null, null, 'course.summary', $courseid, (string) $summary, true);
        }

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section', 'id, summary, visible');
        foreach ($sections as $section) {
            yield self::fragment(
                (int) $section->id,
                null,
                null,
                'course_sections.summary',
                (int) $section->id,
                (string) $section->summary,
                (bool) $section->visible
            );
        }

        $sql = "SELECT cm.id, cm.instance, cm.section, cm.visible, m.name AS modname
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :course AND cm.deletioninprogress = 0";
        $bymodule = [];
        foreach ($DB->get_records_sql($sql, ['course' => $courseid]) as $cm) {
            $bymodule[$cm->modname][(int) $cm->instance] = $cm;
        }

        foreach ($bymodule as $modname => $cms) {
            $instances = array_keys($cms);
            if (self::has_intro($modname)) {
                $rs = $DB->get_recordset_list($modname, 'id', $instances, '', 'id, intro');
                foreach ($rs as $record) {
                    $cm = $cms[(int) $record->id];
                    yield self::from_cm($cm, $modname . '.intro', (int) $record->id, (string) $record->intro);
                }
                $rs->close();
            }
            yield from self::module_texts($modname, $cms);
        }

        yield from self::questions($courseid);
    }

    /**
     * Texts specific to some modules.
     *
     * @param string $modname Module name.
     * @param \stdClass[] $cms Course modules keyed by instance id.
     * @return \Generator<\stdClass>
     */
    private static function module_texts(string $modname, array $cms): \Generator {
        global $DB;
        $instances = array_keys($cms);
        $queries = [
            'page' => ['page', 'id', 'id AS instance, content AS text', 'page.content'],
            'url' => ['url', 'id', 'id AS instance, externalurl AS text', 'url.externalurl'],
            'book' => ['book_chapters', 'bookid', 'id, bookid AS instance, content AS text', 'book_chapters.content'],
            'lesson' => ['lesson_pages', 'lessonid', 'id, lessonid AS instance, contents AS text', 'lesson_pages.contents'],
        ];
        if (!isset($queries[$modname])) {
            return;
        }
        [$table, $field, $fields, $source] = $queries[$modname];
        $rs = $DB->get_recordset_list($table, $field, $instances, '', $fields);
        foreach ($rs as $record) {
            $cm = $cms[(int) $record->instance];
            $itemid = isset($record->id) ? (int) $record->id : (int) $record->instance;
            yield self::from_cm($cm, $source, $itemid, (string) $record->text);
        }
        $rs->close();
    }

    /**
     * Question texts of the question banks attached to activities of the course.
     *
     * @param int $courseid Course id.
     * @return \Generator<\stdClass>
     */
    private static function questions(int $courseid): \Generator {
        global $DB;
        $sql = "SELECT q.id, q.questiontext, q.generalfeedback, cm.id AS cmid, cm.section, cm.visible, m.name AS modname
                  FROM {question} q
                  JOIN {question_versions} qv ON qv.questionid = q.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN {context} ctx ON ctx.id = qc.contextid AND ctx.contextlevel = :level
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :course AND cm.deletioninprogress = 0";
        $rs = $DB->get_recordset_sql($sql, ['level' => CONTEXT_MODULE, 'course' => $courseid]);
        foreach ($rs as $record) {
            $cm = (object) ['id' => $record->cmid, 'section' => $record->section, 'visible' => $record->visible,
                'modname' => $record->modname];
            yield self::from_cm($cm, 'question.questiontext', (int) $record->id, (string) $record->questiontext);
            yield self::from_cm($cm, 'question.generalfeedback', (int) $record->id, (string) $record->generalfeedback);
        }
        $rs->close();
    }

    /**
     * Whether the module table has an intro field.
     *
     * @param string $modname Module name.
     * @return bool
     */
    private static function has_intro(string $modname): bool {
        global $DB;
        if (!isset(self::$hasintro[$modname])) {
            $dbman = $DB->get_manager();
            self::$hasintro[$modname] = $dbman->table_exists($modname)
                && $dbman->field_exists($modname, 'intro');
        }
        return self::$hasintro[$modname];
    }

    /**
     * Builds a fragment of an activity.
     *
     * @param \stdClass $cm Course module with id, section, visible and modname.
     * @param string $source Origin.
     * @param int $itemid Id of the record of origin.
     * @param string $text Text.
     * @return \stdClass
     */
    private static function from_cm(\stdClass $cm, string $source, int $itemid, string $text): \stdClass {
        return self::fragment((int) $cm->section, (int) $cm->id, $cm->modname, $source, $itemid, $text, (bool) $cm->visible);
    }

    /**
     * Builds a fragment.
     *
     * @param int|null $sectionid Section id.
     * @param int|null $cmid Course module id.
     * @param string|null $modname Module name.
     * @param string $source Origin.
     * @param int $itemid Id of the record of origin.
     * @param string $text Text.
     * @param bool $visible Visibility.
     * @return \stdClass
     */
    private static function fragment(
        ?int $sectionid,
        ?int $cmid,
        ?string $modname,
        string $source,
        int $itemid,
        string $text,
        bool $visible
    ): \stdClass {
        return (object) ['sectionid' => $sectionid, 'cmid' => $cmid, 'modname' => $modname, 'source' => $source,
            'itemid' => $itemid, 'text' => $text, 'cmvisible' => $visible];
    }
}
