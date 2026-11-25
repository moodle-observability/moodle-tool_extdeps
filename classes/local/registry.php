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

use tool_extdeps\local\provider\base;

/**
 * Finds the installed providers.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** @var base[]|null Instances keyed by name. */
    private static ?array $providers = null;

    /**
     * All installed providers, keyed by name.
     *
     * @return base[]
     */
    public static function all(): array {
        if (self::$providers === null) {
            self::$providers = [];
            foreach (\core_component::get_plugin_list_with_class('extdepsprovider', 'provider') as $classname) {
                if (is_subclass_of($classname, base::class)) {
                    $provider = new $classname();
                    self::$providers[$provider->get_name()] = $provider;
                }
            }
            ksort(self::$providers);
        }
        return self::$providers;
    }

    /**
     * Enabled providers, keyed by name.
     *
     * @return base[]
     */
    public static function enabled(): array {
        return array_filter(self::all(), fn(base $provider): bool => $provider->is_enabled());
    }

    /**
     * One provider by name.
     *
     * @param string $name Provider name.
     * @return base|null
     */
    public static function get(string $name): ?base {
        return self::all()[$name] ?? null;
    }

    /**
     * Forgets the instances (unit tests).
     *
     * @return void
     */
    public static function reset(): void {
        self::$providers = null;
    }
}
