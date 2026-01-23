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

use mod_minilesson\constants;

/**
 * This file replaces the legacy STATEMENTS section in db/install.xml,
 * lib.php/modulename_install() post installation hook and partially defaults.php
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post installation procedure
 *
 * @see upgrade_plugins_modules()
 */
function xmldb_minilesson_install()
{
    global $DB;

    // Create default templates if they do not exist.
    $templates = \mod_minilesson\aigen::fetch_lesson_templates();
    if (!$templates || empty($templates)) {
        \mod_minilesson\aigen::create_default_templates();
    }
    // Add any other post-installation tasks here.
    $qtypes = constants::ITEMTYPES;
    //remove dictation chat
    $key = array_search('dictationchat', $qtypes);
    if ($key !== false) {
        unset($qtypes[$key]);
    }
    set_config('enableditems', implode(',', $qtypes), 'minilesson');
}

/**
 * Post installation recovery procedure
 *
 * @see upgrade_plugins_modules()
 */
function xmldb_minilesson_install_recovery()
{
}
