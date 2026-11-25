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

use extdepsprovider_eduplay\provider as eduplay;
use extdepsprovider_vimeo\provider as vimeo;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use tool_extdeps\local\http;
use tool_extdeps\local\status;

/**
 * Tests of the Vimeo and eduplay providers.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \extdepsprovider_vimeo\provider
 * @covers     \extdepsprovider_eduplay\provider
 */
final class vimeo_eduplay_test extends \advanced_testcase {
    /**
     * Prepares each test.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('curlsecurityblockedhosts', '');
        set_config('curlsecurityallowedport', '');
    }

    /**
     * Cleans the mock.
     *
     * @return void
     */
    protected function tearDown(): void {
        http::set_mock(null);
        parent::tearDown();
    }

    /**
     * Vimeo addresses and the keys expected.
     *
     * @return array
     */
    public static function vimeo_provider(): array {
        return [
            'player' => ['<iframe src="https://player.vimeo.com/video/123456789?badge=0"></iframe>', ['123456789']],
            'player with hash' => ['https://player.vimeo.com/video/123456789?h=abcdef1234&badge=0', ['123456789:abcdef1234']],
            'page' => ['https://vimeo.com/123456789', ['123456789']],
            'page with hash' => ['https://vimeo.com/123456789/abcdef1234', ['123456789:abcdef1234']],
            'channel' => ['https://vimeo.com/channels/staffpicks/123456789', ['123456789']],
            'short id' => ['https://vimeo.com/12345', []],
            'other host' => ['https://notvimeo.example/123456789', []],
        ];
    }

    /**
     * Vimeo extraction.
     *
     * @dataProvider vimeo_provider
     * @param string $text Text.
     * @param string[] $expected Keys.
     * @return void
     */
    public function test_vimeo_extract(string $text, array $expected): void {
        $this->assertSame($expected, array_map(fn($r) => $r->key, (new vimeo())->extract_from_text($text)));
    }

    /**
     * Vimeo classification.
     *
     * @return void
     */
    public function test_vimeo_classify(): void {
        $this->assertSame(status::OK, vimeo::classify_oembed(200, ['title' => 'x'])->status);
        $this->assertSame(status::EMBED_BLOCKED, vimeo::classify_oembed(200, ['domain_status_code' => 403])->status);
        $this->assertSame(status::EMBED_BLOCKED, vimeo::classify_oembed(403, null)->status);
        $this->assertSame(status::REMOVED, vimeo::classify_oembed(404, null)->status);
        $this->assertSame(status::TEMPORARY, vimeo::classify_oembed(503, null)->status);
        $this->assertSame(900, vimeo::classify_oembed(429, null, 900)->retryafter);

        $this->assertSame(status::REMOVED, vimeo::classify_api(404, null)->status);
        $this->assertTrue(vimeo::classify_api(404, null)->definitive);
        $ok = ['status' => 'available', 'privacy' => ['view' => 'unlisted', 'embed' => 'public']];
        $this->assertSame(status::OK, vimeo::classify_api(200, $ok)->status);
        $private = ['status' => 'available', 'privacy' => ['view' => 'nobody', 'embed' => 'public']];
        $this->assertSame(status::PRIVATE, vimeo::classify_api(200, $private)->status);
        $noembed = ['status' => 'available', 'privacy' => ['view' => 'anybody', 'embed' => 'private']];
        $this->assertSame(status::EMBED_BLOCKED, vimeo::classify_api(200, $noembed)->status);
    }

    /**
     * Vimeo verification sends the site as Referer.
     *
     * @return void
     */
    public function test_vimeo_verify_referer(): void {
        global $CFG;
        http::set_mock(new MockHandler([new Response(200, [], '{"title":"x"}')]));
        $resources = [5 => (object) ['id' => 5, 'resourcekey' => '123456789:abcdef1234',
            'url' => 'https://vimeo.com/123456789/abcdef1234']];
        $results = (new vimeo())->verify($resources);
        $this->assertSame(status::OK, $results[5]->status);
        $request = http::get_history()[0];
        $this->assertSame($CFG->wwwroot . '/', $request['headers']['Referer']);
        $this->assertStringContainsString(rawurlencode('https://vimeo.com/123456789/abcdef1234'), $request['url']);
    }

