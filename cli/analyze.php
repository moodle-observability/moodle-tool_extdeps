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
 * On demand analysis of external dependencies from the command line.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'resource' => 0,
    'cmid' => 0,
    'course' => 0,
    'provider' => '',
    'all' => false,
    'dry-run' => false,
    'strict' => false,
    'format' => 'table',
    'compare' => '',
    'help' => false,
], ['h' => 'help']);

$help = <<<EOT
Re-reads and checks the external dependencies of a scope now.

Options (one scope):
  --resource=ID     One resource (tool_extdeps_resource.id)
  --cmid=ID         One activity
  --course=ID       One course
  --provider=NAME   Every resource of a provider (youtube, vimeo, ...)
  --all             The whole site

Modifiers:
  --dry-run         Report only: change no status and send no alert
  --strict          Use a second, independent method when the provider has one
  --format=table|csv
  --compare=FILE    CSV of dependencies known to be broken (columns provider,key or video_id);
                    prints false positives, false negatives, precision and recall
  -h, --help        This help

Example:
  php admin/tool/extdeps/cli/analyze.php --all --dry-run --strict --compare=/tmp/known-broken.csv

EOT;

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}
if ($options['help']) {
    echo $help;
    exit(0);
}

$scopes = array_filter([
    'resource' => (int) $options['resource'],
    'cm' => (int) $options['cmid'],
    'course' => (int) $options['course'],
    'provider' => $options['provider'] !== '' ? 1 : 0,
    'site' => $options['all'] ? 1 : 0,
]);
if (count($scopes) !== 1) {
    cli_error("Choose exactly one scope.\n\n" . $help);
}
$scope = array_key_first($scopes);
$scopeid = in_array($scope, ['resource', 'cm', 'course'], true) ? (int) $scopes[$scope] : 0;
$provider = $scope === 'provider' ? clean_param($options['provider'], PARAM_ALPHANUMEXT) : null;

$compare = '';
if ($options['compare'] !== '') {
    if (!is_readable($options['compare'])) {
        cli_error('Cannot read ' . $options['compare']);
    }
    $compare = file_get_contents($options['compare']);
}

$admin = get_admin();
\core\session\manager::set_user($admin);

$run = \tool_extdeps\local\analysis::create_run(
    $scope,
    $scopeid,
    $provider,
    (int) $admin->id,
    (bool) $options['dry-run'],
    (bool) $options['strict']
);
$trace = $options['format'] === 'csv' ? new null_progress_trace() : new text_progress_trace();
$run = \tool_extdeps\local\analysis::execute($run, $trace);

$items = $DB->get_records('tool_extdeps_runitem', ['runid' => $run->id], 'verdict, provider, resourcekey');
if ($options['format'] === 'csv') {
    $out = fopen('php://stdout', 'w');
    fputcsv($out, ['provider', 'key', 'verdict', 'status', 'evidence']);
    foreach ($items as $item) {
        fputcsv($out, [$item->provider, $item->resourcekey, $item->verdict, $item->status, $item->evidence]);
    }
    fclose($out);
} else {
    cli_heading("Run {$run->id}: {$run->status}" . ($run->dryrun ? ' (dry run)' : '') . ($run->strict ? ' (strict)' : ''));
    $summary = json_decode((string) $run->summary, true) ?: [];
    $http = $summary['http'] ?? null;
    unset($summary['http']);
    foreach ($summary as $verdict => $count) {
        echo str_pad($verdict, 24) . $count . "\n";
    }
    if ($http) {
        echo 'HTTP: ' . $http['requests'] . ' requests, ' . $http['seconds'] . ' s waiting, slowest ' . $http['maxseconds']
            . ' s, run ' . ($run->timefinished - $run->timestarted) . " s\n";
    }
}

if ($compare !== '') {
    $result = \tool_extdeps\local\analysis::compare((int) $run->id, $compare);
    cli_heading('Comparison');
    echo 'True positives:  ' . $result['truepositives'] . "\n";
    echo 'Precision:       ' . $result['precision'] . "\n";
    echo 'Recall:          ' . $result['recall'] . "\n";
    foreach (
        ['falsepositives' => 'False positives', 'falsenegatives' => 'False negatives',
            'notfound' => 'Expected but not found in the content'] as $key => $label
    ) {
        echo "\n{$label} (" . count($result[$key]) . "):\n";
        foreach ($result[$key] as $value) {
            echo "  {$value}\n";
        }
    }
}
exit($run->status === 'done' ? 0 : 1);
