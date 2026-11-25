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

use tool_extdeps\local\provider\base;

/**
 * Checks the resources that are due, provider by provider, within a time budget.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class verifier {
    /** @var int Resources sent to a provider at once. */
    public const BATCH = 50;

    /**
     * Checks due resources.
     *
     * @param int $timelimit Seconds available, shared by the enabled providers.
     * @param \progress_trace|null $trace Output.
     * @return array<string, array<string, int>> Counters per provider and status.
     */
    public static function run(int $timelimit, ?\progress_trace $trace = null): array {
        $trace = $trace ?? new \null_progress_trace();
        $providers = array_filter(registry::enabled(), fn(base $provider): bool => !self::is_paused($provider->get_name()));
        $stats = [];
        if (!$providers) {
            return $stats;
        }
        $share = max(10, intdiv($timelimit, count($providers)));
        foreach ($providers as $name => $provider) {
            $deadline = microtime(true) + $share;
            $stats[$name] = [];
            http::get_stats(true);
            while (microtime(true) < $deadline) {
                $resources = self::due($name, self::BATCH);
                if (!$resources) {
                    break;
                }
                $retryafter = self::check($provider, $resources, $stats[$name]);
                if ($retryafter > 0) {
                    self::pause($name, $retryafter);
                    $trace->output("{$name}: paused for {$retryafter} s");
                    break;
                }
                if (count($resources) < self::BATCH) {
                    break;
                }
            }
            $trace->output($name . ': ' . json_encode($stats[$name]) . ' http ' . json_encode(http::get_stats()));
        }
        return $stats;
    }

    /**
     * Checks some resources of one provider and applies the results.
     *
     * @param base $provider Provider.
     * @param \stdClass[] $resources Resources keyed by id.
     * @param array $stats Counters per status, updated.
     * @param bool $strict Whether to use a second method.
     * @param bool $notify Whether to send alerts.
     * @return int Seconds the provider asked to wait, or 0.
     */
    public static function check(
        base $provider,
        array $resources,
        array &$stats,
        bool $strict = false,
        bool $notify = true
    ): int {
        $results = $provider->verify($resources, $strict);
        $retryafter = 0;
        foreach ($resources as $id => $resource) {
            $result = $results[$id] ?? new result(status::UNKNOWN, 'none', ['reason' => 'no answer']);
            state::apply($resource, $result, $provider, $notify);
            $stats[$result->status] = ($stats[$result->status] ?? 0) + 1;
            $retryafter = max($retryafter, $result->retryafter);
        }
        return $retryafter;
    }

    /**
     * Resources of a provider due for checking that are still used somewhere.
     *
     * @param string $provider Provider name.
     * @param int $limit Maximum.
     * @return \stdClass[]
     */
    public static function due(string $provider, int $limit): array {
        global $DB;
        $select = "provider = :provider AND nextcheck <= :now
                   AND EXISTS (SELECT 1 FROM {tool_extdeps_occurrence} o WHERE o.resourceid = {tool_extdeps_resource}.id)";
        return $DB->get_records_select(
            'tool_extdeps_resource',
            $select,
            ['provider' => $provider, 'now' => time()],
            'nextcheck ASC, id ASC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Whether a provider is paused after a rate limit answer.
     *
     * @param string $provider Provider name.
     * @return bool
     */
    public static function is_paused(string $provider): bool {
        return (int) get_config('tool_extdeps', 'pauseduntil_' . $provider) > time();
    }

    /**
     * Pauses a provider.
     *
     * @param string $provider Provider name.
     * @param int $seconds Seconds.
     * @return void
     */
    public static function pause(string $provider, int $seconds): void {
        set_config('pauseduntil_' . $provider, time() + min($seconds, 6 * HOURSECS), 'tool_extdeps');
    }
}
