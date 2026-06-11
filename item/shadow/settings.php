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
 * Admin settings for the Video Shadowing item type.
 *
 * @package    minilessonitem_shadow
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Create video shadowing settings page.

/** @var \mod_minilesson\plugininfo\minilessonitem $plugininfo */
$settings = new admin_settingpage('modsettingminilessonshadow', $plugininfo->displayname, 'moodle/site:config');

$settings->add(new admin_setting_heading('minilessonitem_shadow/heading', $plugininfo->displayname, ''));

$settings->add(
    new admin_setting_configcheckbox(
        'minilessonitem_shadow/enablesubtitlefetch',
        get_string('enablesubtitlefetch', 'minilessonitem_shadow'),
        get_string('enablesubtitlefetch_details', 'minilessonitem_shadow'),
        0
    )
);
