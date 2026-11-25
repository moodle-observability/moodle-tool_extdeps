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

namespace tool_extdeps\local\provider;

/**
 * Providers that read the content of packages (SCORM, H5P) in the night task.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface package_scanner {
    /**
     * Reads packages not read yet, caching the references found by content hash.
     *
     * @param int $timelimit Seconds available.
     * @param int $bytelimit Bytes that may be read.
     * @param \progress_trace $trace Output.
     * @return int Packages read.
     */
    public function scan_packages(int $timelimit, int $bytelimit, \progress_trace $trace): int;
}
