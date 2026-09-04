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
 * Upgrade steps for Short Answer
 *
 * @package    minilessonitem_shortanswer
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the minilessonitem_shortanswer upgrade steps from the given old version.
 *
 * @param int $oldversion the currently installed version
 * @return bool
 */
function xmldb_minilessonitem_shortanswer_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026090400) {
        // The response type and the marks settings were added after this item type shipped, so
        // items authored before then hold 0 in those columns. 0 is not a response type, so neither
        // the recorder nor the text box rendered and the item could not be answered at all, and
        // 0 total marks graded every answer as wrong. Give those items the values that match how
        // they behaved before the settings existed: speak the answer, with the default marks.
        $legacy = [
            'type' => \mod_minilesson\constants::TYPE_SHORTANSWER,
            \minilessonitem_shortanswer\itemtype::RESPONSETYPE => 0,
        ];
        $DB->set_field(
            \mod_minilesson\constants::M_QTABLE,
            \minilessonitem_shortanswer\itemtype::PARTIALLYMARKS,
            \minilessonitem_shortanswer\itemtype::DEFAULT_PARTIALLYMARKS,
            $legacy + [\minilessonitem_shortanswer\itemtype::TOTALMARKS => 0]
        );
        $DB->set_field(
            \mod_minilesson\constants::M_QTABLE,
            \minilessonitem_shortanswer\itemtype::TOTALMARKS,
            \minilessonitem_shortanswer\itemtype::DEFAULT_TOTALMARKS,
            $legacy + [\minilessonitem_shortanswer\itemtype::TOTALMARKS => 0]
        );
        $DB->set_field(
            \mod_minilesson\constants::M_QTABLE,
            \minilessonitem_shortanswer\itemtype::RESPONSETYPE,
            \mod_minilesson\constants::RESPONSE_TYPE['audiorecorder'],
            $legacy
        );
        upgrade_plugin_savepoint(true, 2026090400, 'minilessonitem', 'shortanswer');
    }

    return true;
}
