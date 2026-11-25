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

namespace tool_extdeps\plugininfo;

/**
 * Subplugin type extdepsprovider: one provider of external dependencies.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extdepsprovider extends \core\plugininfo\base {
    /**
     * Providers can be uninstalled.
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * Name of the settings page.
     *
     * @return string
     */
    public function get_settings_section_name() {
        return 'extdepsprovider_' . $this->name;
    }

    /**
     * Whether the provider is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        $value = get_config('extdepsprovider_' . $this->name, 'enabled');
        return $value === false || (bool) $value;
    }

    /**
     * Loads the settings page of the provider.
     *
     * @param \part_of_admin_tree $adminroot Admin tree.
     * @param string $parentnodename Parent node.
     * @param bool $hassiteconfig Whether the user can configure the site.
     * @return void
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        // Variables used by the included settings.php.
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // phpcs:ignore moodle.Commenting.InlineComment
        $ADMIN = $adminroot; // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameUC
        $plugininfo = $this;

        if (!$this->is_installed_and_upgraded() || !$hassiteconfig) {
            return;
        }
        if (!file_exists($this->full_path('settings.php'))) {
            return;
        }
        $settings = new \admin_settingpage(
            $this->get_settings_section_name(),
            $this->displayname,
            'moodle/site:config',
            $this->is_enabled() === false
        );
        include($this->full_path('settings.php'));
        $ADMIN->add($parentnodename, $settings);
    }
}
