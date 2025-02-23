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

if ($ADMIN->fulltree) {

    /*
    $settings->add(new admin_setting_configtextarea(constants::M_COMPONENT .  '/defaultwelcome',
        get_string('welcomelabel', constants::M_COMPONENT), get_string('welcomelabel_details', constants::M_COMPONENT), get_string('defaultwelcome',constants::M_COMPONENT), PARAM_TEXT));
    */

    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/apiuser',
        get_string('apiuser', constants::M_COMPONENT),
            get_string('apiuser_details', constants::M_COMPONENT), '', PARAM_TEXT));

    $cloudpoodllapiuser = get_config(constants::M_COMPONENT, 'apiuser');
    $cloudpoodllapisecret = get_config(constants::M_COMPONENT, 'apisecret');
    $showbelowapisecret = '';
    // if we have an API user and secret we fetch token
    if(!empty($cloudpoodllapiuser) && !empty($cloudpoodllapisecret)) {
        $tokeninfo = utils::fetch_token_for_display($cloudpoodllapiuser, $cloudpoodllapisecret);
        $showbelowapisecret = $tokeninfo;
        // if we have no API user and secret we show a "fetch from elsewhere on site" or "take a free trial" link
    }else{
        $amddata = ['apppath' => $CFG->wwwroot . '/' .constants::M_URL];
        $cpcomponents = ['filter_poodll', 'qtype_cloudpoodll', 'mod_readaloud', 'mod_wordcards', 'mod_solo', 'mod_englishcentral', 'mod_pchat',
            'atto_cloudpoodll', 'tinymce_cloudpoodll', 'assignsubmission_cloudpoodll', 'assignfeedback_cloudpoodll'];
        foreach($cpcomponents as $cpcomponent){
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
            if(!empty($cloudpoodllapiuser)){
                $cloudpoodllapisecret = get_config($cpcomponent, $apisecretsetting);
                if(!empty($cloudpoodllapisecret)){
                    $amddata['apiuser'] = $cloudpoodllapiuser;
                    $amddata['apisecret'] = $cloudpoodllapisecret;
                    break;
                }
            }
        }
        $showbelowapisecret = $OUTPUT->render_from_template( constants::M_COMPONENT . '/managecreds', $amddata);
    }


    // get_string('apisecret_details', constants::M_COMPONENT)
    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/apisecret',
        get_string('apisecret', constants::M_COMPONENT), $showbelowapisecret, '', PARAM_TEXT));


    $regions = \mod_minilesson\utils::get_region_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/awsregion',
            get_string('awsregion', constants::M_COMPONENT), '', 'useast1', $regions));


    $langoptions = \mod_minilesson\utils::get_lang_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/ttslanguage',
             get_string('ttslanguage', constants::M_COMPONENT), '', 'en-US', $langoptions));

    // Transcriber options
    $name = 'transcriber';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::TRANSCRIBER_AUTO;
    $options = utils::fetch_options_transcribers();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));

    $name = 'containerwidth';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::M_CONTWIDTH_COMPACT;
    $options = utils::get_containerwidth_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));

    // Reports Table
    $name = 'reportstable';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::M_USE_DATATABLES;
    $options = utils::fetch_options_reportstable();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));

    // animations
    $name = 'animations';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::M_ANIM_FANCY;
    $options = utils::fetch_options_animations();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $options));


    $settings->add(new admin_setting_configtext(constants::M_COMPONENT .  '/itemsperpage',
        get_string('itemsperpage', constants::M_COMPONENT), get_string('itemsperpage_details', constants::M_COMPONENT), 10, PARAM_INT));


    $modalsettings = [0 => get_string('modaleditform_newpage', constants::M_COMPONENT),
        1 => get_string('modaleditform_modalform', constants::M_COMPONENT)];
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/modaleditform',
        get_string('modaleditform', constants::M_COMPONENT), get_string('modaleditform_details', constants::M_COMPONENT), 0, $modalsettings));


    $promptstyle = \mod_minilesson\utils::get_prompttype_options();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT .  '/prompttype',
            get_string('prompttype', constants::M_COMPONENT), '', constants::M_PROMPT_SEPARATE, $promptstyle));


    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/enablepushtab',
        get_string('enablepushtab', constants::M_COMPONENT), get_string('enablepushtab_details', constants::M_COMPONENT), 0));

    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/alternatestreaming',
    get_string('alternatestreaming', constants::M_COMPONENT), get_string('alternatestreaming_details', constants::M_COMPONENT), 0));



    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/enablesetuptab',
            get_string('enablesetuptab', constants::M_COMPONENT), get_string('enablesetuptab_details', constants::M_COMPONENT), 0));

    // Native Language Setting
    $settings->add(new admin_setting_configcheckbox(constants::M_COMPONENT .  '/setnativelanguage',
        get_string('enablenativelanguage', constants::M_COMPONENT), get_string('enablenativelanguage_details', constants::M_COMPONENT), 1));

    // Show item review.
    $name = 'showitemreview';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_help', constants::M_COMPONENT);
    $default = 1;
    $yesnooptions = [1 => get_string('yes'), 0 => get_string('no')];
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
        $label, $details, $default, $yesnooptions));

    // Finish Screen Options
    $name = 'finishscreen';
    $label = get_string($name, constants::M_COMPONENT);
    $details = get_string($name . '_details', constants::M_COMPONENT);
    $default = constants::FINISHSCREEN_FULL;
    $options = utils::fetch_options_finishscreen();
    $settings->add(new admin_setting_configselect(constants::M_COMPONENT . "/$name",
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

    $settings->add(new admin_setting_configtextarea(constants::M_COMPONENT . "/$name",
        $label, $details, $default, PARAM_RAW));



}
