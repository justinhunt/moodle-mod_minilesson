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
 * Enable or disable a minilesson item type.
 *
 * @package    mod_minilesson
 * @copyright  2020 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$qtype   = required_param('qtype', PARAM_PLUGIN);
$action = required_param('action', PARAM_ALPHANUMEXT);

require_login();
require_capability('moodle/site:config', context_system::instance());
require_sesskey();

$url = new moodle_url('/mod/minilesson/manage.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_heading($SITE->fullname);

// Item types are enabled unless explicitly disabled, and the flag lives in the item type's own
// config namespace. See \mod_minilesson\plugininfo\minilessonitem::get_enabled_plugins().
$itemcomponent = \mod_minilesson\utils::get_sub_component($qtype);
switch ($action) {
    case 'enable':
        unset_config('disabled', $itemcomponent);
        core_plugin_manager::reset_caches();
        break;
    case 'disable':
        set_config('disabled', 1, $itemcomponent);
        core_plugin_manager::reset_caches();
        break;
    default:
        break;
}
$returnurl = new moodle_url('/mod/minilesson/itemtypes.php');
redirect($returnurl, get_string('successfullyupdated', 'mod_minilesson'));