    /**
     * An oEmbed 404 is refined by the player: deleted, password protected, private or playable.
     *
     * @return void
     */
    public function test_vimeo_classify_player(): void {
        $oembed = vimeo::classify_oembed(404, null);
        $this->assertSame(status::REMOVED, vimeo::classify_player(404, $oembed)->status);
        $this->assertSame(status::PRIVATE, vimeo::classify_player(401, $oembed)->status);
        $this->assertSame(status::PRIVATE, vimeo::classify_player(403, $oembed)->status);
        $this->assertSame(status::OK, vimeo::classify_player(200, $oembed)->status);
        $this->assertSame($oembed, vimeo::classify_player(null, $oembed));
        $this->assertSame($oembed, vimeo::classify_player(500, $oembed));
    }

    /**
     * Verification asks the player, with the site as Referer, only when oEmbed answers 404.
     *
     * @return void
     */
    public function test_vimeo_verify_player(): void {
        global $CFG;
        http::set_mock(new MockHandler([new Response(404), new Response(403)]));
        http::get_stats(true);
        $resources = [7 => (object) ['id' => 7, 'resourcekey' => '199209233', 'url' => 'https://vimeo.com/199209233']];
        $results = (new vimeo())->verify($resources);
        $this->assertSame(status::PRIVATE, $results[7]->status);
        $this->assertSame('oembed+player', $results[7]->method);
        $history = http::get_history();
        $this->assertCount(2, $history);
        $stats = http::get_stats(true);
        $this->assertSame(2, $stats['requests']);
        $this->assertGreaterThanOrEqual($stats['maxseconds'], $stats['seconds']);
        $this->assertSame(0, http::get_stats()['requests']);
        $this->assertSame('https://player.vimeo.com/video/199209233', $history[1]['url']);
        $this->assertSame($CFG->wwwroot . '/', $history[1]['headers']['Referer']);

        // Hash of unlisted videos goes to the player too.
        http::set_mock(new MockHandler([new Response(404), new Response(404)]));
        $resources = [8 => (object) ['id' => 8, 'resourcekey' => '123456789:abcdef1234',
            'url' => 'https://vimeo.com/123456789/abcdef1234']];
        $this->assertSame(status::REMOVED, (new vimeo())->verify($resources)[8]->status);
        $this->assertSame('https://player.vimeo.com/video/123456789?h=abcdef1234', http::get_history()[1]['url']);
    }

    /**
     * Eduplay extraction ignores look-alike hosts.
     *
     * @return void
     */
    public function test_eduplay_extract(): void {
        $provider = new eduplay();
        $keys = fn(string $text) => array_map(fn($r) => $r->key, $provider->extract_from_text($text));
        $this->assertSame(['353479'], $keys('https://eduplay.rnp.br/app/video/353479'));
        $this->assertSame(['353479'], $keys('<iframe src="https://eduplay.rnp.br/app/video/embed/353479"></iframe>'));
        $this->assertSame([], $keys('https://eduplay.rnp.br@evil.example/app/video/1'));
        $this->assertSame([], $keys('https://evil.example/eduplay.rnp.br/app/video/1'));
    }

    /**
     * Eduplay classification.
     *
     * @return void
     */
    public function test_eduplay_classify(): void {
        $this->assertSame(status::OK, eduplay::classify(200)->status);
        $this->assertSame(status::REMOVED, eduplay::classify(404)->status);
        $this->assertSame(status::PRIVATE, eduplay::classify(302, 'https://eduplay.rnp.br/login?next=x')->status);
        $this->assertSame(status::TEMPORARY, eduplay::classify(500)->status);
    }
}
