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

namespace extdepsprovider_youtube;

use tool_extdeps\local\http;
use tool_extdeps\local\provider\base;
use tool_extdeps\local\reference;
use tool_extdeps\local\result;
use tool_extdeps\local\status;

/**
 * YouTube videos: embed, watch, youtu.be, shorts and live addresses.
 *
 * With a YouTube Data API key the status comes from videos.list (50 videos per request, definitive). Without a key,
 * the watch page is read (playabilityStatus); in strict mode the oEmbed endpoint is the second opinion.
 *
 * @package    extdepsprovider_youtube
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends base {
    /** @var string Pattern of the video addresses; group 1 is the id. */
    private const PATTERN = '~(?:https?:)?//(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com/(?:embed/|v/|shorts/|live/|'
        . 'watch/?\?(?:[^"\'\s<>]*?&)?v=)|youtu\.be/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])~i';

    /**
     * Finds YouTube videos in a text.
     *
     * @param string $text Text.
     * @return reference[]
     */
    public function extract_from_text(string $text): array {
        if (stripos($text, 'youtu') === false) {
            return [];
        }
        preg_match_all(self::PATTERN, $text, $matches);
        $refs = [];
        foreach (array_unique($matches[1]) as $id) {
            $refs[] = new reference('youtube', $id, 'https://www.youtube.com/watch?v=' . $id);
        }
        return $refs;
    }

    /**
     * Checks videos.
     *
     * @param \stdClass[] $resources Resources keyed by id.
     * @param bool $strict Use a second method.
     * @return result[]
     */
    public function verify(array $resources, bool $strict = false): array {
        $apikey = trim((string) $this->get_setting('apikey', ''));
        $results = $apikey !== '' ? $this->verify_api($resources, $apikey) : [];
        foreach ($resources as $id => $resource) {
            $primary = $results[$id] ?? null;
            if ($primary === null || status::is_inconclusive($primary->status)) {
                $primary = $this->verify_page($resource->resourcekey);
            }
            if ($strict && !status::is_inconclusive($primary->status)) {
                $second = $primary->method === 'api' ? $this->verify_page($resource->resourcekey)
                    : $this->verify_oembed($resource->resourcekey);
                if (!status::is_inconclusive($second->status)) {
                    // OEmbed answers 200 for age or region restricted videos: that is not a disagreement.
                    $primary = $primary->with_second_opinion($second, [status::RESTRICTED, status::REGION_BLOCKED]);
                }
            }
            $results[$id] = $primary;
            if ($primary->retryafter > 0) {
                break;
            }
        }
        return $results;
    }

    /**
     * Checks videos with the YouTube Data API.
     *
     * @param \stdClass[] $resources Resources keyed by id.
     * @param string $apikey API key.
     * @return result[]
     */
    private function verify_api(array $resources, string $apikey): array {
        $results = [];
        foreach (array_chunk($resources, 50, true) as $chunk) {
            $ids = array_map(fn($r) => $r->resourcekey, $chunk);
            $this->throttle();
            $url = 'https://www.googleapis.com/youtube/v3/videos?part=status,contentDetails&id=' . implode(',', $ids)
                . '&key=' . rawurlencode($apikey);
            $answer = http::get($url, $this->get_timeout());
            $response = $answer['response'];
            if ($response === null || $response->getStatusCode() !== 200) {
                $code = $response ? $response->getStatusCode() : 0;
                $retry = $code === 429 || $code === 403 ? http::retry_after($response, 3600) : 0;
                foreach ($chunk as $id => $resource) {
                    $results[$id] = new result(
                        status::TEMPORARY,
                        'api',
                        ['http' => $code, 'error' => $answer['error']],
                        false,
                        $retry
                    );
                }
                continue;
            }
            $items = [];
            foreach (json_decode((string) $response->getBody(), true)['items'] ?? [] as $item) {
                $items[$item['id']] = $item;
            }
            foreach ($chunk as $id => $resource) {
                $results[$id] = self::classify_api($items[$resource->resourcekey] ?? null);
            }
        }
        return $results;
    }

    /**
     * Status from an item of videos.list.
     *
     * @param array|null $item Item, or null when the video is not returned.
     * @return result
     */
    public static function classify_api(?array $item): result {
        if ($item === null) {
            return new result(status::REMOVED, 'api', ['reason' => 'not returned by videos.list'], true);
        }
        $st = $item['status'] ?? [];
        $evidence = ['privacyStatus' => $st['privacyStatus'] ?? null, 'uploadStatus' => $st['uploadStatus'] ?? null,
            'embeddable' => $st['embeddable'] ?? null];
        if (in_array($st['uploadStatus'] ?? '', ['rejected', 'deleted', 'failed'], true)) {
            return new result(status::REMOVED, 'api', $evidence, true);
        }
        if (($st['privacyStatus'] ?? '') === 'private') {
            return new result(status::PRIVATE, 'api', $evidence, true);
        }
        if (($st['embeddable'] ?? true) === false) {
            return new result(status::EMBED_BLOCKED, 'api', $evidence, true);
        }
        $rating = $item['contentDetails']['contentRating']['ytRating'] ?? '';
        if ($rating === 'ytAgeRestricted') {
            return new result(status::RESTRICTED, 'api', $evidence + ['ytRating' => $rating], true);
        }
        $blocked = $item['contentDetails']['regionRestriction']['blocked'] ?? [];
        $allowed = $item['contentDetails']['regionRestriction']['allowed'] ?? null;
        if (in_array('BR', $blocked, true) || (is_array($allowed) && !in_array('BR', $allowed, true))) {
            return new result(status::REGION_BLOCKED, 'api', $evidence + ['region' => 'BR'], true);
        }
        return new result(status::OK, 'api', $evidence, true);
    }

    /**
     * Checks a video by reading its watch page.
     *
     * @param string $videoid Video id.
     * @return result
     */
    private function verify_page(string $videoid): result {
        $this->throttle();
        $answer = http::get('https://www.youtube.com/watch?v=' . $videoid . '&hl=en&gl=BR', $this->get_timeout(), [
            'Accept-Language' => 'en-US,en;q=0.8',
            'Cookie' => 'CONSENT=YES+cb; SOCS=CAI',
            'User-Agent' => 'Mozilla/5.0 (compatible; MoodleExternalDependencies/1.0)',
        ]);
        $response = $answer['response'];
        if ($response === null) {
            return new result(status::TEMPORARY, 'page', ['error' => $answer['error']]);
        }
        $code = $response->getStatusCode();
        if ($code === 429) {
            return new result(status::TEMPORARY, 'page', ['http' => $code], false, http::retry_after($response, 1800));
        }
        if ($code !== 200) {
            return new result($code >= 500 ? status::TEMPORARY : status::UNKNOWN, 'page', ['http' => $code]);
        }
        return self::classify_page((string) $response->getBody());
    }

    /**
     * Status from the watch page HTML.
     *
     * @param string $html Page.
     * @return result
     */
    public static function classify_page(string $html): result {
        if (!preg_match('~"playabilityStatus":\{"status":"([A-Z_]+)"(.{0,2000})~s', $html, $m)) {
            if (str_contains($html, 'consent.youtube.com') || str_contains($html, 'www.google.com/sorry')) {
                return new result(status::TEMPORARY, 'page', ['reason' => 'consent or captcha page'], false, 3600);
            }
            return new result(status::UNKNOWN, 'page', ['reason' => 'playabilityStatus not found']);
        }
        $playability = $m[1];
        $reason = '';
        if (preg_match('~^,"reason":"((?:[^"\\\\]|\\\\.)*)"~', $m[2], $r)) {
            $reason = (string) json_decode('"' . $r[1] . '"');
        }
        $embeddable = !preg_match('~"playableInEmbed":false~', $m[2]);
        $evidence = ['playabilityStatus' => $playability, 'reason' => \core_text::substr($reason, 0, 200),
            'playableInEmbed' => $embeddable];
        $lower = \core_text::strtolower($reason);
        switch ($playability) {
            case 'OK':
                return new result($embeddable ? status::OK : status::EMBED_BLOCKED, 'page', $evidence);
            case 'LOGIN_REQUIRED':
                if (str_contains($lower, 'private')) {
                    return new result(status::PRIVATE, 'page', $evidence);
                }
                if (str_contains($lower, 'bot')) {
                    return new result(status::TEMPORARY, 'page', $evidence, false, 3600);
                }
                return new result(status::RESTRICTED, 'page', $evidence);
            case 'CONTENT_CHECK_REQUIRED':
            case 'AGE_CHECK_REQUIRED':
            case 'AGE_VERIFICATION_REQUIRED':
                return new result(status::RESTRICTED, 'page', $evidence);
            case 'ERROR':
                return new result(status::REMOVED, 'page', $evidence);
            case 'UNPLAYABLE':
                if (str_contains($lower, 'country')) {
                    return new result(status::REGION_BLOCKED, 'page', $evidence);
                }
                if (str_contains($lower, 'other websites') || str_contains($lower, 'embed')) {
                    return new result(status::EMBED_BLOCKED, 'page', $evidence);
                }
                return new result(status::UNAVAILABLE, 'page', $evidence);
            case 'LIVE_STREAM_OFFLINE':
                return new result(status::UNAVAILABLE, 'page', $evidence);
            default:
                return new result(status::UNKNOWN, 'page', $evidence);
        }
    }

    /**
     * Checks a video through oEmbed (second opinion).
     *
     * @param string $videoid Video id.
     * @return result
     */
    private function verify_oembed(string $videoid): result {
        $this->throttle();
        $url = 'https://www.youtube.com/oembed?format=json&url='
            . rawurlencode('https://www.youtube.com/watch?v=' . $videoid);
        $answer = http::get($url, $this->get_timeout());
        return self::classify_oembed($answer['response'] ? $answer['response']->getStatusCode() : 0, $answer['error']);
    }

    /**
     * Status from the oEmbed HTTP code.
     *
     * @param int $code HTTP code (0 on network error).
     * @param string|null $error Network error.
     * @return result
     */
    public static function classify_oembed(int $code, ?string $error = null): result {
        $evidence = ['http' => $code] + ($error ? ['error' => $error] : []);
        if ($code === 200) {
            return new result(status::OK, 'oembed', $evidence);
        }
        if ($code === 401 || $code === 403) {
            return new result(status::EMBED_BLOCKED, 'oembed', $evidence + ['reason' => 'private or not embeddable']);
        }
        if ($code === 404 || $code === 400) {
            return new result(status::REMOVED, 'oembed', $evidence);
        }
        return new result(status::TEMPORARY, 'oembed', $evidence, false, $code === 429 ? 1800 : 0);
    }
}
