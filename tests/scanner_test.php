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

use tool_extdeps\local\scanner;

/**
 * Tests of the scan of courses and of the observers.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_extdeps\local\scanner
 * @covers     \tool_extdeps\local\source\text_sources
 * @covers     \tool_extdeps\local\eligibility
 * @covers     \tool_extdeps\observer
 */
final class scanner_test extends \advanced_testcase {
    /**
     * Occurrences of a course as source => provider:key.
     *
     * @param int $courseid Course.
     * @return array
     */
    private function stored(int $courseid): array {
        global $DB;
        $sql = "SELECT o.id, o.source, o.cmid, r.provider, r.resourcekey
                  FROM {tool_extdeps_occurrence} o
                  JOIN {tool_extdeps_resource} r ON r.id = o.resourceid
                 WHERE o.courseid = ?
              ORDER BY o.source, r.resourcekey";
        $list = [];
        foreach ($DB->get_records_sql($sql, [$courseid]) as $row) {
            $list[] = $row->source . ' ' . $row->provider . ':' . $row->resourcekey;
        }
        return $list;
    }

    /**
     * Texts of several sources are scanned; the course is never changed.
     *
     * @return void
     */
    public function test_scan_course(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'summary' => '<p><a href="https://youtu.be/AAAAAAAAAAA">intro</a></p>',
        ]);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'content' => '<iframe src="https://www.youtube.com/embed/BBBBBBBBBBB?rel=0&amp;x=1"></iframe>'
                . '<iframe src="https://player.vimeo.com/video/123456789?h=abcdef1234"></iframe>',
        ]);
        $this->getDataGenerator()->create_module('url', [
            'course' => $course->id, 'externalurl' => 'https://vimeo.com/987654321',
        ]);
        $this->getDataGenerator()->create_module('label', [
            'course' => $course->id, 'intro' => 'https://www.youtube.com/watch?v=AAAAAAAAAAA',
        ]);
        $before = $DB->get_record('page', ['id' => $page->id]);

        $this->assertSame(5, scanner::scan_course($course->id));
        $this->assertSame([
            'course.summary youtube:AAAAAAAAAAA',
            'label.intro youtube:AAAAAAAAAAA',
            'page.content vimeo:123456789:abcdef1234',
            'page.content youtube:BBBBBBBBBBB',
            'url.externalurl vimeo:987654321',
        ], $this->stored($course->id));
        // One resource per distinct video.
        $this->assertSame(1, $DB->count_records('tool_extdeps_resource', ['provider' => 'youtube',
            'resourcekey' => 'AAAAAAAAAAA']));
        $this->assertEquals($before, $DB->get_record('page', ['id' => $page->id]));
        $state = $DB->get_record('tool_extdeps_coursestate', ['courseid' => $course->id]);
        $this->assertSame(0, (int) $state->dirty);
        $this->assertSame(5, (int) $state->refcount);
    }

    /**
     * Hidden activities and excluded courses follow the settings.
     *
     * @return void
     */
    public function test_settings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('label', [
            'course' => $course->id, 'intro' => 'https://youtu.be/AAAAAAAAAAA', 'visible' => 0,
        ]);
        set_config('includehiddencm', 0, 'tool_extdeps');
        $this->assertSame(0, scanner::scan_course($course->id));
        set_config('includehiddencm', 1, 'tool_extdeps');
        $this->assertSame(1, scanner::scan_course($course->id));
        set_config('excludedcourses', '999,' . $course->id, 'tool_extdeps');
        $this->assertSame(0, scanner::scan_course($course->id));
        $this->assertSame([], $this->stored($course->id));
    }

    /**
     * Editing content flags the course; deleting the course removes its occurrences.
     *
     * @return void
     */
    public function test_observers(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $DB->delete_records('tool_extdeps_coursestate');
        $this->getDataGenerator()->create_module('label', ['course' => $course->id, 'intro' => 'https://youtu.be/AAAAAAAAAAA']);
        $this->assertSame(1, (int) $DB->get_field('tool_extdeps_coursestate', 'dirty', ['courseid' => $course->id]));

        $this->expectOutputRegex('/1 courses scanned/');
        (new task\scan_dirty_courses())->execute();
        $this->assertSame(0, (int) $DB->get_field('tool_extdeps_coursestate', 'dirty', ['courseid' => $course->id]));
        $this->assertCount(1, $this->stored($course->id));

        delete_course($course, false);
        $this->assertSame([], $this->stored($course->id));
        $this->assertFalse($DB->record_exists('tool_extdeps_coursestate', ['courseid' => $course->id]));
    }

    /**
     * Courses without scan state are all flagged once, as done at install.
     *
     * @return void
     */
    public function test_mark_all_dirty(): void {
        global $DB;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_course();
        $DB->delete_records('tool_extdeps_coursestate');
        $total = $DB->count_records('course');
        $this->assertSame($total, scanner::mark_all_dirty('install'));
        $this->assertSame($total, $DB->count_records('tool_extdeps_coursestate', ['dirty' => 1, 'reason' => 'install']));
        $this->assertSame(0, scanner::mark_all_dirty('install'));
    }

    /**
     * The first time an occurrence was found is kept between scans.
     *
     * @return void
     */
    public function test_timefound_kept(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['summary' => 'https://youtu.be/AAAAAAAAAAA']);
        scanner::scan_course($course->id);
        $DB->set_field('tool_extdeps_occurrence', 'timefound', 1000, ['courseid' => $course->id]);
        scanner::scan_course($course->id);
        $this->assertSame(1000, (int) $DB->get_field('tool_extdeps_occurrence', 'timefound', ['courseid' => $course->id]));
    }
}
