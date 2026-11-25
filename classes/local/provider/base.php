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

use tool_extdeps\local\occurrence;
use tool_extdeps\local\reference;
use tool_extdeps\local\result;

/**
 * Base class of the providers (subplugins of type extdepsprovider).
 *
 * A provider recognises one kind of external dependency in the course content and checks it. Each subplugin has a
 * class named extdepsprovider_<name>\provider extending this one.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var int Default number of equal failures needed to confirm a broken status. */
    public const DEFAULT_CONFIRMATIONS = 3;

    /** @var float Time of the last request, to respect the rate limit. */
    private float $lastrequest = 0.0;

    /**
     * Provider name (the subplugin name).
     *
     * @return string
     */
    final public function get_name(): string {
        $namespace = explode('\\', static::class)[0];
        return substr($namespace, strlen('extdepsprovider_'));
    }

    /**
     * Frankenstyle component of the provider.
     *
     * @return string
     */
    final public function get_component(): string {
        return 'extdepsprovider_' . $this->get_name();
    }

    /**
     * Finds references in a text (HTML or URL). Entities such as &amp; are already decoded.
     *
     * @param string $text Text.
     * @return reference[]
     */
    public function extract_from_text(string $text): array {
        return [];
    }

    /**
     * Finds dependencies that are not inside texts, such as SCORM packages or LTI tools.
     *
     * @param int $courseid Course id.
     * @return occurrence[]
     */
    public function extract_structural(int $courseid): array {
        return [];
    }

    /**
     * Checks resources of this provider.
     *
     * @param \stdClass[] $resources Records of tool_extdeps_resource keyed by id.
     * @param bool $strict Whether to use a second, independent method when there is one.
     * @return result[] Results keyed by resource id; missing ids are treated as unknown.
     */
    abstract public function verify(array $resources, bool $strict = false): array;

    /**
     * Advice shown to the course editor for a status.
     *
     * @param string $status Status.
     * @return string
     */
    public function get_advice(string $status): string {
        $sm = get_string_manager();
        if ($sm->string_exists('advice_' . $status, $this->get_component())) {
            return get_string('advice_' . $status, $this->get_component());
        }
        if ($sm->string_exists('advice_' . $status, 'tool_extdeps')) {
            return get_string('advice_' . $status, 'tool_extdeps');
        }
        return '';
    }

    /**
     * Reads a setting of the provider.
     *
     * @param string $name Setting name.
     * @param mixed $default Default value.
     * @return mixed
     */
    public function get_setting(string $name, $default = null) {
        $value = get_config($this->get_component(), $name);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }

    /**
     * Whether the provider is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool) $this->get_setting('enabled', 1);
    }

    /**
     * Number of equal failures needed to confirm a broken status.
     *
     * @return int
     */
    public function get_confirmations(): int {
        return max(1, (int) $this->get_setting('confirmations', static::DEFAULT_CONFIRMATIONS));
    }

    /**
     * Seconds until the next check, by situation.
     *
     * @param string $kind ok, broken or suspect.
     * @return int
     */
    public function get_interval(string $kind): int {
        $defaults = ['ok' => 7 * DAYSECS, 'broken' => DAYSECS, 'suspect' => HOURSECS];
        return max(60, (int) $this->get_setting('interval' . $kind, $defaults[$kind]));
    }

    /**
     * Maximum requests per minute.
     *
     * @return int
     */
    public function get_rate(): int {
        return max(1, (int) $this->get_setting('rate', 15));
    }

    /**
     * Timeout of each request, in seconds.
     *
     * @return int
     */
    public function get_timeout(): int {
        return max(5, (int) $this->get_setting('timeout', 20));
    }

    /**
     * Waits as needed to respect the rate limit, then records the request time.
     *
     * @return void
     */
    protected function throttle(): void {
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            return;
        }
        $interval = 60 / $this->get_rate();
        $wait = $this->lastrequest + $interval - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1000000));
        }
        $this->lastrequest = microtime(true);
    }

    /**
     * Adds the settings shared by every provider to its settings page.
     *
     * @param \admin_settingpage $settings Settings page.
     * @param string $component Provider component.
     * @param array $defaults Defaults: rate, timeout, confirmations, intervalok, intervalbroken, intervalsuspect.
     * @return void
     */
    public static function add_common_settings(\admin_settingpage $settings, string $component, array $defaults = []): void {
        $defaults += ['rate' => 15, 'timeout' => 20, 'confirmations' => self::DEFAULT_CONFIRMATIONS,
            'intervalok' => 7 * DAYSECS, 'intervalbroken' => DAYSECS, 'intervalsuspect' => HOURSECS];
        $settings->add(new \admin_setting_configcheckbox(
            "{$component}/enabled",
            new \lang_string('enabled', 'tool_extdeps'),
            new \lang_string('enabled_desc', 'tool_extdeps'),
            1
        ));
        $settings->add(new \admin_setting_configtext(
            "{$component}/rate",
            new \lang_string('rate', 'tool_extdeps'),
            new \lang_string('rate_desc', 'tool_extdeps'),
            $defaults['rate'],
            PARAM_INT
        ));
        $settings->add(new \admin_setting_configtext(
            "{$component}/timeout",
            new \lang_string('timeout', 'tool_extdeps'),
            new \lang_string('timeout_desc', 'tool_extdeps'),
            $defaults['timeout'],
            PARAM_INT
        ));
        $settings->add(new \admin_setting_configtext(
            "{$component}/confirmations",
            new \lang_string('confirmations', 'tool_extdeps'),
            new \lang_string('confirmations_desc', 'tool_extdeps'),
            $defaults['confirmations'],
            PARAM_INT
        ));
        foreach (['ok', 'broken', 'suspect'] as $kind) {
            $settings->add(new \admin_setting_configduration(
                "{$component}/interval{$kind}",
                new \lang_string('interval' . $kind, 'tool_extdeps'),
                new \lang_string('interval' . $kind . '_desc', 'tool_extdeps'),
                $defaults['interval' . $kind]
            ));
        }
    }
}
