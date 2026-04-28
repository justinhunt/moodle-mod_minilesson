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
 * TODO describe file settings
 *
 * @package    minilessonitem_audiochat
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use minilessonitem_audiochat\itemtype;
use mod_minilesson\constants;

defined('MOODLE_INTERNAL') || die();

// Create audio chat settings page.

/** @var \mod_minilesson\plugininfo\minilessonitem $plugininfo */
$settings = new admin_settingpage('modsettingminilessonaudiochat', $plugininfo->displayname, 'moodle/site:config');

// Audio chat settings.
$settings->add(new admin_setting_heading(constants::M_COMPONENT . '/audiochat', $plugininfo->displayname, ''));

$settings->add(
    new admin_setting_configselect(
        constants::M_COMPONENT . "/provider",
        get_string('provider', 'minilessonitem_audiochat'),
        '',
        itemtype::PROVIDER_OPENAI,
        [
            itemtype::PROVIDER_OPENAI => get_string('openai', 'minilessonitem_audiochat'),
            itemtype::PROVIDER_GEMINI => get_string('gemini', 'minilessonitem_audiochat'),
        ]
    )
);

// Audio Chat Prompts.
$maxprompts = constants::MAX_AI_PROMPTS;
for ($i = 0; $i < $maxprompts; $i++) {
    // Audio Chat instructions prompt.
    $defaults = 3;
    $name = 'audiochat_instructionspromptheading_' . ($i + 1);
    $label = get_string('instructionsprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
    $details = '';
    $default = $i < $defaults ? get_string('audiochat:instructionsprompt' . ($i + 1), constants::M_COMPONENT) : '';
    $settings->add(new admin_setting_configtext(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        PARAM_TEXT
    ));
    $name = 'audiochat_instructionsprompt_' . ($i + 1);
    $label = get_string('instructionsprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
    $default = $i < $defaults ? get_string('audiochat:instructionsprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
    $settings->add(new admin_setting_configtextarea(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        PARAM_RAW
    ));
}
for ($i = 0; $i < $maxprompts; $i++) {
    // Audio Chat feedback prompt.
    $defaults = 2;
    $name = 'audiochat_feedbackpromptheading_' . ($i + 1);
    $label = get_string('feedbackprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
    $details = '';
    $default = $i < $defaults ? get_string('audiochat:feedbackprompt' . ($i + 1), constants::M_COMPONENT) : '';
    $settings->add(new admin_setting_configtext(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        PARAM_TEXT
    ));
    $name = 'audiochat_feedbackprompt_' . ($i + 1);
    $label = get_string('feedbackprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
    $default = $i < $defaults ? get_string('audiochat:feedbackprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
    $settings->add(new admin_setting_configtextarea(
        constants::M_COMPONENT . "/$name",
        $label,
        $details,
        $default,
        PARAM_RAW
    ));
}
