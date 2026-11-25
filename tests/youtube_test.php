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

use extdepsprovider_youtube\provider;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use tool_extdeps\local\http;
use tool_extdeps\local\status;

/**
 * Tests of the YouTube provider.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \extdepsprovider_youtube\provider
 */
final class youtube_test extends \advanced_testcase {
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
     * Addresses and the ids expected.
     *
     * @return array
     */
    public static function extraction_provider(): array {
        return [
            'embed' => ['<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0"></iframe>', ['dQw4w9WgXcQ']],
            'nocookie' => ['<iframe src="//www.youtube-nocookie.com/embed/abcdefghijk"></iframe>', ['abcdefghijk']],
            'watch' => ['https://www.youtube.com/watch?v=AAAAAAAAAAA&t=10', ['AAAAAAAAAAA']],
            'watch other params' => ['https://youtube.com/watch?feature=share&v=BBBBBBBBBBB', ['BBBBBBBBBBB']],
            'short' => ['<a href="https://youtu.be/CCCCCCCCCCC">x</a>', ['CCCCCCCCCCC']],
            'shorts' => ['https://www.youtube.com/shorts/DDDDDDDDDDD', ['DDDDDDDDDDD']],
            'live' => ['https://www.youtube.com/live/EEEEEEEEEEE?si=x', ['EEEEEEEEEEE']],
            'mobile' => ['https://m.youtube.com/watch?v=FFFFFFFFFFF', ['FFFFFFFFFFF']],
            'repeated' => ['https://youtu.be/GGGGGGGGGGG https://youtu.be/GGGGGGGGGGG', ['GGGGGGGGGGG']],
            'two' => ['https://youtu.be/HHHHHHHHHHH and https://youtu.be/IIIIIIIIIII', ['HHHHHHHHHHH', 'IIIIIIIIIII']],
            'too long id' => ['https://youtu.be/JJJJJJJJJJJJ', []],
            'channel' => ['https://www.youtube.com/channel/UCabcdefghijklmnopqrstuv', []],
            'other host' => ['https://notyoutube.example/embed/KKKKKKKKKKK', []],
        ];
    }

    /**
     * Extraction.
     *
     * @dataProvider extraction_provider
     * @param string $text Text.
     * @param string[] $expected Ids.
     * @return void
     */
    public function test_extract(string $text, array $expected): void {
        $refs = (new provider())->extract_from_text($text);
        $this->assertSame($expected, array_map(fn($r) => $r->key, $refs));
        foreach ($refs as $ref) {
            $this->assertSame('youtube', $ref->provider);
            $this->assertSame('https://www.youtube.com/watch?v=' . $ref->key, $ref->url);
        }
    }

    /**
     * Watch pages and statuses.
     *
     * @return array
     */
    public static function page_provider(): array {
        return [
            'ok' => ['{"playabilityStatus":{"status":"OK","playableInEmbed":true}}', status::OK],
            'no embed' => ['{"playabilityStatus":{"status":"OK","playableInEmbed":false}}', status::EMBED_BLOCKED],
            'private' => ['{"playabilityStatus":{"status":"LOGIN_REQUIRED","reason":"This video is private"}}', status::PRIVATE],
            'age' => ['{"playabilityStatus":{"status":"LOGIN_REQUIRED","reason":"Sign in to confirm your age"}}',
                status::RESTRICTED],
            'bot' => ['{"playabilityStatus":{"status":"LOGIN_REQUIRED","reason":"Sign in to confirm you’re not a bot"}}',
                status::TEMPORARY],
            'removed' => ['{"playabilityStatus":{"status":"ERROR","reason":"Video unavailable"}}', status::REMOVED],
            'country' => ['{"playabilityStatus":{"status":"UNPLAYABLE","reason":"The uploader has not made this video '
                . 'available in your country"}}', status::REGION_BLOCKED],
            'embed disabled' => ['{"playabilityStatus":{"status":"UNPLAYABLE","reason":"Playback on other websites has been '
                . 'disabled by the video owner"}}', status::EMBED_BLOCKED],
            'consent' => ['<form action="https://consent.youtube.com/save">', status::TEMPORARY],
            'garbage' => ['<html>nothing here</html>', status::UNKNOWN],
        ];
    }

    /**
     * Classification of the watch page.
     *
     * @dataProvider page_provider
     * @param string $html Page.
     * @param string $expected Status.
     * @return void
     */
    public function test_classify_page(string $html, string $expected): void {
        $result = provider::classify_page($html);
        $this->assertSame($expected, $result->status);
        $this->assertFalse($result->definitive);
    }

