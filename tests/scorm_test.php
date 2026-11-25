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

use extdepsprovider_scorm\provider;
use tool_extdeps\local\scanner;
use tool_extdeps\local\status;

/**
 * Tests of the SCORM provider.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \extdepsprovider_scorm\provider
 */
final class scorm_test extends \advanced_testcase {
    /**
     * Local packages: available, then missing.
     *
     * @return void
     */
    public function test_local_package(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);

        scanner::scan_course($course->id);
        $resource = $DB->get_record(
            'tool_extdeps_resource',
            ['provider' => 'scorm', 'resourcekey' => 'cm:' . $scorm->cmid],
            '*',
            MUST_EXIST
        );
        $this->assertSame(status::OK, provider::check_local((int) $scorm->cmid)->status);

        $context = \context_module::instance($scorm->cmid);
        get_file_storage()->delete_area_files($context->id, 'mod_scorm', 'package');
        $result = provider::check_local((int) $scorm->cmid);
        $this->assertSame(status::MISSING, $result->status);
        $this->assertTrue($result->definitive);
        $this->assertNotEmpty($resource->id);
    }

    /**
     * References inside the package are cached by content hash and become occurrences.
     *
     * @return void
     */
    public function test_package_content(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $context = \context_module::instance($scorm->cmid);
        get_file_storage()->create_file_from_string(
            ['contextid' => $context->id, 'component' => 'mod_scorm',
            'filearea' => 'content', 'itemid' => 0, 'filepath' => '/extra/', 'filename' => 'video.html'],
            '<iframe src="https://www.youtube.com/embed/AAAAAAAAAAA"></iframe>'
        );

        $provider = new provider();
        $this->assertSame(1, $provider->scan_packages(60, 10 * 1024 * 1024, new \null_progress_trace()));
        $this->assertSame(0, $provider->scan_packages(60, 10 * 1024 * 1024, new \null_progress_trace()));
        $this->assertSame(1, (int) $DB->get_field('tool_extdeps_coursestate', 'dirty', ['courseid' => $course->id]));

        scanner::scan_course($course->id);
        $sql = "SELECT o.source FROM {tool_extdeps_occurrence} o
                  JOIN {tool_extdeps_resource} r ON r.id = o.resourceid
                 WHERE r.provider = 'youtube' AND r.resourcekey = 'AAAAAAAAAAA' AND o.cmid = ?";
        $this->assertSame('scorm.content', $DB->get_field_sql($sql, [$scorm->cmid]));
    }

    /**
     * External package addresses.
     *
     * @return void
     */
    public function test_classify_http(): void {
        $this->assertSame(status::OK, provider::classify_http(200)->status);
        $this->assertSame(status::REMOVED, provider::classify_http(404)->status);
        $this->assertSame(status::UNAVAILABLE, provider::classify_http(0, 'connect: Could not resolve host')->status);
        $this->assertSame(status::TEMPORARY, provider::classify_http(0, 'connect: timed out')->status);
    }
}
