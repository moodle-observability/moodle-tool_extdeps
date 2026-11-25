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

/**
 * English strings for tool_extdeps.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['advice_corrupted'] = 'The package file is damaged. Upload the package again.';
$string['advice_embed_blocked'] = 'The owner does not allow this content to be embedded in other sites. Ask the owner to allow embedding, or replace it with a link or another copy.';
$string['advice_misconfigured'] = 'The activity points to a tool that was deleted or disabled. Choose another tool or ask the administrator.';
$string['advice_missing'] = 'The package file is missing. Upload the package again.';
$string['advice_private'] = 'The content became private. Ask the owner to make it public or unlisted, or replace it.';
$string['advice_region_blocked'] = 'The content is blocked in this country. Replace it with another copy.';
$string['advice_removed'] = 'The content was removed or never existed. Replace it with another copy or remove the reference.';
$string['advice_restricted'] = 'The content requires login or age confirmation, so students cannot watch it embedded. Replace it or ask the owner to remove the restriction.';
$string['advice_tls_expiring'] = 'The certificate of the external server is about to expire. Warn the owner of the server.';
$string['advice_tls_invalid'] = 'The certificate of the external server is invalid. Warn the owner of the server.';
$string['advice_unavailable'] = 'The external server does not answer or answers with an error. Check the address; if it persists, replace it.';
$string['alert_body'] = 'The external dependency {$a->url} ({$a->provider}) used in the course "{$a->course}" is now: {$a->status}.';
$string['alert_title'] = 'Broken external dependency in {$a->course}: {$a->status}';
$string['alertheading'] = 'Alerts';
$string['alertheading_desc'] = 'Alerts are sent through local_alerthub, when it is installed.';
$string['alertstatuses'] = 'Statuses that raise alerts';
$string['alertstatuses_desc'] = 'Confirmed statuses that raise an alert for the course.';
$string['confirmations'] = 'Confirmations';
$string['confirmations_desc'] = 'Number of equal failures in a row needed to confirm a broken status. Definitive evidence (for example an official API) confirms at once.';
$string['enabled'] = 'Enabled';
$string['enabled_desc'] = 'Whether this provider finds and checks dependencies.';
$string['endedcategories'] = 'Categories of ended courses';
$string['endedcategories_desc'] = 'Comma separated category ids whose courses are treated as ended (subcategories included).';
$string['errorcomparecsv'] = 'The CSV must have a header with the column key (and optionally provider), or video_id (and optionally plataforma).';
$string['errorunknownprovider'] = 'Unknown provider: {$a}';
$string['excludedcategories'] = 'Excluded categories';
$string['excludedcategories_desc'] = 'Comma separated category ids never monitored (subcategories included).';
$string['excludedcourses'] = 'Excluded courses';
$string['excludedcourses_desc'] = 'Comma separated course ids never monitored.';
$string['extdeps:analyze'] = 'Run on demand analyses of external dependencies';
$string['extdeps:manage'] = 'Manage the monitoring of external dependencies';
$string['generalsettings'] = 'General settings';
$string['historyretention'] = 'History retention';
$string['historyretention_desc'] = 'How long the history of status changes and the on demand analyses are kept.';
$string['includeended'] = 'Include ended courses';
$string['includeended_desc'] = 'Monitor courses whose end date has passed and courses in the categories of ended courses.';
$string['includehidden'] = 'Include hidden courses';
$string['includehidden_desc'] = 'Monitor courses hidden from students.';
$string['includehiddencm'] = 'Include hidden activities';
$string['includehiddencm_desc'] = 'Monitor activities hidden from students.';
$string['intervalbroken'] = 'Recheck broken';
$string['intervalbroken_desc'] = 'Time until a dependency confirmed as broken is checked again.';
$string['intervalok'] = 'Recheck available';
$string['intervalok_desc'] = 'Time until an available dependency is checked again.';
$string['intervalsuspect'] = 'Recheck suspect';
$string['intervalsuspect_desc'] = 'Time until a dependency with an unconfirmed failure or an inconclusive answer is checked again.';
$string['loadheading'] = 'Load';
$string['loadheading_desc'] = 'Limits that protect the site and the external providers.';
$string['orphanretention'] = 'Orphan retention';
$string['orphanretention_desc'] = 'How long a dependency no longer used by any course is kept.';
$string['packagebytebudget'] = 'Package bytes per run (MB)';
$string['packagebytebudget_desc'] = 'Maximum megabytes of package content read by each run of the package task.';
$string['packagetimebudget'] = 'Package time per run (seconds)';
$string['packagetimebudget_desc'] = 'Maximum seconds of each run of the package task.';
$string['pluginname'] = 'External dependencies monitor';
$string['privacy:metadata:exception'] = 'False positives accepted by managers.';
$string['privacy:metadata:exception:reason'] = 'Reason given.';
$string['privacy:metadata:exception:timecreated'] = 'When it was accepted.';
$string['privacy:metadata:exception:userid'] = 'Who accepted it.';
$string['privacy:metadata:run'] = 'On demand analyses.';
$string['privacy:metadata:run:scope'] = 'What was analysed.';
$string['privacy:metadata:run:timequeued'] = 'When it was asked.';
$string['privacy:metadata:run:userid'] = 'Who asked for it.';
$string['rate'] = 'Requests per minute';
$string['rate_desc'] = 'Maximum requests per minute sent to this provider.';
$string['scanbudget'] = 'Scan time per run (seconds)';
$string['scanbudget_desc'] = 'Maximum seconds of each run of the task that scans the changed courses.';
$string['scopeheading'] = 'What is monitored';
$string['scopeheading_desc'] = 'The plugin only reads the courses: it never changes their content.';
$string['status_corrupted'] = 'Corrupted';
$string['status_embed_blocked'] = 'Embedding blocked';
$string['status_misconfigured'] = 'Misconfigured';
$string['status_missing'] = 'Missing';
$string['status_ok'] = 'Available';
$string['status_pending'] = 'Not checked yet';
$string['status_private'] = 'Private';
$string['status_region_blocked'] = 'Blocked in the country';
$string['status_removed'] = 'Removed';
$string['status_restricted'] = 'Restricted (login or age)';
$string['status_temporary_error'] = 'Temporary error';
$string['status_tls_expiring'] = 'Certificate expiring';
$string['status_tls_invalid'] = 'Invalid certificate';
$string['status_unavailable'] = 'Unavailable';
$string['status_unknown'] = 'Unknown';
$string['subplugintype_extdepsprovider'] = 'Provider';
$string['subplugintype_extdepsprovider_plural'] = 'Providers';
$string['task_cleanup'] = 'Clean up external dependencies';
$string['task_full_sweep'] = 'Flag courses for a new scan of external dependencies';
$string['task_run_analysis'] = 'On demand analysis of external dependencies';
$string['task_scan_dirty_courses'] = 'Scan changed courses for external dependencies';
$string['task_scan_packages'] = 'Read packages looking for external dependencies';
$string['task_verify_resources'] = 'Check external dependencies';
$string['timeout'] = 'Timeout (seconds)';
$string['timeout_desc'] = 'Timeout of each request to this provider.';
$string['type_extdepsprovider'] = 'Provider';
$string['type_extdepsprovider_plural'] = 'Providers';
$string['verdict_confirmed_ok'] = 'Confirmed available';
$string['verdict_divergent'] = 'Methods disagree';
$string['verdict_inconclusive'] = 'Inconclusive';
$string['verdict_removed_from_content'] = 'Removed from the content';
$string['verdict_still_broken'] = 'Still broken';
$string['verdict_suspected'] = 'Failure not yet confirmed';
$string['verifybudget'] = 'Check time per run (seconds)';
$string['verifybudget_desc'] = 'Maximum seconds of each run of the check task, shared by the providers.';
