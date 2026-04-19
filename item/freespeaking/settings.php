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
 * Settings for Free Speaking item subplugin.
 *
 * @package    minilessonitem_freespeaking
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_minilesson\constants;

defined('MOODLE_INTERNAL') || die();

/** @var \mod_minilesson\plugininfo\minilessonitem $plugininfo */
$settings = new admin_settingpage('modsettingminilessonfreespeaking', $plugininfo->displayname, 'moodle/site:config');
$settings->add(new admin_setting_heading(constants::M_COMPONENT . '/freespeaking', $plugininfo->displayname, ''));

$maxprompts = constants::MAX_AI_PROMPTS;

for ($i = 0; $i < $maxprompts; $i++) {
    // Free Speaking instructions prompt.
    $defaults = 3;
    $name = 'freespeaking_gradingpromptheading_' . ($i + 1);
    $label = get_string('gradingprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
    $details = '';
    $default = $i < $defaults ? get_string('freespeaking:gradingprompt' . ($i + 1), constants::M_COMPONENT) : '';
    $settings->add(new admin_setting_configtext(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        PARAM_TEXT
    ));
    $name = 'freespeaking_gradingprompt_' . ($i + 1);
    $label = get_string('gradingprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
    $default = $i < $defaults ? get_string('freespeaking:gradingprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
    $settings->add(new admin_setting_configtextarea(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        PARAM_RAW
    ));
}
for ($i = 0; $i < $maxprompts; $i++) {
    // Free Speaking Feedback Prompt.
    $defaults = 2;
    $name = 'freespeaking_feedbackpromptheading_' . ($i + 1);
    $label = get_string('feedbackprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
    $details = '';
    $default = $i < $defaults ? get_string('freespeaking:feedbackprompt' . ($i + 1), constants::M_COMPONENT) : '';
    $settings->add(new admin_setting_configtext(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        PARAM_TEXT
    ));
    $name = 'freespeaking_feedbackprompt_' . ($i + 1);
    $label = get_string('feedbackprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
    $default = $i < $defaults ? get_string('freespeaking:feedbackprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
    $settings->add(new admin_setting_configtextarea(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        PARAM_RAW
    ));
}
