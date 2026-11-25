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

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use tool_extdeps\local\analysis;
use tool_extdeps\local\http;
use tool_extdeps\local\scanner;
use tool_extdeps\local\status;
use tool_extdeps\local\verifier;

/**
 * Tests of the on demand analysis and of the scheduled verification.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_extdeps\local\analysis
 * @covers     \tool_extdeps\local\verifier
 */
final class analysis_test extends \advanced_testcase {
    /** @var string Watch page of an available video. */
    private const OK_PAGE = '{"playabilityStatus":{"status":"OK","playableInEmbed":true}}';

    /** @var string Watch page of a removed video. */
    private const REMOVED_PAGE = '{"playabilityStatus":{"status":"ERROR","reason":"Video unavailable"}}';

    /**
     * Prepares each test: only the YouTube provider is enabled.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('curlsecurityblockedhosts', '');
        set_config('curlsecurityallowedport', '');
        foreach (['vimeo', 'eduplay', 'scorm'] as $name) {
            set_config('enabled', 0, 'extdepsprovider_' . $name);
        }
        local\registry::reset();
    }

    /**
     * Cleans the mock.
     *
     * @return void
     */
    protected function tearDown(): void {
        http::set_mock(null);
        local\registry::reset();
        parent::tearDown();
    }

    /**
     * Verdicts of a run keyed by resource key.
     *
     * @param int $runid Run.
     * @return array
     */
    private function verdicts(int $runid): array {
        global $DB;
        return $DB->get_records_menu('tool_extdeps_runitem', ['runid' => $runid], 'resourcekey', 'resourcekey, verdict');
    }

    /**
     * Fixing an activity: the replaced video is removed from the content and the new one is confirmed.
     *
     * @return void
     */
    public function test_fix_confirmed(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id,
            'content' => 'https://youtu.be/AAAAAAAAAAA']);
        scanner::scan_course($course->id);
        $old = $DB->get_record('tool_extdeps_resource', ['resourcekey' => 'AAAAAAAAAAA']);
        $DB->set_field('tool_extdeps_resource', 'confirmedstatus', status::REMOVED, ['id' => $old->id]);

        // The editor replaces the video.
        $DB->set_field('page', 'content', 'https://youtu.be/BBBBBBBBBBB', ['id' => $page->id]);
        http::set_mock(new MockHandler([new Response(200, [], self::OK_PAGE)]));

        $run = analysis::create_run('cm', (int) $page->cmid, null, 2);
        $run = analysis::execute($run);
        $this->assertSame('done', $run->status);
        $this->assertSame(
            ['AAAAAAAAAAA' => analysis::REMOVED_FROM_CONTENT, 'BBBBBBBBBBB' => analysis::CONFIRMED_OK],
            $this->verdicts($run->id)
        );
        $new = $DB->get_record('tool_extdeps_resource', ['resourcekey' => 'BBBBBBBBBBB']);
        $this->assertSame(status::OK, $new->confirmedstatus);
        $this->assertFalse($DB->record_exists('tool_extdeps_occurrence', ['resourceid' => $old->id]));
    }

    /**
     * A dry run reports but changes nothing; a temporary error is inconclusive.
     *
     * @return void
     */
    public function test_dry_run_and_inconclusive(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['summary' => 'https://youtu.be/AAAAAAAAAAA '
            . 'https://youtu.be/BBBBBBBBBBB']);
        scanner::scan_course($course->id);
        $before = $DB->get_records('tool_extdeps_resource', null, 'id');

        http::set_mock(new MockHandler([new Response(200, [], self::REMOVED_PAGE), new Response(503)]));
        $run = analysis::execute(analysis::create_run('course', (int) $course->id, null, 2, true));
        $this->assertSame(
            ['AAAAAAAAAAA' => analysis::SUSPECTED, 'BBBBBBBBBBB' => analysis::INCONCLUSIVE],
            $this->verdicts($run->id)
        );
        $this->assertEquals($before, $DB->get_records('tool_extdeps_resource', null, 'id'));
    }

    /**
     * Comparison with a list of known broken videos.
     *
     * @return void
     */
    public function test_compare(): void {
        $course = $this->getDataGenerator()->create_course(['summary' => 'https://youtu.be/AAAAAAAAAAA '
            . 'https://youtu.be/BBBBBBBBBBB https://youtu.be/CCCCCCCCCCC']);
        scanner::scan_course($course->id);
        http::set_mock(new MockHandler([
            new Response(200, [], self::REMOVED_PAGE),
            new Response(200, [], self::REMOVED_PAGE),
            new Response(200, [], self::OK_PAGE),
        ]));
        $run = analysis::execute(analysis::create_run('course', (int) $course->id, null, 2, true));
        $csv = "video_id,plataforma\nAAAAAAAAAAA,YouTube\nCCCCCCCCCCC,YouTube\nZZZZZZZZZZZ,YouTube\n";
        $result = analysis::compare((int) $run->id, $csv);
        $this->assertSame(['youtube:BBBBBBBBBBB'], $result['falsepositives']);
        $this->assertSame(['youtube:CCCCCCCCCCC'], $result['falsenegatives']);
        $this->assertSame(['youtube:ZZZZZZZZZZZ'], $result['notfound']);
        $this->assertSame(1, $result['truepositives']);
        $this->assertEquals(0.5, $result['precision']);
    }

    /**
     * The scheduled verification checks due resources and pauses a provider on rate limits.
     *
     * @return void
     */
    public function test_verifier(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['summary' => 'https://youtu.be/AAAAAAAAAAA '
            . 'https://youtu.be/BBBBBBBBBBB']);
        scanner::scan_course($course->id);
        http::set_mock(new MockHandler([new Response(200, [], self::OK_PAGE), new Response(429, ['Retry-After' => '600'])]));
        $stats = verifier::run(60);
        $this->assertSame([status::OK => 1, status::TEMPORARY => 1], $stats['youtube']);
        $this->assertTrue(verifier::is_paused('youtube'));
        $this->assertSame(1, $DB->count_records('tool_extdeps_resource', ['confirmedstatus' => status::OK]));
        // Paused: nothing is sent.
        http::set_mock(new MockHandler([]));
        $this->assertSame([], verifier::run(60));
    }
}
