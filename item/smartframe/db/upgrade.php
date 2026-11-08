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

/**
 * Upgrade steps for Smart Frame
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the minilessonitem_smartframe upgrade steps from the given old version.
 *
 * @param int $oldversion the currently installed version
 * @return bool
 */
function xmldb_minilessonitem_smartframe_upgrade($oldversion) {
    if ($oldversion < 2026110900) {
        // Smart Frame was a placeholder, so sites that moved from the old list of enabled item types
        // to per-type "disabled" flags got it switched off. It is now a finished item type, so turn
        // it on, as it is on a new site.
        unset_config('disabled', 'minilessonitem_smartframe');
        \core_plugin_manager::reset_caches();
        upgrade_plugin_savepoint(true, 2026110900, 'minilessonitem', 'smartframe');
    }

    return true;
}
