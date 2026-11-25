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
 * Where a reference was found.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class occurrence {
    /**
     * Constructor.
     *
     * @param reference $reference The reference.
     * @param int $courseid Course id.
     * @param int|null $sectionid Section id, when known.
     * @param int|null $cmid Course module id, when the reference is inside an activity.
     * @param string|null $modname Module name.
     * @param string $source Table and field of origin, e.g. page.content.
     * @param int $itemid Id of the record of origin.
     * @param bool $cmvisible Whether the activity is visible.
     */
    public function __construct(
        /** @var reference The reference. */
        public readonly reference $reference,
        /** @var int Course id. */
        public readonly int $courseid,
        /** @var int|null Section id. */
        public readonly ?int $sectionid,
        /** @var int|null Course module id. */
        public readonly ?int $cmid,
        /** @var string|null Module name. */
        public readonly ?string $modname,
        /** @var string Table and field of origin. */
        public readonly string $source,
        /** @var int Id of the record of origin. */
        public readonly int $itemid,
        /** @var bool Whether the activity is visible. */
        public readonly bool $cmvisible = true,
    ) {
    }

    /**
     * Key that identifies the occurrence inside a course.
     *
     * @return string
     */
    public function get_identity(): string {
        return implode('|', [$this->reference->provider, $this->reference->key, $this->source, $this->itemid]);
    }
}
