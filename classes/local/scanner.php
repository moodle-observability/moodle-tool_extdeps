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

use tool_extdeps\local\source\text_sources;

/**
 * Finds the external dependencies of a course and stores where they are used.
 *
 * Read only for the course content: it never changes anything in the course.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scanner {
    /**
     * Finds the occurrences of a course without writing anything.
     *
     * @param int $courseid Course id.
     * @return occurrence[] Keyed by occurrence identity.
     */
    public static function collect(int $courseid): array {
        $providers = registry::enabled();
        $includehidden = eligibility::include_hidden_activities();
        $found = [];
        foreach (text_sources::fragments($courseid) as $fragment) {
            if ($fragment->cmid && !$fragment->cmvisible && !$includehidden) {
                continue;
            }
            $text = self::prepare_text($fragment->text);
            if ($text === '') {
                continue;
            }
            foreach ($providers as $provider) {
                foreach ($provider->extract_from_text($text) as $reference) {
                    $occurrence = new occurrence(
                        self::normalise($reference),
                        $courseid,
                        $fragment->sectionid,
                        $fragment->cmid,
                        $fragment->modname,
                        $fragment->source,
                        $fragment->itemid,
                        $fragment->cmvisible
                    );
                    $found[$occurrence->get_identity()] = $occurrence;
                }
            }
        }
        foreach ($providers as $provider) {
            foreach ($provider->extract_structural($courseid) as $occurrence) {
                if ($occurrence->cmid && !$occurrence->cmvisible && !$includehidden) {
                    continue;
                }
                if (!isset($providers[$occurrence->reference->provider])) {
                    continue;
                }
                $found[$occurrence->get_identity()] = $occurrence;
            }
        }
        return $found;
    }

    /**
     * Scans a course and replaces its stored occurrences.
     *
     * @param int $courseid Course id.
     * @return int Number of occurrences found.
     */
    public static function scan_course(int $courseid): int {
        $start = microtime(true);
        if (!eligibility::course($courseid)) {
            self::forget_course($courseid, false);
            self::save_state($courseid, 0, 0);
            return 0;
        }
        $occurrences = self::collect($courseid);
        self::save($courseid, $occurrences, $start);
        return count($occurrences);
    }

    /**
     * Stores the occurrences collected for a course and its scan state.
     *
     * @param int $courseid Course id.
     * @param occurrence[] $occurrences Every occurrence of the course.
     * @param float $start Time the scan started (microtime).
     * @return int[] Ids of the resources used by the course.
     */
    public static function save(int $courseid, array $occurrences, float $start): array {
        $ids = self::persist($courseid, $occurrences);
        self::save_state($courseid, count($occurrences), (int) round((microtime(true) - $start) * 1000));
        return $ids;
    }

    /**
     * Replaces the stored occurrences of a course.
     *
     * @param int $courseid Course id.
     * @param occurrence[] $occurrences Occurrences.
     * @return int[] Ids of the resources used by the course.
     */
    public static function persist(int $courseid, array $occurrences): array {
        global $DB;
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $sql = "SELECT o.id, o.source, o.itemid, o.timefound, r.provider, r.resourcekey
                  FROM {tool_extdeps_occurrence} o
                  JOIN {tool_extdeps_resource} r ON r.id = o.resourceid
                 WHERE o.courseid = :courseid";
        $timefound = [];
        foreach ($DB->get_records_sql($sql, ['courseid' => $courseid]) as $old) {
            $timefound[implode('|', [$old->provider, $old->resourcekey, $old->source, $old->itemid])] = (int) $old->timefound;
        }
        $DB->delete_records('tool_extdeps_occurrence', ['courseid' => $courseid]);

        $records = [];
        $resourceids = [];
        foreach ($occurrences as $identity => $occurrence) {
            $resource = self::find_or_create_resource($occurrence->reference, $now);
            $resourceids[$resource->id] = (int) $resource->id;
            $records[] = [
                'resourceid' => $resource->id,
                'courseid' => $courseid,
                'sectionid' => $occurrence->sectionid,
                'cmid' => $occurrence->cmid,
                'modname' => $occurrence->modname,
                'source' => \core_text::substr($occurrence->source, 0, 40),
                'itemid' => $occurrence->itemid,
                'cmvisible' => (int) $occurrence->cmvisible,
                'timefound' => $timefound[$identity] ?? $now,
                'timeseen' => $now,
            ];
        }
        if ($records) {
            $DB->insert_records('tool_extdeps_occurrence', $records);
        }
        $transaction->allow_commit();
        return array_values($resourceids);
    }

    /**
     * Gets the resource of a reference, creating it when new (due for checking at once).
     *
     * @param reference $reference Reference.
     * @param int $now Current time.
     * @return \stdClass
     */
    public static function find_or_create_resource(reference $reference, int $now): \stdClass {
        global $DB;
        $conditions = ['provider' => $reference->provider, 'resourcekey' => $reference->key];
        $resource = $DB->get_record('tool_extdeps_resource', $conditions);
        if ($resource) {
            return $resource;
        }
        $resource = (object) ($conditions + [
            'url' => $reference->url,
            'status' => status::PENDING,
            'confirmedstatus' => status::PENDING,
            'failcount' => 0,
            'nextcheck' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        try {
            $id = $DB->insert_record('tool_extdeps_resource', $resource);
            return $DB->get_record('tool_extdeps_resource', ['id' => $id], '*', MUST_EXIST);
        } catch (\dml_write_exception $e) {
            // Another process created it in the meantime.
            return $DB->get_record('tool_extdeps_resource', $conditions, '*', MUST_EXIST);
        }
    }

    /**
     * Flags a course for the next scan.
     *
     * @param int $courseid Course id.
     * @param string $reason Why (event name or task).
     * @return void
     */
    public static function mark_dirty(int $courseid, string $reason): void {
        global $DB;
        $reason = \core_text::substr($reason, 0, 40);
        $state = $DB->get_record('tool_extdeps_coursestate', ['courseid' => $courseid], 'id, dirty');
        if ($state) {
            if (!$state->dirty) {
                $DB->update_record('tool_extdeps_coursestate', (object) ['id' => $state->id, 'dirty' => 1, 'reason' => $reason]);
            }
            return;
        }
        try {
            $DB->insert_record('tool_extdeps_coursestate', (object) ['courseid' => $courseid, 'dirty' => 1, 'reason' => $reason]);
        } catch (\dml_write_exception $e) {
            // Already inserted by a concurrent request: it is dirty anyway.
            return;
        }
    }

    /**
     * Flags for scanning every course that has no scan state yet (first scan after install).
     *
     * @param string $reason Reason stored with the flag.
     * @return int Number of courses flagged.
     */
    public static function mark_all_dirty(string $reason): int {
        global $DB;
        $reason = \core_text::substr($reason, 0, 40);
        $sql = "SELECT c.id
                  FROM {course} c
             LEFT JOIN {tool_extdeps_coursestate} s ON s.courseid = c.id
                 WHERE s.id IS NULL";
        $ids = $DB->get_fieldset_sql($sql);
        $rows = array_map(fn($id) => ['courseid' => (int) $id, 'dirty' => 1, 'reason' => $reason], $ids);
        foreach (array_chunk($rows, 500) as $chunk) {
            $DB->insert_records('tool_extdeps_coursestate', $chunk);
        }
        return count($ids);
    }

    /**
     * Removes what is stored about a course.
     *
     * @param int $courseid Course id.
     * @param bool $state Whether to remove the scan state too.
     * @return void
     */
    public static function forget_course(int $courseid, bool $state = true): void {
        global $DB;
        $DB->delete_records('tool_extdeps_occurrence', ['courseid' => $courseid]);
        if ($state) {
            $DB->delete_records('tool_extdeps_coursestate', ['courseid' => $courseid]);
        }
    }

    /**
     * Decodes entities so that URLs written as &amp; are found.
     *
     * @param string $text Text.
     * @return string
     */
    public static function prepare_text(string $text): string {
        if ($text === '' || (!str_contains($text, '//') && !str_contains($text, 'http'))) {
            return '';
        }
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Guarantees that the key fits in the database.
     *
     * @param reference $reference Reference.
     * @return reference
     */
    private static function normalise(reference $reference): reference {
        if (\core_text::strlen($reference->key) <= 255) {
            return $reference;
        }
        return new reference($reference->provider, 'sha1:' . sha1($reference->key), $reference->url);
    }

    /**
     * Stores the scan state of a course.
     *
     * @param int $courseid Course id.
     * @param int $refcount Occurrences found.
     * @param int $duration Milliseconds.
     * @return void
     */
    private static function save_state(int $courseid, int $refcount, int $duration): void {
        global $DB;
        $record = ['courseid' => $courseid, 'dirty' => 0, 'reason' => null, 'lastscan' => time(),
            'scanduration' => $duration, 'refcount' => $refcount];
        $id = $DB->get_field('tool_extdeps_coursestate', 'id', ['courseid' => $courseid]);
        if ($id) {
            $DB->update_record('tool_extdeps_coursestate', (object) (['id' => $id] + $record));
        } else {
            $DB->insert_record('tool_extdeps_coursestate', (object) $record);
        }
    }
}
