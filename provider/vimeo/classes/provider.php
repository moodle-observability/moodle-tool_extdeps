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

namespace extdepsprovider_vimeo;

use tool_extdeps\local\http;
use tool_extdeps\local\provider\base;
use tool_extdeps\local\reference;
use tool_extdeps\local\result;
use tool_extdeps\local\status;

/**
 * Vimeo videos, including unlisted videos with a privacy hash.
 *
 * Without a token, oEmbed is asked with the site as Referer (domain restricted embeds are evaluated by the Referer).
 * With a token, the API answers the privacy settings and is definitive; in strict mode both are used.
 *
 * @package    extdepsprovider_vimeo
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends base {
    /** @var string Pattern of the video addresses; group 1 is the id, group 2 the path hash. */
    private const PATTERN = '~(?:https?:)?//(?:www\.|player\.)?vimeo\.com/(?:video/|channels/[\w-]+/|groups/[\w-]+/videos/|'
        . 'album/\d+/video/|showcase/\d+/video/)?(\d{6,})(?![\d])(?:/([0-9a-f]{6,})(?![0-9a-z]))?([^"\'\s<>]*)~i';

    /**
     * Finds Vimeo videos in a text.
     *
     * @param string $text Text.
     * @return reference[]
     */
    public function extract_from_text(string $text): array {
        if (stripos($text, 'vimeo.com') === false) {
            return [];
        }
        preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER);
        $refs = [];
        foreach ($matches as $m) {
            $id = $m[1];
            $hash = $m[2] ?? '';
            if ($hash === '' && preg_match('~[?&](?:amp;)?h=([0-9a-f]{6,})~i', $m[3] ?? '', $h)) {
                $hash = $h[1];
            }
            $hash = strtolower($hash);
            $key = $hash !== '' ? $id . ':' . $hash : $id;
            $url = 'https://vimeo.com/' . $id . ($hash !== '' ? '/' . $hash : '');
            $refs[$key] = new reference('vimeo', $key, $url);
        }
        return array_values($refs);
    }

    /**
     * Checks videos.
     *
     * @param \stdClass[] $resources Resources keyed by id.
     * @param bool $strict Use a second method.
     * @return result[]
     */
    public function verify(array $resources, bool $strict = false): array {
        $token = trim((string) $this->get_setting('token', ''));
        $results = [];
        foreach ($resources as $id => $resource) {
            $primary = $token !== '' ? $this->verify_api($resource, $token) : $this->verify_oembed($resource);
            if (status::is_inconclusive($primary->status) && $token !== '') {
                $primary = $this->verify_oembed($resource);
            } else if ($strict && $token !== '' && !status::is_inconclusive($primary->status)) {
                $second = $this->verify_oembed($resource);
                if (!status::is_inconclusive($second->status)) {
                    $primary = $primary->with_second_opinion($second);
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
     * Checks a video through oEmbed.
     *
     * @param \stdClass $resource Resource.
     * @return result
     */
    private function verify_oembed(\stdClass $resource): result {
        global $CFG;
        $this->throttle();
        $url = 'https://vimeo.com/api/oembed.json?url=' . rawurlencode($resource->url);
        $answer = http::get($url, $this->get_timeout(), ['Referer' => $CFG->wwwroot . '/']);
        $response = $answer['response'];
        if ($response === null) {
            return new result(status::TEMPORARY, 'oembed', ['error' => $answer['error']]);
        }
        $body = $response->getStatusCode() === 200 ? json_decode((string) $response->getBody(), true) : null;
        $result = self::classify_oembed(
            $response->getStatusCode(),
            is_array($body) ? $body : null,
            $response->getStatusCode() === 429 ? http::retry_after($response, 900) : 0
        );
        if ($response->getStatusCode() === 404) {
            // OEmbed answers 404 for deleted, private and password protected videos: the player tells them apart.
            $result = self::classify_player($this->player_status($resource), $result);
        }
        return $result;
    }

    /**
     * HTTP code of the embedded player, asked with the site as Referer.
     *
     * @param \stdClass $resource Resource.
     * @return int|null Code, or null when the request failed.
     */
    private function player_status(\stdClass $resource): ?int {
        global $CFG;
        $this->throttle();
        [$id, $hash] = array_pad(explode(':', $resource->resourcekey, 2), 2, '');
        $url = 'https://player.vimeo.com/video/' . preg_replace('~\D~', '', $id)
            . ($hash !== '' ? '?h=' . preg_replace('~[^0-9a-f]~i', '', $hash) : '');
        $answer = http::get($url, $this->get_timeout(), ['Referer' => $CFG->wwwroot . '/']);
        return $answer['response'] === null ? null : $answer['response']->getStatusCode();
    }

    /**
     * Refines an oEmbed 404 with the answer of the player.
     *
     * @param int|null $code HTTP code of the player, or null when it could not be asked.
     * @param result $oembed Result from oEmbed.
     * @return result
     */
    public static function classify_player(?int $code, result $oembed): result {
        $evidence = ['http' => 404, 'player' => $code];
        if ($code === 404) {
            return new result(status::REMOVED, 'oembed+player', $evidence + ['reason' => 'deleted']);
        }
        if ($code === 401) {
            return new result(status::PRIVATE, 'oembed+player', $evidence + ['reason' => 'password protected']);
        }
        if ($code === 403) {
            return new result(status::PRIVATE, 'oembed+player', $evidence + ['reason' => 'private or embed restricted']);
        }
        if ($code === 200) {
            return new result(status::OK, 'oembed+player', $evidence);
        }
        return $oembed;
    }

    /**
     * Status from an oEmbed answer.
     *
     * @param int $code HTTP code.
     * @param array|null $body Decoded JSON body.
     * @param int $retryafter Seconds to wait on rate limit.
     * @return result
     */
    public static function classify_oembed(int $code, ?array $body, int $retryafter = 0): result {
        $evidence = ['http' => $code];
        if ($code === 200) {
            $domainstatus = (int) ($body['domain_status_code'] ?? 200);
            if ($domainstatus === 403) {
                return new result(status::EMBED_BLOCKED, 'oembed', $evidence + ['domain_status_code' => 403]);
            }
            return new result(status::OK, 'oembed', $evidence);
        }
        if ($code === 403) {
            return new result(status::EMBED_BLOCKED, 'oembed', $evidence + ['reason' => 'embedding not allowed here']);
        }
        if ($code === 404) {
            return new result(status::REMOVED, 'oembed', $evidence + ['reason' => 'removed or private']);
        }
        if ($code === 401) {
            return new result(status::PRIVATE, 'oembed', $evidence);
        }
        return new result(status::TEMPORARY, 'oembed', $evidence, false, $retryafter);
    }

    /**
     * Checks a video through the Vimeo API.
     *
     * @param \stdClass $resource Resource.
     * @param string $token Access token.
     * @return result
     */
    private function verify_api(\stdClass $resource, string $token): result {
        $this->throttle();
        // The key is the numeric id, optionally followed by :hash (hexadecimal), as the API expects.
        $path = preg_replace('~[^0-9a-f:]~i', '', $resource->resourcekey);
        $answer = http::get('https://api.vimeo.com/videos/' . $path
            . '?fields=status,privacy.view,privacy.embed', $this->get_timeout(), [
            'Authorization' => 'bearer ' . $token,
            'Accept' => 'application/vnd.vimeo.*+json;version=3.4',
        ]);
        $response = $answer['response'];
        if ($response === null) {
            return new result(status::TEMPORARY, 'api', ['error' => $answer['error']]);
        }
        $code = $response->getStatusCode();
        $body = $code === 200 ? json_decode((string) $response->getBody(), true) : null;
        return self::classify_api(
            $code,
            is_array($body) ? $body : null,
            $code === 429 ? http::retry_after($response, 900) : 0
        );
    }

    /**
     * Status from an API answer.
     *
     * @param int $code HTTP code.
     * @param array|null $body Decoded JSON body.
     * @param int $retryafter Seconds to wait on rate limit.
     * @return result
     */
    public static function classify_api(int $code, ?array $body, int $retryafter = 0): result {
        $evidence = ['http' => $code];
        if ($code === 404) {
            return new result(status::REMOVED, 'api', $evidence, true);
        }
        if ($code === 403) {
            return new result(status::PRIVATE, 'api', $evidence, true);
        }
        if ($code !== 200 || $body === null) {
            return new result(status::TEMPORARY, 'api', $evidence, false, $retryafter);
        }
        $view = $body['privacy']['view'] ?? null;
        $embed = $body['privacy']['embed'] ?? null;
        $state = $body['status'] ?? null;
        $evidence += ['privacy.view' => $view, 'privacy.embed' => $embed, 'status' => $state];
        if ($state !== null && $state !== 'available') {
            return new result(status::UNAVAILABLE, 'api', $evidence, true);
        }
        if (in_array($view, ['nobody', 'contacts', 'users', 'password'], true)) {
            return new result(status::PRIVATE, 'api', $evidence, true);
        }
        if ($embed === 'private') {
            return new result(status::EMBED_BLOCKED, 'api', $evidence, true);
        }
        return new result(status::OK, 'api', $evidence, true);
    }
}
