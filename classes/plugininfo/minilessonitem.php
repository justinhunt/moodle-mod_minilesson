<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_minilesson\plugininfo;

use admin_settingpage;
use core\plugininfo\base;
use lang_string;
use mod_minilesson\constants;
use mod_minilesson\utils;
use moodle_url;

/**
 * Subplugin info class.
 *
 * @package    mod_minilesson
 * @copyright  2026 justin hunt <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class minilessonitem extends base {
    /**
     * Do not allow users to uninstall these plugins as it could cause customcerts to break.
     *
     * @return bool
     */
    public function is_uninstall_allowed(): bool {
        return false;
    }

    /**
     * Loads plugin settings to the settings tree.
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig whether the current user has moodle/site:config capability
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig): void {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE;
        $ADMIN = $adminroot;
        $plugininfo = $this;

        if (!$this->is_installed_and_upgraded()) {
            return;
        }

        if (!$hassiteconfig || !file_exists($this->full_path('settings.php'))) {
            return;
        }

        $section = $this->get_settings_section_name();
        $settings = new admin_settingpage($section, $this->displayname, 'moodle/site:config', false);

        include($this->full_path('settings.php'));
        $ADMIN->add($parentnodename, $settings);
    }

    public static function get_enabled_plugins() {
        $enabledplugin = get_config(constants::M_MODNAME, 'enableditems');
        if (empty($enabledplugin)) {
            return null;;
        }
        $enabledclass = explode(',', $enabledplugin);
        return array_fill_keys($enabledclass, 1);
    }

    public function is_enabled() {
        if (!parent::is_enabled()) {
            return false;
        }
        $itemtypeclass = utils::fetch_itemtype_classname($this->name);
        if (!$itemtypeclass || !$itemtypeclass::is_configured()) {
            return false;
        }
        return true;
    }

    /**
     * Get the settings section name.
     *
     * @return null|string the settings section name.
     */
    public function get_settings_section_name(): ?string {
        if (file_exists($this->full_path('settings.php'))) {
            return 'minilessonitem_' . $this->name;
        } else {
            return null;
        }
    }

    public function get_logo_url(): moodle_url {
        global $OUTPUT;
        return $OUTPUT->image_url('icon', $this->component);
    }

    public function get_intro_video_url(): moodle_url {
        global $CFG;
        $pluginrootwwwurl = str_replace($CFG->dirroot, '', $this->rootdir);
        return new moodle_url("{$pluginrootwwwurl}/pix/intro.mp4", ['ver' => $CFG->themerev]);
    }

    public function get_add_label(): lang_string {
        return new lang_string('additem', $this->component);
    }

    public function get_description(): lang_string {
        return new lang_string('item_desc', $this->component);
    }
}
