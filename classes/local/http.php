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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP access for the providers, through the Moodle HTTP client (proxy and blocked hosts are respected).
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class http {
    /** @var MockHandler|null Handler used by the unit tests. */
    private static ?MockHandler $mock = null;

    /** @var array<int, array{method: string, url: string, headers: array}> Requests sent while a mock is set. */
    private static array $history = [];

    /** @var array{requests: int, seconds: float, maxseconds: float} Time spent in requests since the last reset. */
    private static array $stats = ['requests' => 0, 'seconds' => 0.0, 'maxseconds' => 0.0];

    /**
     * Sets a mock handler (unit tests only).
     *
     * @param MockHandler|null $mock Handler, or null to go back to the network.
     * @return void
     */
    public static function set_mock(?MockHandler $mock): void {
        self::$mock = $mock;
        self::$history = [];
    }

    /**
     * Requests sent while a mock handler was set.
     *
     * @return array
     */
    public static function get_history(): array {
        return self::$history;
    }

    /**
     * Sends a GET request and never throws: network errors come back as an error description.
     *
     * @param string $url URL.
     * @param int $timeout Timeout in seconds.
     * @param array $headers Extra headers.
     * @param bool $followredirects Whether to follow redirects.
     * @return array{response: ResponseInterface|null, error: string|null}
     */
    public static function get(string $url, int $timeout, array $headers = [], bool $followredirects = true): array {
        $options = [
            'timeout' => $timeout,
            'connect_timeout' => min($timeout, 10),
            'http_errors' => false,
            'allow_redirects' => $followredirects ? ['max' => 5] : false,
            'headers' => $headers,
        ];
        if (self::$mock !== null) {
            $options['mock'] = self::$mock;
            self::$history[] = ['method' => 'GET', 'url' => $url, 'headers' => $headers];
        }
        $start = microtime(true);
        try {
            $client = new \core\http_client($options);
            return ['response' => $client->request('GET', $url), 'error' => null];
        } catch (ConnectException $e) {
            return ['response' => null, 'error' => 'connect: ' . self::sanitise($e->getMessage())];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return ['response' => $e->getResponse(), 'error' => null];
            }
            return ['response' => null, 'error' => 'request: ' . self::sanitise($e->getMessage())];
        } catch (\Throwable $e) {
            return ['response' => null, 'error' => 'error: ' . self::sanitise($e->getMessage())];
        } finally {
            $elapsed = microtime(true) - $start;
            self::$stats['requests']++;
            self::$stats['seconds'] += $elapsed;
            self::$stats['maxseconds'] = max(self::$stats['maxseconds'], $elapsed);
        }
    }

    /**
     * Time spent in requests since the last reset, rounded to the millisecond.
     *
     * @param bool $reset Start counting again.
     * @return array{requests: int, seconds: float, maxseconds: float}
     */
    public static function get_stats(bool $reset = false): array {
        $stats = [
            'requests' => self::$stats['requests'],
            'seconds' => round(self::$stats['seconds'], 3),
            'maxseconds' => round(self::$stats['maxseconds'], 3),
        ];
        if ($reset) {
            self::$stats = ['requests' => 0, 'seconds' => 0.0, 'maxseconds' => 0.0];
        }
        return $stats;
    }

    /**
     * Removes query strings (they may carry API keys) from an error message.
     *
     * @param string $message Message.
     * @return string
     */
    public static function sanitise(string $message): string {
        $message = preg_replace('~(https?://[^\s?"\']+)\?[^\s"\']*~i', '$1?…', $message);
        return \core_text::substr($message, 0, 300);
    }

    /**
     * Seconds asked by a Retry-After header, or a default.
     *
     * @param ResponseInterface $response Response.
     * @param int $default Default seconds.
     * @return int
     */
    public static function retry_after(ResponseInterface $response, int $default = 300): int {
        $value = trim($response->getHeaderLine('Retry-After'));
        if ($value !== '' && ctype_digit($value)) {
            return max(60, (int) $value);
        }
        return $default;
    }
}
