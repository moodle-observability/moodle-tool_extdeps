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

namespace extdepsprovider_eduplay;

use tool_extdeps\local\http;
use tool_extdeps\local\provider\base;
use tool_extdeps\local\reference;
use tool_extdeps\local\result;
use tool_extdeps\local\status;

/**
 * Videos of eduplay (RNP).
 *
 * Basic check of the embed address: 404 means removed, a redirect to a login page means private.
 *
 * @package    extdepsprovider_eduplay
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends base {
    /** @var string Pattern of the video addresses (the host must come right after //); group 1 is the id. */
    private const PATTERN = '~(?:https?:)?//eduplay\.rnp\.br/(?:app|portal)/video/(?:embed/)?(\d+)(?!\d)~i';

    /**
     * Finds eduplay videos in a text.
     *
     * @param string $text Text.
     * @return reference[]
     */
    public function extract_from_text(string $text): array {
        if (stripos($text, 'eduplay') === false) {
            return [];
        }
        preg_match_all(self::PATTERN, $text, $matches);
        $refs = [];
        foreach (array_unique($matches[1]) as $id) {
            $refs[] = new reference('eduplay', $id, 'https://eduplay.rnp.br/app/video/embed/' . $id);
        }
        return $refs;
    }

    /**
     * Checks videos.
     *
     * @param \stdClass[] $resources Resources keyed by id.
     * @param bool $strict Not used: there is no second method.
     * @return result[]
     */
    public function verify(array $resources, bool $strict = false): array {
        $results = [];
        foreach ($resources as $id => $resource) {
            $this->throttle();
            $answer = http::get($resource->url, $this->get_timeout(), [], false);
            $response = $answer['response'];
            if ($response === null) {
                $results[$id] = new result(status::TEMPORARY, 'http', ['error' => $answer['error']]);
                continue;
            }
            $results[$id] = self::classify($response->getStatusCode(), $response->getHeaderLine('Location'));
            if ($results[$id]->retryafter > 0) {
                break;
            }
        }
        return $results;
    }

    /**
     * Status from the HTTP answer.
     *
     * @param int $code HTTP code.
     * @param string $location Location header of redirects.
     * @return result
     */
    public static function classify(int $code, string $location = ''): result {
        $evidence = ['http' => $code];
        if ($code >= 200 && $code < 300) {
            return new result(status::OK, 'http', $evidence);
        }
        if ($code >= 300 && $code < 400) {
            $evidence['location'] = preg_replace('~\?.*$~', '', $location);
            if (preg_match('~login|auth|signin~i', $location)) {
                return new result(status::PRIVATE, 'http', $evidence);
            }
            return new result(status::OK, 'http', $evidence);
        }
        if ($code === 404 || $code === 410) {
            return new result(status::REMOVED, 'http', $evidence);
        }
        if ($code === 401 || $code === 403) {
            return new result(status::PRIVATE, 'http', $evidence);
        }
        return new result(status::TEMPORARY, 'http', $evidence, false, $code === 429 ? 900 : 0);
    }
}
