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
 * A reference to an external dependency found in the course content.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reference {
    /**
     * Constructor.
     *
     * @param string $provider Provider name, e.g. youtube.
     * @param string $key Normalised identifier inside the provider, at most 255 characters.
     * @param string $url Canonical URL used to check the dependency.
     */
    public function __construct(
        /** @var string Provider name. */
        public readonly string $provider,
        /** @var string Normalised identifier. */
        public readonly string $key,
        /** @var string Canonical URL. */
        public readonly string $url,
    ) {
    }
}
