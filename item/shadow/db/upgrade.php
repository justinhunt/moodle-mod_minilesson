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
 * Upgrade steps for Video Shadowing
 *
 * @package    minilessonitem_shadow
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the minilessonitem_shadow upgrade steps from the given old version.
 *
 * @param int $oldversion the currently installed version
 * @return bool
 */
function xmldb_minilessonitem_shadow_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026061200) {
        // The per-word highlighting setting was added (on by default). Existing
        // shadow items predate it and were highlighting per word, so keep them on.
        $DB->set_field(
            \mod_minilesson\constants::M_QTABLE,
            \minilessonitem_shadow\itemtype::WORDHIGHLIGHT,
            1,
            ['type' => \mod_minilesson\constants::TYPE_SHADOW]
        );
        upgrade_plugin_savepoint(true, 2026061200, 'minilessonitem', 'shadow');
    }

    return true;
}
