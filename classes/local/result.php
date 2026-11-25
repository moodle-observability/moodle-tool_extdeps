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
 * Result of the check of one external dependency.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class result {
    /**
     * Constructor.
     *
     * @param string $status One of the status constants.
     * @param string $method How the status was obtained, e.g. api, oembed, page, file.
     * @param array $evidence What the provider answered (HTTP code, fields), without personal data or credentials.
     * @param bool $definitive Whether the evidence is enough to confirm the status at once.
     * @param int $retryafter Seconds the provider asked us to wait (rate limits), or 0.
     * @param bool $divergent Whether two independent methods disagreed (strict mode).
     */
    public function __construct(
        /** @var string Status. */
        public readonly string $status,
        /** @var string Method. */
        public readonly string $method,
        /** @var array Evidence. */
        public readonly array $evidence = [],
        /** @var bool Definitive evidence. */
        public readonly bool $definitive = false,
        /** @var int Seconds to wait before asking the provider again. */
        public readonly int $retryafter = 0,
        /** @var bool Methods disagreed. */
        public readonly bool $divergent = false,
    ) {
    }

    /**
     * Copy of this result marked as divergent, keeping the evidence of both methods.
     *
     * @param result $other Result of the second method.
     * @param string[] $okcompatible Failures of this method that the second method cannot see (it answers ok for them).
     * @return result
     */
    public function with_second_opinion(result $other, array $okcompatible = []): result {
        $evidence = $this->evidence;
        $evidence['second'] = ['method' => $other->method, 'status' => $other->status] + $other->evidence;
        $divergent = (status::is_broken($this->status) && $other->status === status::OK
                && !in_array($this->status, $okcompatible, true))
            || ($this->status === status::OK && status::is_broken($other->status));
        return new result(
            $this->status,
            $this->method,
            $evidence,
            $this->definitive && !$divergent,
            $this->retryafter,
            $divergent
        );
    }

    /**
     * Evidence as JSON, with the method and the status.
     *
     * @return string
     */
    public function to_json(): string {
        return json_encode(['method' => $this->method, 'status' => $this->status, 'definitive' => $this->definitive,
            'divergent' => $this->divergent] + $this->evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
