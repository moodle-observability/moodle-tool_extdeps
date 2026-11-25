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
 * Statuses of an external dependency.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class status {
    /** Never checked. */
    public const PENDING = 'pending';
    /** Available. */
    public const OK = 'ok';
    /** Private: only the owner can see it. */
    public const PRIVATE = 'private';
    /** Removed or never existed. */
    public const REMOVED = 'removed';
    /** Needs login or age confirmation. */
    public const RESTRICTED = 'restricted';
    /** Cannot be embedded in this site. */
    public const EMBED_BLOCKED = 'embed_blocked';
    /** Blocked in the country of the site. */
    public const REGION_BLOCKED = 'region_blocked';
    /** The server does not answer or answers with an error. */
    public const UNAVAILABLE = 'unavailable';
    /** Invalid TLS certificate. */
    public const TLS_INVALID = 'tls_invalid';
    /** TLS certificate about to expire. */
    public const TLS_EXPIRING = 'tls_expiring';
    /** Wrong configuration inside Moodle (deleted or disabled tool). */
    public const MISCONFIGURED = 'misconfigured';
    /** File missing. */
    public const MISSING = 'missing';
    /** File corrupted. */
    public const CORRUPTED = 'corrupted';
    /** Temporary error: never confirms a failure. */
    public const TEMPORARY = 'temporary_error';
    /** The answer could not be understood: never confirms a failure. */
    public const UNKNOWN = 'unknown';

    /**
     * Statuses that mean the dependency is broken.
     *
     * @return string[]
     */
    public static function broken(): array {
        return [
            self::PRIVATE, self::REMOVED, self::RESTRICTED, self::EMBED_BLOCKED, self::REGION_BLOCKED,
            self::UNAVAILABLE, self::TLS_INVALID, self::TLS_EXPIRING, self::MISCONFIGURED, self::MISSING, self::CORRUPTED,
        ];
    }

    /**
     * All statuses, in display order.
     *
     * @return string[]
     */
    public static function all(): array {
        return array_merge([self::PENDING, self::OK], self::broken(), [self::TEMPORARY, self::UNKNOWN]);
    }

    /**
     * Whether the status means a failure.
     *
     * @param string $status Status.
     * @return bool
     */
    public static function is_broken(string $status): bool {
        return in_array($status, self::broken(), true);
    }

    /**
     * Whether the status is inconclusive (it never changes the confirmed status).
     *
     * @param string $status Status.
     * @return bool
     */
    public static function is_inconclusive(string $status): bool {
        return $status === self::TEMPORARY || $status === self::UNKNOWN;
    }
}
