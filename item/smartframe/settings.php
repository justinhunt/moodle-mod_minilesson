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
 * Settings for the widget item: where widgets are published and served from.
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use minilessonitem_smartframe\local\publisher;
use minilessonitem_smartframe\local\publisher_local;

defined('MOODLE_INTERNAL') || die();

/** @var \mod_minilesson\plugininfo\minilessonitem $plugininfo */
$settings = new admin_settingpage('modsettingminilessonsmartframe', $plugininfo->displayname, 'moodle/site:config');

$component = 'minilessonitem_smartframe';
$settings->add(new admin_setting_heading(
    $component . '/widgethost',
    get_string('widgethost', $component),
    get_string('widgethost_desc', $component)
));

$settings->add(new admin_setting_configselect(
    $component . '/publisher',
    get_string('publisher', $component),
    get_string('publisher_desc', $component),
    publisher::CLOUDPOODLL,
    [
        publisher::CLOUDPOODLL => get_string('publisher_cloudpoodll', $component),
        publisher::LOCAL => get_string('publisher_local', $component),
    ]
));

$settings->add(new admin_setting_configtext(
    $component . '/cdnbaseurl',
    get_string('cdnbaseurl', $component),
    get_string('cdnbaseurl_desc', $component),
    '',
    PARAM_URL
));

$settings->add(new admin_setting_configtext(
    $component . '/localbaseurl',
    get_string('localbaseurl', $component),
    get_string('localbaseurl_desc', $component, publisher_local::get_root()),
    '',
    PARAM_URL
));
