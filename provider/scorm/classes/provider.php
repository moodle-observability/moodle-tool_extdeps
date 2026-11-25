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

namespace extdepsprovider_scorm;

use tool_extdeps\local\http;
use tool_extdeps\local\occurrence;
use tool_extdeps\local\provider\base;
use tool_extdeps\local\provider\package_scanner;
use tool_extdeps\local\reference;
use tool_extdeps\local\registry;
use tool_extdeps\local\result;
use tool_extdeps\local\scanner;
use tool_extdeps\local\status;

/**
 * SCORM packages.
 *
 * - Local packages: the package file must exist in the file system, open as a zip and have its manifest extracted.
 * - External packages (external, aiccurl, localsync): the reference URL must answer.
 * - References inside local packages (videos in the HTML of the package) are read at night, cached by the content
 *   hash of the package, and reported as occurrences of the other providers.
 *
 * @package    extdepsprovider_scorm
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends base implements package_scanner {
    /** Default number of confirmations: the checks of local files are definitive. */
    public const DEFAULT_CONFIRMATIONS = 2;

    /** @var int Largest file of the package content that is read. */
    public const MAX_FILE_SIZE = 2 * 1024 * 1024;

    /** @var string Extensions of package files that may contain references. */
    private const TEXT_FILES = '~\.(html?|js|css|xml|json|txt)$~i';

    /**
     * SCORM activities of a course, plus the references found inside their packages.
     *
     * @param int $courseid Course id.
     * @return occurrence[]
     */
    public function extract_structural(int $courseid): array {
        global $DB;
        $sql = "SELECT s.id, s.scormtype, s.reference, cm.id AS cmid, cm.section, cm.visible, ctx.id AS contextid
                  FROM {scorm} s
                  JOIN {modules} m ON m.name = 'scorm'
                  JOIN {course_modules} cm ON cm.instance = s.id AND cm.module = m.id AND cm.deletioninprogress = 0
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                 WHERE s.course = :course";
        $occurrences = [];
        $cachedrefs = $this->cached_package_refs($courseid);
        foreach ($DB->get_records_sql($sql, ['level' => CONTEXT_MODULE, 'course' => $courseid]) as $scorm) {
            if ($scorm->scormtype === 'local') {
                $ref = new reference('scorm', 'cm:' . $scorm->cmid, '/mod/scorm/view.php?id=' . $scorm->cmid);
                $source = 'scorm.package';
            } else {
                $ref = new reference('scorm', 'url:' . sha1($scorm->reference), $scorm->reference);
                $source = 'scorm.reference';
            }
            $occurrences[] = new occurrence(
                $ref,
                $courseid,
                (int) $scorm->section,
                (int) $scorm->cmid,
                'scorm',
                $source,
                (int) $scorm->id,
                (bool) $scorm->visible
            );
            foreach ($cachedrefs[(int) $scorm->contextid] ?? [] as $inner) {
                $occurrences[] = new occurrence(
                    new reference($inner['provider'], $inner['key'], $inner['url']),
                    $courseid,
                    (int) $scorm->section,
                    (int) $scorm->cmid,
                    'scorm',
                    'scorm.content',
                    (int) $scorm->id,
                    (bool) $scorm->visible
                );
            }
        }
        return $occurrences;
    }

    /**
     * References cached for the packages of a course, keyed by context id.
     *
     * @param int $courseid Course id.
     * @return array<int, array<int, array{provider: string, key: string, url: string}>>
     */
    private function cached_package_refs(int $courseid): array {
        global $DB;
        $sql = "SELECT f.contextid, fs.refs
                  FROM {files} f
                  JOIN {context} ctx ON ctx.id = f.contextid AND ctx.contextlevel = :level
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                  JOIN {tool_extdeps_filescan} fs ON fs.contenthash = f.contenthash
                 WHERE cm.course = :course AND f.component = 'mod_scorm' AND f.filearea = 'package' AND f.filename <> '.'";
        $byctx = [];
        foreach ($DB->get_records_sql($sql, ['level' => CONTEXT_MODULE, 'course' => $courseid]) as $row) {
            $refs = json_decode((string) $row->refs, true);
            $byctx[(int) $row->contextid] = is_array($refs) ? $refs : [];
        }
        return $byctx;
    }

    /**
     * Checks SCORM resources.
     *
     * @param \stdClass[] $resources Resources keyed by id.
     * @param bool $strict Not used.
     * @return result[]
     */
    public function verify(array $resources, bool $strict = false): array {
        $results = [];
        foreach ($resources as $id => $resource) {
            if (str_starts_with($resource->resourcekey, 'cm:')) {
                $results[$id] = self::check_local((int) substr($resource->resourcekey, 3));
            } else {
                $this->throttle();
                $answer = http::get($resource->url, $this->get_timeout());
                $results[$id] = self::classify_http(
                    $answer['response'] ? $answer['response']->getStatusCode() : 0,
                    $answer['error']
                );
            }
        }
        return $results;
    }

    /**
     * Checks a local package.
     *
     * @param int $cmid Course module id.
     * @return result
     */
    public static function check_local(int $cmid): result {
        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$context) {
            return new result(status::REMOVED, 'file', ['reason' => 'activity deleted'], true);
        }
        $fs = get_file_storage();
        $packages = $fs->get_area_files($context->id, 'mod_scorm', 'package', 0, 'id', false);
        if (!$packages) {
            return new result(status::MISSING, 'file', ['reason' => 'no package file'], true);
        }
        $package = reset($packages);
        $evidence = ['contenthash' => $package->get_contenthash(), 'filesize' => (int) $package->get_filesize()];
        if (!$fs->get_file_system()->is_file_readable_remotely_by_storedfile($package)) {
            return new result(
                status::MISSING,
                'file',
                $evidence + ['reason' => 'package content missing in the file system'],
                true
            );
        }
        $list = $package->list_files(get_file_packer('application/zip'));
        if (empty($list)) {
            return new result(status::CORRUPTED, 'file', $evidence + ['reason' => 'package does not open as a zip'], true);
        }
        if (!$fs->file_exists($context->id, 'mod_scorm', 'content', 0, '/', 'imsmanifest.xml')) {
            return new result(status::MISSING, 'file', $evidence + ['reason' => 'imsmanifest.xml not extracted'], true);
        }
        return new result(status::OK, 'file', $evidence + ['files' => count($list)], true);
    }

    /**
     * Status of an external package URL.
     *
     * @param int $code HTTP code (0 on network error).
     * @param string|null $error Network error.
     * @return result
     */
    public static function classify_http(int $code, ?string $error = null): result {
        $evidence = ['http' => $code] + ($error ? ['error' => $error] : []);
        if ($code >= 200 && $code < 400) {
            return new result(status::OK, 'http', $evidence);
        }
        if ($code === 404 || $code === 410) {
            return new result(status::REMOVED, 'http', $evidence);
        }
        if ($code === 401 || $code === 403) {
            return new result(status::RESTRICTED, 'http', $evidence);
        }
        if ($code === 0 && $error !== null && preg_match('~resolve|ssl|certificate~i', $error)) {
            return new result(status::UNAVAILABLE, 'http', $evidence);
        }
        return new result(status::TEMPORARY, 'http', $evidence, false, $code === 429 ? 900 : 0);
    }

    /**
     * Reads packages not read yet.
     *
     * @param int $timelimit Seconds available.
     * @param int $bytelimit Bytes that may be read.
     * @param \progress_trace $trace Output.
     * @return int Packages read.
     */
    public function scan_packages(int $timelimit, int $bytelimit, \progress_trace $trace): int {
        global $DB;
        $start = microtime(true);
        $read = 0;
        $bytes = 0;
        $sql = "SELECT f.contenthash, MIN(f.contextid) AS contextid
                  FROM {files} f
             LEFT JOIN {tool_extdeps_filescan} fs ON fs.contenthash = f.contenthash
                 WHERE f.component = 'mod_scorm' AND f.filearea = 'package' AND f.filename <> '.' AND fs.id IS NULL
              GROUP BY f.contenthash";
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $package) {
            if (microtime(true) - $start > $timelimit || $bytes > $bytelimit) {
                break;
            }
            [$refs, $size] = $this->read_package_content((int) $package->contextid);
            $bytes += $size;
            $DB->insert_record('tool_extdeps_filescan', (object) ['contenthash' => $package->contenthash,
                'lastscan' => time(), 'bytes' => $size, 'refs' => json_encode($refs, JSON_UNESCAPED_SLASHES)]);
            $read++;
            $trace->output("scorm: package {$package->contenthash}: " . count($refs) . ' references, ' . $size . ' bytes');
            foreach ($this->courses_with_package($package->contenthash) as $courseid) {
                scanner::mark_dirty($courseid, 'scorm_package');
            }
        }
        $rs->close();
        return $read;
    }

    /**
     * References found in the extracted content of a package.
     *
     * @param int $contextid Context of an activity that uses the package.
     * @return array{0: array, 1: int} References and bytes read.
     */
    public function read_package_content(int $contextid): array {
        $fs = get_file_storage();
        $providers = array_filter(registry::enabled(), fn(base $p): bool => $p->get_name() !== 'scorm');
        $refs = [];
        $bytes = 0;
        foreach ($fs->get_area_files($contextid, 'mod_scorm', 'content', 0, 'filepath, filename', false) as $file) {
            if (!preg_match(self::TEXT_FILES, $file->get_filename()) || $file->get_filesize() > self::MAX_FILE_SIZE) {
                continue;
            }
            $text = scanner::prepare_text((string) $file->get_content());
            $bytes += (int) $file->get_filesize();
            if ($text === '') {
                continue;
            }
            foreach ($providers as $provider) {
                foreach ($provider->extract_from_text($text) as $ref) {
                    $refs[$ref->provider . '|' . $ref->key] = ['provider' => $ref->provider, 'key' => $ref->key,
                        'url' => $ref->url];
                }
            }
        }
        return [array_values($refs), $bytes];
    }

    /**
     * Courses with activities using a package.
     *
     * @param string $contenthash Content hash of the package.
     * @return int[]
     */
    private function courses_with_package(string $contenthash): array {
        global $DB;
        $sql = "SELECT DISTINCT cm.course
                  FROM {files} f
                  JOIN {context} ctx ON ctx.id = f.contextid AND ctx.contextlevel = :level
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid
                 WHERE f.contenthash = :contenthash AND f.component = 'mod_scorm' AND f.filearea = 'package'";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['level' => CONTEXT_MODULE, 'contenthash' => $contenthash]));
    }
}
