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

namespace tool_extdeps\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: who asked for analyses and who accepted false positives.
 *
 * The data is technical and stays after a deletion request, with the user replaced by 0.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describes the stored personal data.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_extdeps_run', [
            'userid' => 'privacy:metadata:run:userid',
            'scope' => 'privacy:metadata:run:scope',
            'timequeued' => 'privacy:metadata:run:timequeued',
        ], 'privacy:metadata:run');
        $collection->add_database_table('tool_extdeps_exception', [
            'userid' => 'privacy:metadata:exception:userid',
            'reason' => 'privacy:metadata:exception:reason',
            'timecreated' => 'privacy:metadata:exception:timecreated',
        ], 'privacy:metadata:exception');
        return $collection;
    }

    /**
     * Contexts with data of a user (always the system context).
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if (
            $DB->record_exists('tool_extdeps_run', ['userid' => $userid])
                || $DB->record_exists('tool_extdeps_exception', ['userid' => $userid])
        ) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {tool_extdeps_run} WHERE userid > 0', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {tool_extdeps_exception} WHERE userid > 0', []);
    }

    /**
     * Exports the data of a user.
     *
     * @param approved_contextlist $contextlist Contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $runs = [];
            foreach ($DB->get_records('tool_extdeps_run', ['userid' => $userid], 'id') as $run) {
                $runs[] = ['scope' => $run->scope, 'scopeid' => $run->scopeid,
                    'timequeued' => \core_privacy\local\request\transform::datetime($run->timequeued)];
            }
            $exceptions = [];
            foreach ($DB->get_records('tool_extdeps_exception', ['userid' => $userid], 'id') as $exception) {
                $exceptions[] = ['resourceid' => $exception->resourceid, 'reason' => $exception->reason,
                    'timecreated' => \core_privacy\local\request\transform::datetime($exception->timecreated)];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'tool_extdeps')],
                (object) ['analyses' => $runs, 'falsepositives' => $exceptions]
            );
        }
    }

    /**
     * Anonymises the data of every user in a context.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_system) {
            $DB->set_field_select('tool_extdeps_run', 'userid', 0, 'userid > 0');
            $DB->set_field_select('tool_extdeps_exception', 'userid', 0, 'userid > 0');
        }
    }

    /**
     * Anonymises the data of a user.
     *
     * @param approved_contextlist $contextlist Contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                self::anonymise([(int) $contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Anonymises the data of some users.
     *
     * @param approved_userlist $userlist Users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context() instanceof \context_system) {
            self::anonymise(array_map('intval', $userlist->get_userids()));
        }
    }

    /**
     * Replaces the users by 0.
     *
     * @param int[] $userids Users.
     * @return void
     */
    private static function anonymise(array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $DB->set_field_select('tool_extdeps_run', 'userid', 0, "userid {$insql}", $params);
        $DB->set_field_select('tool_extdeps_exception', 'userid', 0, "userid {$insql}", $params);
    }
}
