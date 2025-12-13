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
 * minilesson module admin settings and defaults
 *
 * @package    mod
 * @subpackage minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot.'/mod/minilesson/lib.php');

use mod_minilesson\constants;
use mod_minilesson\utils;

//if ($ADMIN->fulltree) {
if ($hassiteconfig) {

    //Add category to navigation
    $minilessoncat = new admin_category('modsettingsminilessoncat',
        get_string('modulename', constants::M_COMPONENT));//, $module->is_enabled() === false);
    $ADMIN->add('modsettings', $minilessoncat);

    //create main settings page
    $pagetitle = get_string('generalsettings', 'admin');
    $mainsettings = new admin_settingpage('modsettingminilessonmain', $pagetitle, 'moodle/site:config');

    // Add all the main settings
    $mainsettings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/apiuser',
        get_string('apiuser', constants::M_COMPONENT),
            get_string('apiuser_details', constants::M_COMPONENT), '', PARAM_TEXT));

    $cloudpoodllapiuser = get_config(constants::M_COMPONENT, 'apiuser');
    $cloudpoodllapisecret = get_config(constants::M_COMPONENT, 'apisecret');
    $showbelowapisecret = '';
    // If we have an API user and secret we fetch token.
    if (!empty($cloudpoodllapiuser) && !empty($cloudpoodllapisecret)) {
        $tokeninfo = utils::fetch_token_for_display($cloudpoodllapiuser, $cloudpoodllapisecret);
        $showbelowapisecret = $tokeninfo;
        // if we have no API user and secret we show a "fetch from elsewhere on site" or "take a free trial" link
    } else {
        $amddata = ['apppath' => $CFG->wwwroot . '/' .constants::M_URL];
        $cpcomponents = ['filter_poodll',
        'qtype_cloudpoodll',
        'mod_readaloud',
        'mod_wordcards',
        'mod_solo',
        'mod_englishcentral',
        'mod_pchat',
        'atto_cloudpoodll',
        'tinymce_cloudpoodll',
        'tiny_poodll',
        'assignsubmission_cloudpoodll',
        'assignfeedback_cloudpoodll',
        ];

        foreach ($cpcomponents as $cpcomponent) {
            switch($cpcomponent){
                case 'filter_poodll':
                    $apiusersetting = 'cpapiuser';
                    $apisecretsetting = 'cpapisecret';
                    break;
                case 'mod_englishcentral':
                    $apiusersetting = 'poodllapiuser';
                    $apisecretsetting = 'poodllapisecret';
                    break;
                default:
                    $apiusersetting = 'apiuser';
                    $apisecretsetting = 'apisecret';
            }
            $cloudpoodllapiuser = get_config($cpcomponent, $apiusersetting);
            if (!empty($cloudpoodllapiuser)) {
                $cloudpoodllapisecret = get_config($cpcomponent, $apisecretsetting);
                if (!empty($cloudpoodllapisecret)) {
                    $amddata['apiuser'] = $cloudpoodllapiuser;
                    $amddata['apisecret'] = $cloudpoodllapisecret;
                    break;
                }
            }
        }
        $showbelowapisecret = $OUTPUT->render_from_template( constants::M_COMPONENT . '/managecreds', $amddata);
    }


    // get_string('apisecret_details', constants::M_COMPONENT)
    $mainsettings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/apisecret',
        get_string('apisecret', constants::M_COMPONENT), $showbelowapisecret, '', PARAM_TEXT));


    $regions = utils::get_region_options();
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/awsregion',
            get_string('awsregion', constants::M_COMPONENT), '', 'useast1', $regions));


    // Default target language.
    $langoptions = utils::get_lang_options();
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/ttslanguage',
             get_string('ttslanguage', constants::M_COMPONENT), '', 'en-US', $langoptions));

    // Default learners native language.    
    $nativelangoptions = [0 => '--'] + utils::get_lang_options();
    $shortlangcodes = utils::get_shortlang_options();
    // Use the site default language as default native language or if that is not available use '--'.
    $nativelangdefault = $CFG->lang && array_key_exists($CFG->lang, $shortlangcodes) ? $shortlangcodes[$CFG->lang] : 0;
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/nativelang',
             get_string('nativelang', constants::M_COMPONENT), '', $nativelangdefault, $nativelangoptions));



    // Cloud Poodll Server.
    $mainsettings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/cloudpoodllserver',
        get_string('cloudpoodllserver', constants::M_COMPONENT),
            get_string('cloudpoodllserver_details', constants::M_COMPONENT),
             constants::M_DEFAULT_CLOUDPOODLL, PARAM_URL));

    // Transcriber options
    $name = 'transcriber';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::TRANSCRIBER_AUTO;
    $options = utils::fetch_options_transcribers();
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));

    $name = 'containerwidth';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::M_CONTWIDTH_COMPACT;
    $options = utils::get_containerwidth_options();
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));

    // Reports Table
    $name = 'reportstable';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::M_USE_DATATABLES;
    $options = utils::fetch_options_reportstable();
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));

    // animations
    $name = 'animations';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::M_ANIM_FANCY;
    $options = utils::fetch_options_animations();
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));


    $mainsettings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/itemsperpage',
        get_string('itemsperpage', constants::M_COMPONENT), get_string('itemsperpage_details', constants::M_COMPONENT), 10, PARAM_INT));


    $modalsettings = [0 => get_string('modaleditform_newpage', constants::M_COMPONENT),
        1 => get_string('modaleditform_modalform', constants::M_COMPONENT)];
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/modaleditform',
        get_string('modaleditform', constants::M_COMPONENT), get_string('modaleditform_details', constants::M_COMPONENT), 0, $modalsettings));


    $promptstyle = \mod_minilesson\utils::get_prompttype_options();
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/prompttype',
            get_string('prompttype', constants::M_COMPONENT), '', constants::M_PROMPT_SEPARATE, $promptstyle));


    $mainsettings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/enablepushtab',
        get_string('enablepushtab', constants::M_COMPONENT), get_string('enablepushtab_details', constants::M_COMPONENT), 0));

    $mainsettings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/alternatestreaming',
    get_string('alternatestreaming', constants::M_COMPONENT), get_string('alternatestreaming_details', constants::M_COMPONENT), 0));



    $mainsettings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/enablesetuptab',
            get_string('enablesetuptab', constants::M_COMPONENT), get_string('enablesetuptab_details', constants::M_COMPONENT), 0));

    // Native Language Setting
    $mainsettings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/setnativelanguage',
        get_string('enablenativelanguage', constants::M_COMPONENT), get_string('enablenativelanguage_details', constants::M_COMPONENT), 1));

    // Show item review.
    $name = 'showitemreview';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_help', constants::M_COMPONENT);
    $default = 1;
    $yesnooptions = [1 => get_string('yes'), 0 => get_string('no')];
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $yesnooptions));

    // Finish Screen Options
    $mainsettings->add(new admin_setting_heading(constants::M_COMPONENT . '/finishscreen', get_string('finishscreen', constants::M_COMPONENT), ''));
    $name = 'finishscreen';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::FINISHSCREEN_FULL;
    $options = utils::fetch_options_finishscreen();
    $mainsettings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));

    // Default custom Finish Screen
    $name = 'finishscreencustom';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    // The default custom finish screen ... a section with title, grade, stars and a try again button
    $default = '{{total}} %<br />
 {{#yellowstars}}<i class="fa fa-star"></i>{{/yellowstars}}{{#graystars}}<i class="fa fa-star-o"></i>{{/graystars}} <br />
<div class="container">
  {{#results}}
  <div class="row">
    <div class="col-sm">{{title}}</div>
    <div class="col-sm">{{grade}}%</div>
    <div class="col-sm"> {{#yellowstars}}<i class="fa fa-star"></i>{{/yellowstars}} {{#graystars}}<i class="fa fa-star-o"></i>{{/graystars}} <br /></div>
  </div>
{{/results}}
</div>
 <a class ="btn btn-secondary" href="{{{reattempturl}}}">{{#str}} tryagain, mod_minilesson {{/str}}</a> <br />';

    $mainsettings->add(new admin_setting_configtextarea(constants::M_COMPONENT . "/$name",
        $label, $details, $default, PARAM_RAW));

    // Add main settings page to minilesson category.
    $ADMIN->add('modsettingsminilessoncat', $mainsettings);

    // Manage Items Page.
    $manageitemspage = new admin_externalpage(
        'manageminilessonitem',
        get_string('manageminilessonitem', 'mod_minilesson'),
        new moodle_url('/mod/minilesson/itemtypes.php')
    );
    $ADMIN->add('modsettingsminilessoncat', $manageitemspage);

    //create audio chat settings page
    $pagetitle = get_string('audiochat', constants::M_COMPONENT);
    $audiochatsettings = new admin_settingpage('modsettingminilessonaudiochat', $pagetitle, 'moodle/site:config');

    // Audio chat settings.
    $audiochatsettings->add(new admin_setting_heading(constants::M_COMPONENT . '/audiochat', get_string('audiochat', constants::M_COMPONENT), ''));
    // The OpenAI APIKEY.
    $name = 'openaikey';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = '';
    $audiochatsettings->add(new admin_setting_configtext(constants::M_COMPONENT . "/$name",
        $label, $details, $default, PARAM_TEXT));

    // Audio Chat Prompts
    $maxprompts = constants::MAX_AI_PROMPTS;
    for ($i = 0; $i < $maxprompts; $i++) {
        //Audio Chat instructions prompt
        $defaults = 3;
        $name = 'audiochat_instructionspromptheading_' . ($i + 1);
        $label = get_string('instructionsprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
        $details = '';
        $default = $i < $defaults ? get_string('audiochat:instructionsprompt' . ($i + 1), constants::M_COMPONENT) : '';
        $audiochatsettings->add(new admin_setting_configtext(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_TEXT));
        $name = 'audiochat_instructionsprompt_' . ($i + 1);
        $label = get_string('instructionsprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
        $default = $i < $defaults ? get_string('audiochat:instructionsprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
        $audiochatsettings->add(new admin_setting_configtextarea(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_RAW));
    }
    for ($i = 0; $i < $maxprompts; $i++) {
        //Audio Chat feedback prompt
        $defaults = 2;
        $name = 'audiochat_feedbackpromptheading_' . ($i + 1);
        $label = get_string('feedbackprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
        $details = '';
        $default = $i < $defaults ? get_string('audiochat:feedbackprompt' . ($i + 1), constants::M_COMPONENT) : '';
        $audiochatsettings->add(new admin_setting_configtext(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_TEXT));
        $name = 'audiochat_feedbackprompt_' . ($i + 1);
        $label = get_string('feedbackprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
        $default = $i < $defaults ? get_string('audiochat:feedbackprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
        $audiochatsettings->add(new admin_setting_configtextarea(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_RAW));
    }
    //add audiochat settings page to minilesson category
    $ADMIN->add('modsettingsminilessoncat', $audiochatsettings);


    // Free speaking settings.
    $pagetitle = get_string('freespeaking', constants::M_COMPONENT);
    $freespeakingsettings = new admin_settingpage('modsettingminilessonfreespeaking', $pagetitle, 'moodle/site:config');

    $freespeakingsettings->add(new admin_setting_heading(constants::M_COMPONENT . '/freespeaking', get_string('freespeaking', constants::M_COMPONENT), ''));
    $maxprompts = constants::MAX_AI_PROMPTS;

    for ($i = 0; $i < $maxprompts; $i++) {
        //Free Speaking instructions prompt
        $defaults = 3;
        $name = 'freespeaking_gradingpromptheading_' . ($i + 1);
        $label = get_string('gradingprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
        $details = '';
        $default =  $i < $defaults ? get_string('freespeaking:gradingprompt' . ($i + 1), constants::M_COMPONENT) : '';
        $freespeakingsettings->add(new admin_setting_configtext(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_TEXT));
        $name = 'freespeaking_gradingprompt_' . ($i + 1);
        $label = get_string('gradingprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
        $default = $i < $defaults ? get_string('freespeaking:gradingprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
        $freespeakingsettings->add(new admin_setting_configtextarea(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_RAW));
    }
    for ($i = 0; $i < $maxprompts; $i++) {
        //Free Speaking Feedback Prompt
        $defaults = 2;
        $name = 'freespeaking_feedbackpromptheading_' . ($i + 1);
        $label = get_string('feedbackprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
        $details = '';
        $default = $i < $defaults ? get_string('freespeaking:feedbackprompt' . ($i + 1), constants::M_COMPONENT) : '';
        $freespeakingsettings->add(new admin_setting_configtext(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_TEXT));
        $name = 'freespeaking_feedbackprompt_' . ($i + 1);
        $label = get_string('feedbackprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
        $default = $i < $defaults ? get_string('freespeaking:feedbackprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
        $freespeakingsettings->add(new admin_setting_configtextarea(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_RAW));
    }
    // Add free speaking settings page to minilesson category.
    $ADMIN->add('modsettingsminilessoncat', $freespeakingsettings);

    // Free Writing settings.
    $pagetitle = get_string('freewriting', constants::M_COMPONENT);
    $freewritingsettings = new admin_settingpage('modsettingminilessonfreewriting', $pagetitle, 'moodle/site:config');
    $freewritingsettings->add(new admin_setting_heading(constants::M_COMPONENT . '/freewriting', get_string('freewriting', constants::M_COMPONENT), ''));
    $maxprompts = constants::MAX_AI_PROMPTS;

    for ($i = 0; $i < $maxprompts; $i++) {
        //Free Writing instructions prompt
        $defaults = 3;
        $name = 'freewriting_gradingpromptheading_' . ($i + 1);
        $label = get_string('gradingprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
        $details = '';
        $default = $i < $defaults ? get_string('freewriting:gradingprompt' . ($i + 1), constants::M_COMPONENT) : '';
        $freewritingsettings->add(new admin_setting_configtext(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_TEXT));
        $name = 'freewriting_gradingprompt_' . ($i + 1);
        $label = get_string('gradingprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
        $default = $i < $defaults ? get_string('freewriting:gradingprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
        $freewritingsettings->add(new admin_setting_configtextarea(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_RAW));
    }
    for ($i = 0; $i < $maxprompts; $i++) {
        //Free Writing Feedback Prompt
        $defaults = 2;
        $name = 'freewriting_feedbackpromptheading_' . ($i + 1);
        $label = get_string('feedbackprompt_header', constants::M_COMPONENT) . ' ' . ($i + 1);
        $details = '';
        $default = $i < $defaults ? get_string('freewriting:feedbackprompt' . ($i + 1), constants::M_COMPONENT) : '';
        $freewritingsettings->add(new admin_setting_configtext(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_TEXT));
        $name = 'freewriting_feedbackprompt_' . ($i + 1);
        $label = get_string('feedbackprompt', constants::M_COMPONENT) . ' ' . ($i + 1);
        $default = $i < $defaults ? get_string('freewriting:feedbackprompt_dec' . ($i + 1), constants::M_COMPONENT) : '';
        $freewritingsettings->add(new admin_setting_configtextarea(constants::M_COMPONENT . "/$name",
            $label, $details, $default, PARAM_RAW));
    }

    $mainsettings->add(new admin_setting_configcheckbox(
        constants::M_COMPONENT .  '/setlessonbank',
        get_string('enablelessonbank', constants::M_COMPONENT),
        get_string('enablelessonbank_details', constants::M_COMPONENT),
        0
    ));

    $mainsettings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/lessonbankurl',
        get_string('lessonbankurl', constants::M_COMPONENT),
            get_string('lessonbankurl_details', constants::M_COMPONENT),
            ''));

    // Add prompt settings page to minilesson category.
    $ADMIN->add('modsettingsminilessoncat', $freewritingsettings);

}
$settings = null; // We do not want standard settings link.
