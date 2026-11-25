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

namespace tool_extdeps;

use extdepsprovider_youtube\provider;
use tool_extdeps\local\reference;
use tool_extdeps\local\result;
use tool_extdeps\local\scanner;
use tool_extdeps\local\state;
use tool_extdeps\local\status;

/**
 * Tests of the confirmation rules.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_extdeps\local\state
 */
final class state_test extends \advanced_testcase {
    /**
     * A new resource.
     *
     * @return \stdClass
     */
    private function resource(): \stdClass {
        return scanner::find_or_create_resource(
            new reference('youtube', 'AAAAAAAAAAA', 'https://youtu.be/AAAAAAAAAAA'),
            time()
        );
    }

    /**
     * A failure is confirmed only after the configured number of equal failures in a row.
     *
     * @return void
     */
    public function test_confirmation(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('confirmations', 3, 'extdepsprovider_youtube');
        $provider = new provider();
        $resource = $this->resource();

        $this->assertNull(state::apply($resource, new result(status::REMOVED, 'page'), $provider, false));
        $this->assertNull(state::apply($resource, new result(status::REMOVED, 'page'), $provider, false));
        $this->assertSame(status::PENDING, $resource->confirmedstatus);
        // A temporary error in the middle changes nothing.
        $this->assertNull(state::apply($resource, new result(status::TEMPORARY, 'page'), $provider, false));
        $this->assertSame(2, (int) $resource->failcount);
        $this->assertSame(
            status::REMOVED,
            state::apply($resource, new result(status::REMOVED, 'page'), $provider, false)
        );
        $this->assertSame(status::REMOVED, $DB->get_field('tool_extdeps_resource', 'confirmedstatus', ['id' => $resource->id]));
        $this->assertSame(1, $DB->count_records('tool_extdeps_history', ['resourceid' => $resource->id]));

        // A single available answer confirms at once, with history.
        $this->assertSame(status::OK, state::apply($resource, new result(status::OK, 'page'), $provider, false));
        $this->assertSame(0, (int) $resource->failcount);
        $this->assertSame(2, $DB->count_records('tool_extdeps_history', ['resourceid' => $resource->id]));
    }

    /**
     * A different failure restarts the count; definitive evidence confirms at once.
     *
     * @return void
     */
    public function test_different_failure_and_definitive(): void {
        $this->resetAfterTest();
        set_config('confirmations', 2, 'extdepsprovider_youtube');
        $provider = new provider();
        $resource = $this->resource();

        $this->assertNull(state::apply($resource, new result(status::REMOVED, 'page'), $provider, false));
        $this->assertNull(state::apply($resource, new result(status::PRIVATE, 'page'), $provider, false));
        $this->assertSame(1, (int) $resource->failcount);
        $this->assertSame(
            status::PRIVATE,
            state::apply($resource, new result(status::PRIVATE, 'api', [], true), $provider, false)
        );
    }

    /**
     * Unknown and divergent answers never confirm and are checked again soon.
     *
     * @return void
     */
    public function test_inconclusive(): void {
        $this->resetAfterTest();
        set_config('confirmations', 1, 'extdepsprovider_youtube');
        $provider = new provider();
        $resource = $this->resource();
        $now = time();
        $this->assertNull(state::apply($resource, new result(status::UNKNOWN, 'page'), $provider, false, $now));
        $this->assertNull(state::apply(
            $resource,
            new result(status::REMOVED, 'page', [], false, 0, true),
            $provider,
            false,
            $now
        ));
        $this->assertSame(status::PENDING, $resource->confirmedstatus);
        $this->assertSame($now + $provider->get_interval('suspect'), (int) $resource->nextcheck);
    }
}
