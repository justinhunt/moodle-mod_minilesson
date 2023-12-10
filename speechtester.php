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

use \mod_minilesson\constants;
use \mod_minilesson\utils;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n = optional_param('n', 0, PARAM_INT);  // minilesson instance ID


if ($id) {
    $cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $n), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(0,'You must specify a course_module ID or an instance ID');
}

$PAGE->set_url(constants::M_URL . '/speechtester.php',
        array('id' => $cm->id));
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:manage', $modulecontext);

//Get an admin settings 
$config = get_config(constants::M_COMPONENT);

//get token
$token = utils::fetch_token($config->apiuser,$config->apisecret);

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

if($config->enablesetuptab){
    $PAGE->set_pagelayout('popup');
}else{
    $PAGE->set_pagelayout('course');
}


//This puts all our display logic into the renderer.php files in this plugin
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

//From here we actually display the page.
echo $renderer->header($moduleinstance, $cm,'speechtester', null, get_string('speechtester', constants::M_COMPONENT));

$tdata=[];
$tdata['language']=$moduleinstance->ttslanguage;
$tdata['token']=$token;
$tdata['region']=$moduleinstance->region;
$tdata['cmid']=$cm->id;
$showall=true;

//tts voices
$ttsvoices=utils::get_tts_voices($moduleinstance->ttslanguage,$showall);
$voices = array_map(function($key, $value) {
    return ['key' => $key, 'display' => $value];
}, array_keys($ttsvoices), $ttsvoices);
$tdata['voices']=$voices;

//tts languages
$ttslanguages=utils::get_lang_options();
$languages = array_map(function($key, $value) {
    return ['key' => $key, 'display' => $value];
}, array_keys($ttslanguages), $ttslanguages);
$tdata['languages']=$languages;
echo $renderer->render_from_template(constants::M_COMPONENT . '/speechtester', $tdata);

//set up the AMD js and related opts
$tdata['recorderid']=constants::M_RECORDERID;
$tdata['asrurl']=utils::fetch_lang_server_url($moduleinstance->region,'transcribe');
$jsonstring = json_encode($tdata);
$widgetid = constants::M_RECORDERID . '_opts_9999';
$opts_html =
        \html_writer::tag('input', '', array('id' => 'amdopts_' . $widgetid, 'type' => 'hidden', 'value' => $jsonstring));
$opts = array('widgetid' => $widgetid);

//this inits the model audio helper JS
$PAGE->requires->js_call_amd("mod_minilesson/sthelper", 'init', array($opts));
echo $opts_html;

// Finish the page
echo $renderer->footer();
return;
