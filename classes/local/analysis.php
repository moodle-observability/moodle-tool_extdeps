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

/**
 * On demand analysis: re-reads the content of a scope and checks its dependencies now.
 *
 * Used to confirm that a fix worked. It re-extracts before checking (a replaced link becomes removed_from_content),
 * ignores the schedule, records the evidence of each verdict and, in dry run, changes nothing.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class analysis {
    /** Scopes. */
    public const SCOPES = ['resource', 'cm', 'course', 'provider', 'site'];

    /** Scopes small enough to run synchronously. */
    public const SYNC_SCOPES = ['resource', 'cm'];

    /** Maximum resources checked synchronously. */
    public const MAX_SYNC = 20;

    /** The provider confirmed that the dependency works. */
    public const CONFIRMED_OK = 'confirmed_ok';
    /** Definitive evidence of failure, or the same failure already confirmed. */
    public const STILL_BROKEN = 'still_broken';
    /** Failure seen, not yet confirmed. */
    public const SUSPECTED = 'suspected';
    /** Temporary error, unknown answer or provider paused: nothing changes. */
    public const INCONCLUSIVE = 'inconclusive';
    /** Two methods disagreed (strict mode). */
    public const DIVERGENT = 'divergent';
    /** The reference is no longer in the content. */
    public const REMOVED_FROM_CONTENT = 'removed_from_content';

    /**
     * Verdicts in display order.
     *
     * @return string[]
     */
    public static function verdicts(): array {
        return [self::CONFIRMED_OK, self::REMOVED_FROM_CONTENT, self::STILL_BROKEN, self::SUSPECTED, self::DIVERGENT,
            self::INCONCLUSIVE];
    }

    /**
     * Creates a run.
     *
     * @param string $scope Scope.
     * @param int $scopeid Id of the resource, course module or course (0 for provider and site).
     * @param string|null $scopename Provider name when the scope is a provider.
     * @param int $userid Who asked.
     * @param bool $dryrun Report only, change nothing.
     * @param bool $strict Use two methods when the provider has them.
     * @return \stdClass
     */
    public static function create_run(
        string $scope,
        int $scopeid,
        ?string $scopename,
        int $userid,
        bool $dryrun = false,
        bool $strict = false
    ): \stdClass {
        global $DB;
        if (!in_array($scope, self::SCOPES, true)) {
            throw new \coding_exception('Invalid scope: ' . $scope);
        }
        if ($scope === 'provider' && ($scopename === null || registry::get($scopename) === null)) {
            throw new \moodle_exception('errorunknownprovider', 'tool_extdeps', '', s((string) $scopename));
        }
        $run = (object) ['scope' => $scope, 'scopeid' => $scopeid, 'scopename' => $scopename, 'userid' => $userid,
            'dryrun' => (int) $dryrun, 'strict' => (int) $strict, 'status' => 'queued', 'timequeued' => time()];
        $run->id = $DB->insert_record('tool_extdeps_run', $run);
        return $DB->get_record('tool_extdeps_run', ['id' => $run->id], '*', MUST_EXIST);
    }

    /**
     * Runs an analysis.
     *
     * @param \stdClass $run Record of tool_extdeps_run.
     * @param \progress_trace|null $trace Output.
     * @return \stdClass The run, finished.
     */
    public static function execute(\stdClass $run, ?\progress_trace $trace = null): \stdClass {
        global $DB;
        $trace = $trace ?? new \null_progress_trace();
        $run->status = 'running';
        $run->timestarted = time();
        $DB->update_record('tool_extdeps_run', $run);

        try {
            [$courseids, $filter] = self::scope($run);
            $before = self::stored_references($courseids, $filter);
            $after = [];
            foreach ($courseids as $courseid) {
                $start = microtime(true);
                $eligible = eligibility::course($courseid);
                $occurrences = $eligible ? scanner::collect($courseid) : [];
                if (!$run->dryrun) {
                    if ($eligible) {
                        scanner::save($courseid, $occurrences, $start);
                    } else {
                        scanner::forget_course($courseid, false);
                    }
                }
                foreach ($occurrences as $occurrence) {
                    if (self::matches($occurrence, $filter)) {
                        $ref = $occurrence->reference;
                        $after[$ref->provider . '|' . $ref->key] = $ref;
                    }
                }
            }

            $summary = array_fill_keys(self::verdicts(), 0);
            $run->total = count($after) + count(array_diff_key($before, $after));
            $run->processed = 0;
            $DB->update_record('tool_extdeps_run', $run);

            foreach (array_diff_key($before, $after) as $resource) {
                self::add_item(
                    $run,
                    $resource,
                    self::REMOVED_FROM_CONTENT,
                    $resource->confirmedstatus,
                    ['reason' => 'reference no longer found in the content']
                );
                $summary[self::REMOVED_FROM_CONTENT]++;
                if (!$run->dryrun) {
                    notifier::removed_from_courses($resource, $courseids);
                }
            }

            http::get_stats(true);
            self::check_references($run, $after, $summary, $trace);
            // Time spent waiting for the providers, to tell a slow provider from the rate limit.
            $summary['http'] = http::get_stats();

            $run->status = 'done';
            $run->summary = json_encode($summary);
        } catch (\Throwable $e) {
            $run->status = 'failed';
            $run->summary = json_encode(['error' => http::sanitise($e->getMessage())]);
            $trace->output('Failed: ' . $e->getMessage());
        }
        $run->timefinished = time();
        $DB->update_record('tool_extdeps_run', $run);
        return $run;
    }

    /**
     * Checks the references found and records the verdicts.
     *
     * @param \stdClass $run Run.
     * @param reference[] $references References keyed by provider|key.
     * @param array $summary Counters per verdict, updated.
     * @param \progress_trace $trace Output.
     * @return void
     */
    private static function check_references(
        \stdClass $run,
        array $references,
        array &$summary,
        \progress_trace $trace
    ): void {
        global $DB;
        $byprovider = [];
        foreach ($references as $reference) {
            $byprovider[$reference->provider][] = $reference;
        }
        foreach ($byprovider as $name => $refs) {
            $provider = registry::get($name);
            foreach (array_chunk($refs, verifier::BATCH) as $chunk) {
                $resources = [];
                $tempid = -1;
                foreach ($chunk as $reference) {
                    $resource = $DB->get_record(
                        'tool_extdeps_resource',
                        ['provider' => $reference->provider, 'resourcekey' => $reference->key]
                    );
                    if (!$resource) {
                        $resource = (object) ['id' => $tempid--, 'provider' => $reference->provider,
                            'resourcekey' => $reference->key, 'url' => $reference->url, 'status' => status::PENDING,
                            'confirmedstatus' => status::PENDING, 'candidatestatus' => null, 'failcount' => 0];
                    }
                    $resources[$resource->id] = $resource;
                }
                if (verifier::is_paused($name)) {
                    $results = array_map(
                        fn() => new result(status::TEMPORARY, 'none', ['reason' => 'provider paused']),
                        $resources
                    );
                } else {
                    $results = $provider->verify($resources, (bool) $run->strict);
                }
                $retryafter = 0;
                foreach ($resources as $id => $resource) {
                    $result = $results[$id] ?? new result(status::UNKNOWN, 'none', ['reason' => 'no answer']);
                    $retryafter = max($retryafter, $result->retryafter);
                    $old = $resource->confirmedstatus;
                    $changed = null;
                    if (!$run->dryrun && $id > 0) {
                        $changed = state::apply($resource, $result, $provider);
                    }
                    $verdict = self::verdict($result, $old, $changed);
                    self::add_item($run, $resource, $verdict, $result->status, json_decode($result->to_json(), true));
                    $summary[$verdict]++;
                    $trace->output("{$resource->provider} {$resource->resourcekey}: {$verdict} ({$result->status})");
                }
                if ($retryafter > 0) {
                    verifier::pause($name, $retryafter);
                }
            }
        }
    }

    /**
     * Verdict of one check.
     *
     * @param result $result Result.
     * @param string $old Confirmed status before the check.
     * @param string|null $changed New confirmed status, when it changed.
     * @return string
     */
    public static function verdict(result $result, string $old, ?string $changed): string {
        if ($result->divergent) {
            return self::DIVERGENT;
        }
        if (status::is_inconclusive($result->status)) {
            return self::INCONCLUSIVE;
        }
        if ($result->status === status::OK) {
            return self::CONFIRMED_OK;
        }
        if ($result->definitive || $old === $result->status || $changed === $result->status) {
            return self::STILL_BROKEN;
        }
        return self::SUSPECTED;
    }

    /**
     * Courses of the scope and the filter that selects the references inside them.
     *
     * @param \stdClass $run Run.
     * @return array{0: int[], 1: array}
     */
    private static function scope(\stdClass $run): array {
        global $DB;
        switch ($run->scope) {
            case 'resource':
                $resource = $DB->get_record('tool_extdeps_resource', ['id' => $run->scopeid], '*', MUST_EXIST);
                $courseids = $DB->get_fieldset_select(
                    'tool_extdeps_occurrence',
                    'DISTINCT courseid',
                    'resourceid = ?',
                    [$resource->id]
                );
                return [array_map('intval', $courseids),
                    ['provider' => $resource->provider, 'key' => $resource->resourcekey]];
            case 'cm':
                $courseid = (int) $DB->get_field('course_modules', 'course', ['id' => $run->scopeid], MUST_EXIST);
                return [[$courseid], ['cmid' => (int) $run->scopeid]];
            case 'course':
                return [[(int) $run->scopeid], []];
            case 'provider':
                $sql = "SELECT DISTINCT o.courseid
                          FROM {tool_extdeps_occurrence} o
                          JOIN {tool_extdeps_resource} r ON r.id = o.resourceid
                         WHERE r.provider = :provider";
                return [array_map('intval', $DB->get_fieldset_sql($sql, ['provider' => $run->scopename])),
                    ['provider' => $run->scopename]];
            default:
                return [array_map('intval', $DB->get_fieldset_select('course', 'id', '1 = 1', [])), []];
        }
    }

    /**
     * Whether an occurrence belongs to the filter.
     *
     * @param occurrence $occurrence Occurrence.
     * @param array $filter Filter: provider, key, cmid.
     * @return bool
     */
    private static function matches(occurrence $occurrence, array $filter): bool {
        if (isset($filter['cmid']) && $occurrence->cmid !== $filter['cmid']) {
            return false;
        }
        if (isset($filter['provider']) && $occurrence->reference->provider !== $filter['provider']) {
            return false;
        }
        if (isset($filter['key']) && $occurrence->reference->key !== $filter['key']) {
            return false;
        }
        return true;
    }

    /**
     * Resources stored for the courses of the scope before the analysis.
     *
     * @param int[] $courseids Courses.
     * @param array $filter Filter.
     * @return \stdClass[] Keyed by provider|key.
     */
    private static function stored_references(array $courseids, array $filter): array {
        global $DB;
        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $where = "o.courseid {$insql}";
        if (isset($filter['cmid'])) {
            $where .= ' AND o.cmid = :cmid';
            $params['cmid'] = $filter['cmid'];
        }
        if (isset($filter['provider'])) {
            $where .= ' AND r.provider = :provider';
            $params['provider'] = $filter['provider'];
        }
        if (isset($filter['key'])) {
            $where .= ' AND r.resourcekey = :resourcekey';
            $params['resourcekey'] = $filter['key'];
        }
        $sql = "SELECT DISTINCT r.*
                  FROM {tool_extdeps_resource} r
                  JOIN {tool_extdeps_occurrence} o ON o.resourceid = r.id
                 WHERE {$where}";
        $resources = [];
        foreach ($DB->get_records_sql($sql, $params) as $resource) {
            $resources[$resource->provider . '|' . $resource->resourcekey] = $resource;
        }
        return $resources;
    }

    /**
     * Records the verdict of one resource.
     *
     * @param \stdClass $run Run.
     * @param \stdClass $resource Resource (negative id when not stored).
     * @param string $verdict Verdict.
     * @param string $status Status observed.
     * @param array $evidence Evidence.
     * @return void
     */
    private static function add_item(
        \stdClass $run,
        \stdClass $resource,
        string $verdict,
        string $status,
        array $evidence
    ): void {
        global $DB;
        $DB->insert_record('tool_extdeps_runitem', (object) [
            'runid' => $run->id,
            'resourceid' => max(0, (int) $resource->id),
            'provider' => $resource->provider,
            'resourcekey' => $resource->resourcekey,
            'verdict' => $verdict,
            'status' => $status,
            'evidence' => json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $run->processed = (int) $run->processed + 1;
        $DB->set_field('tool_extdeps_run', 'processed', $run->processed, ['id' => $run->id]);
    }

    /**
     * Compares the verdicts of a run with a list of dependencies known to be broken.
     *
     * The CSV needs a header with either provider and key, or video_id (YouTube) and optionally plataforma.
     *
     * @param int $runid Run id.
     * @param string $csv CSV content.
     * @return array{falsepositives: string[], falsenegatives: string[], notfound: string[], truepositives: int,
     *     precision: float, recall: float}
     */
    public static function compare(int $runid, string $csv): array {
        global $DB;
        $expected = self::read_expected($csv);
        $items = $DB->get_records('tool_extdeps_runitem', ['runid' => $runid], '', 'id, provider, resourcekey, verdict');
        $found = [];
        $seen = [];
        foreach ($items as $item) {
            $key = $item->provider . ':' . $item->resourcekey;
            $seen[$key] = true;
            if (in_array($item->verdict, [self::STILL_BROKEN, self::SUSPECTED], true)) {
                $found[$key] = true;
            }
        }
        $truepositives = count(array_intersect_key($found, $expected));
        return [
            'falsepositives' => array_keys(array_diff_key($found, $expected)),
            'falsenegatives' => array_keys(array_diff_key(array_intersect_key($expected, $seen), $found)),
            'notfound' => array_keys(array_diff_key($expected, $seen)),
            'truepositives' => $truepositives,
            'precision' => $found ? round($truepositives / count($found), 4) : 1.0,
            'recall' => $expected ? round($truepositives / count($expected), 4) : 1.0,
        ];
    }

    /**
     * Reads the expected broken dependencies from a CSV.
     *
     * @param string $csv CSV content.
     * @return array<string, true> Keyed by provider:key.
     */
    private static function read_expected(string $csv): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        $header = array_map(fn($h) => strtolower(trim($h)), str_getcsv((string) array_shift($lines), ',', '"', '\\'));
        $keycol = array_search('key', $header, true);
        if ($keycol === false) {
            $keycol = array_search('video_id', $header, true);
        }
        if ($keycol === false) {
            throw new \moodle_exception('errorcomparecsv', 'tool_extdeps');
        }
        $providercol = array_search('provider', $header, true);
        if ($providercol === false) {
            $providercol = array_search('plataforma', $header, true);
        }
        $expected = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line, ',', '"', '\\');
            $provider = $providercol !== false ? strtolower(trim($row[$providercol] ?? '')) : 'youtube';
            $key = trim($row[$keycol] ?? '');
            if ($key !== '') {
                $expected[($provider ?: 'youtube') . ':' . $key] = true;
            }
        }
        return $expected;
    }
}