    /**
     * Classification of the Data API items.
     *
     * @return void
     */
    public function test_classify_api(): void {
        $this->assertSame(status::REMOVED, provider::classify_api(null)->status);
        $ok = ['status' => ['privacyStatus' => 'unlisted', 'uploadStatus' => 'processed', 'embeddable' => true]];
        $this->assertSame(status::OK, provider::classify_api($ok)->status);
        $this->assertTrue(provider::classify_api($ok)->definitive);
        $private = ['status' => ['privacyStatus' => 'private', 'uploadStatus' => 'processed', 'embeddable' => true]];
        $this->assertSame(status::PRIVATE, provider::classify_api($private)->status);
        $noembed = ['status' => ['privacyStatus' => 'public', 'uploadStatus' => 'processed', 'embeddable' => false]];
        $this->assertSame(status::EMBED_BLOCKED, provider::classify_api($noembed)->status);
        $age = $ok + ['contentDetails' => ['contentRating' => ['ytRating' => 'ytAgeRestricted']]];
        $this->assertSame(status::RESTRICTED, provider::classify_api($age)->status);
        $region = $ok + ['contentDetails' => ['regionRestriction' => ['blocked' => ['BR', 'PT']]]];
        $this->assertSame(status::REGION_BLOCKED, provider::classify_api($region)->status);
        $deleted = ['status' => ['privacyStatus' => 'public', 'uploadStatus' => 'deleted']];
        $this->assertSame(status::REMOVED, provider::classify_api($deleted)->status);
    }

    /**
     * Verification without key reads the page; strict mode adds oEmbed and flags disagreement.
     *
     * @return void
     */
    public function test_verify_page_and_strict(): void {
        $resources = [
            1 => (object) ['id' => 1, 'resourcekey' => 'AAAAAAAAAAA', 'url' => 'https://www.youtube.com/watch?v=AAAAAAAAAAA'],
        ];
        http::set_mock(new MockHandler([
            new Response(200, [], '{"playabilityStatus":{"status":"OK","playableInEmbed":true}}'),
        ]));
        $results = (new provider())->verify($resources);
        $this->assertSame(status::OK, $results[1]->status);
        $this->assertSame('page', $results[1]->method);
        $this->assertStringContainsString('youtube.com/watch?v=AAAAAAAAAAA', http::get_history()[0]['url']);

        // The page says OK but oEmbed says the video cannot be embedded: divergent.
        http::set_mock(new MockHandler([
            new Response(200, [], '{"playabilityStatus":{"status":"OK","playableInEmbed":true}}'),
            new Response(401),
        ]));
        $results = (new provider())->verify($resources, true);
        $this->assertTrue($results[1]->divergent);

        // Age restricted videos answer 200 in oEmbed: not a disagreement.
        http::set_mock(new MockHandler([
            new Response(200, [], '{"playabilityStatus":{"status":"LOGIN_REQUIRED","reason":"Sign in to confirm your age"}}'),
            new Response(200, [], '{"title":"x"}'),
        ]));
        $results = (new provider())->verify($resources, true);
        $this->assertSame(status::RESTRICTED, $results[1]->status);
        $this->assertFalse($results[1]->divergent);
    }

    /**
     * Verification with the API, and the key never leaks into the evidence.
     *
     * @return void
     */
    public function test_verify_api(): void {
        set_config('apikey', 'SECRET-KEY', 'extdepsprovider_youtube');
        $resources = [
            1 => (object) ['id' => 1, 'resourcekey' => 'AAAAAAAAAAA', 'url' => 'x'],
            2 => (object) ['id' => 2, 'resourcekey' => 'BBBBBBBBBBB', 'url' => 'y'],
        ];
        $body = json_encode(['items' => [['id' => 'AAAAAAAAAAA', 'status' => ['privacyStatus' => 'public',
            'uploadStatus' => 'processed', 'embeddable' => true]]]]);
        http::set_mock(new MockHandler([new Response(200, [], $body)]));
        $results = (new provider())->verify($resources);
        $this->assertSame(status::OK, $results[1]->status);
        $this->assertSame(status::REMOVED, $results[2]->status);
        $this->assertTrue($results[2]->definitive);
        $this->assertCount(1, http::get_history());
        $this->assertStringNotContainsString('SECRET-KEY', $results[1]->to_json() . $results[2]->to_json());

        // Quota exceeded: temporary, with a pause.
        http::set_mock(new MockHandler([new Response(403, [], '{"error":{"message":"quota"}}')]));
        $results = (new provider())->verify([1 => $resources[1]]);
        $this->assertNotSame(status::OK, $results[1]->status);
    }
}
