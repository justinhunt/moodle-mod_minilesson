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
 * SpeechTester mod_minilesson
 *
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

use mod_minilesson\constants;
use mod_minilesson\utils;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n = optional_param('n', 0, PARAM_INT);  // minilesson instance ID



if ($id) {
    $cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(0, 'You must specify a course_module ID or an instance ID');
}

$PAGE->set_url(constants::M_URL . '/speechtester.php',
        ['id' => $cm->id]);
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:manage', $modulecontext);

// Get an admin settings
$config = get_config(constants::M_COMPONENT);

//recorder parameters
$forcestreaming  = optional_param('forcestreaming', 0, PARAM_INT);
$language  = optional_param('language', $moduleinstance->ttslanguage, PARAM_TEXT);
$stt_guided  = optional_param('stt_guided',  0, PARAM_INT);

// get token
$token = utils::fetch_token($config->apiuser, $config->apisecret);

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

if($config->enablesetuptab){
    $PAGE->set_pagelayout('popup');
}else{
    $PAGE->set_pagelayout('incourse');
}


// This puts all our display logic into the renderer.php files in this plugin
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

// From here we actually display the page.
echo $renderer->header($moduleinstance, $cm, 'speechtester', null, get_string('speechtester', constants::M_COMPONENT));

$tdata = [];
$tdata['cloudpoodllurl'] = utils::get_cloud_poodll_server();
$tdata['pageurl'] = $PAGE->url->out(false);
$tdata['language'] = $language;
$tdata['token'] = $token;
$tdata['region'] = $moduleinstance->region;
$tdata['cmid'] = $cm->id;
$tdata['waveheight'] = 75;
$tdata['forcestreaming'] = $forcestreaming;
$tdata['stt_guided'] = $stt_guided;
$tdata['checked_forcestreaming'] = $forcestreaming ? 'checked' : '';
$tdata['checked_stt_guided'] = $stt_guided ? 'checked' : '';
$isenglish = strpos($language, 'en') === 0;
if ($isenglish) {
    $tdata['speechtoken'] = utils::fetch_streaming_token($moduleinstance->region);
    $tdata['speechtokentype'] = 'assemblyai';
}
if ($stt_guided) {
    $items = $DB->get_records(constants::M_QTABLE, ['minilesson' => $moduleinstance->id], 'itemorder ASC');
    $longestitem = null;
    $longesttext = "";
    $longesthash = "";
    foreach($items as $item){
        $itemtext = $item->customtext1;
        if(core_text::strlen($itemtext) > core_Text::strlen($longesttext) && !empty($item->passagehash)){
            $longestitem = $item;
            $longesttext = $itemtext;
            $longesthash = explode('|', $item->passagehash)[1];
        }
    }
    if($longestitem) {

        $tdata['passagehash'] = $longesthash;
        $theitem = utils::fetch_item_from_itemrecord($longestitem, $moduleinstance, $modulecontext);
        switch($longestitem->type){
            case constants::TYPE_LGAPFILL:
            case constants::TYPE_TGAPFILL:
            case constants::TYPE_SGAPFILL:
                $sentences = [];
                $longesttext = "";
                if(isset($longestitem->customtext1)) {
                    $sentences = explode(PHP_EOL, $longestitem->customtext1);
                    $sentencedatas = $theitem->parse_gapfill_sentences($sentences);
                    foreach($sentencedatas as $sentencedata){
                        $longesttext .= $sentencedata->sentence . '<br/>';
                    }
                }
                break;
            case constants::TYPE_LISTENREPEAT:
            case constants::TYPE_SPEECHCARDS:
            default:
                break;
        }
        $tdata['guidedusetext'] = $longesttext;
    }
}

$showall = true;

// tts voices (data for template)
$ttsvoices = utils::get_tts_voices($moduleinstance->ttslanguage, $showall);
$voices = array_map(function($key, $value)  {
    return ['key' => $key, 'display' => $value];
}, array_keys($ttsvoices), $ttsvoices);
$tdata['voices'] = $voices;

// STT languages (data for template)
$ttslanguages = utils::get_lang_options();
$languages = array_map(function($key, $value) use ($language) {
    return ['key' => $key, 'display' => $value, 'selected' => $key == $language ? 'selected' : ''];
}, array_keys($ttslanguages), $ttslanguages);
$tdata['languages'] = $languages;
// set up the AMD js and related opts
$tdata['recorderid'] = constants::M_RECORDERID;
$tdata['uniqueid'] = "uniqueidforspeechtester";
$tdata['asrurl'] = utils::fetch_lang_server_url($moduleinstance->region, 'transcribe');

//Merge data with template and output to page
echo $renderer->render_from_template(constants::M_COMPONENT . '/speechtester', $tdata);


$jsonstring = json_encode($tdata);
$widgetid = constants::M_RECORDERID . '_opts_9999';
$optshtml =
        \html_writer::tag('input', '', ['id' => 'amdopts_' . $widgetid, 'type' => 'hidden', 'value' => $jsonstring]);
$opts = ['widgetid' => $widgetid];

// this inits the model audio helper JS
$PAGE->requires->js_call_amd("mod_minilesson/sthelper", 'init', [$opts]);
echo $optshtml;

// Finish the page
echo $renderer->footer();
return;
