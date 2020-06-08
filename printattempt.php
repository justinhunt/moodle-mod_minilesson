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
 * Prints a particular instance of poodlltime
 *
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
use \mod_poodlltime\constants;




$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // poodlltime instance ID - it should be named as the first character of the module
$attemptid = required_param('attemptid', PARAM_INT); //attempt ID, or
$mode = optional_param('mode', 'manual', PARAM_RAW);

if ($id) {
    $cm         = get_coursemodule_from_id('poodlltime', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record('poodlltime', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $moduleinstance  = $DB->get_record('poodlltime', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('poodlltime', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}

$PAGE->set_url('/mod/poodlltime/printattempt.php', array('id' => $cm->id,'attemptid'=>$attemptid));
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

$PAGE->set_pagelayout('print');

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

//Get our renderers
$renderer = $PAGE->get_renderer('mod_poodlltime');

//if the attemptid or activity is wrong, just quit
$attempt = $DB->get_record(constants::M_USERTABLE,array('id'=>$attemptid,'poodlltimeid'=>$moduleinstance->id));
if(!$attempt){
    echo $renderer->notabsheader();
    echo $renderer->thatsnotright(get_string('invalidattempt',constants::M_COMPONENT));
    // Finish the page
    echo $renderer->footer();
    return;
}

//if owner or teacher, ok. Else, sayonara
$is_teacher=false;
if(!has_capability('mod/' . constants::M_MODNAME . ':' . 'evaluate',$modulecontext)) {
    if(!$USER->id == $attempt->userid){
        echo $renderer->notabsheader();
        echo $renderer->thatsnotright(get_string('notyourattempt',constants::M_COMPONENT));
        // Finish the page
        echo $renderer->footer();
        return;
    }
}else{
    $is_teacher=true;
}

//if not finished, do not print?
if(empty($attempt->qanswer1)){
    echo $renderer->notabsheader();
    echo $renderer->thatsnotright(get_string('notfinished',constants::M_COMPONENT));
    // Finish the page
    echo $renderer->footer();
    return;
}


//Get an admin settings 
$config = get_config(constants::M_COMPONENT);

//fetch ai grade data
$aigrade =false;
if(\mod_poodlltime\utils::can_transcribe($moduleinstance)) {
    $aigrade = new \mod_poodlltime\aigrade($attempt->id, $modulecontext->id);
}

//fetch display stuff
$readonly=true;
$have_humaneval = $attempt->sessiontime!=null;
$have_aieval = $aigrade && $aigrade->has_transcripts();
//show eval and errors in the case that we are teacher, else follow view page pattern
$postattempt_options = $is_teacher ? constants::POSTATTEMPT_EVALERRORS : $moduleinstance->humanpostattempt;
$gradenow = new \mod_poodlltime\gradenow($attempt->id,$modulecontext->id);

//Print running records report
$PAGE->requires->css(new \moodle_url('/blocks/poodlltimestudent/fonts/fonts.css'));
//set up qr code amd
$opts=array();
$opts['size']=4;
$opts['margin']=4;
$opts['selector']='#' . constants::M_QR_PLAYER;
$PAGE->requires->js_call_amd("mod_poodlltime/qrcodemaker", 'init', array($opts));
$PAGE->requires->jquery();
$gradenowrenderer = $PAGE->get_renderer(constants::M_COMPONENT,'gradenow');
$markeduppassage = $gradenowrenderer->render_markeduppassage($gradenow->attemptdetails('passage'));
$templateable = new \mod_poodlltime\output\mustache_output($moduleinstance, $gradenow, $markeduppassage, $mode);
$data = $templateable->export_for_template($renderer);
$corerenderer = $PAGE->get_renderer('core');
echo $corerenderer->header();
echo $corerenderer->render_from_template('mod_poodlltime/printable-report', $data);

if($have_humaneval) {
    $reviewmode = constants::REVIEWMODE_HUMAN;
    $force_aidata=false;
} else {
    $reviewmode =constants::REVIEWMODE_MACHINE;
    $force_aidata=true;
}
echo $gradenow->prepare_javascript($reviewmode,$force_aidata,$readonly);
echo $corerenderer->footer();
return;

//==================================================================================
//this also works, possibly for allowing students to print
//echo $renderer->notabsheader();
//if( $have_humaneval || $have_aieval){
//    switch($postattempt_options){
//        case constants::POSTATTEMPT_NONE:
//            echo $renderer->show_title_postattempt($moduleinstance,$moduleinstance->name);
//            echo $renderer->show_twocol_summary($moduleinstance,$cm,$gradenow);
//            //echo $renderer->show_passage_postattempt($moduleinstance,$cm);
//            break;
//        case constants::POSTATTEMPT_EVAL:
//            echo $renderer->show_title_postattempt($moduleinstance,$moduleinstance->name);
//            if( $have_humaneval) {
//                echo $renderer->show_humanevaluated_message();
//                $force_aidata=false;
//            }else{
//                echo $renderer->show_machineevaluated_message();
//                $force_aidata=true;
//            }
//
//            $reviewmode =constants::REVIEWMODE_SCORESONLY;
//            echo $gradenow->prepare_javascript($reviewmode,$force_aidata,$readonly);
//            $markeduppassage=$gradenowrenderer->render_markeduppassage($gradenow->attemptdetails('passage'));
//            echo $renderer->show_twocol_summary($moduleinstance,$cm,$gradenow,$markeduppassage);
//            break;
//
//        case constants::POSTATTEMPT_EVALERRORS:
//            echo $renderer->show_title_postattempt($moduleinstance,$moduleinstance->name);
//            if( $have_humaneval) {
//                echo $renderer->show_humanevaluated_message();
//                $reviewmode = constants::REVIEWMODE_HUMAN;
//                $force_aidata=false;
//            }else{
//                echo $renderer->show_machineevaluated_message();
//                $reviewmode =constants::REVIEWMODE_MACHINE;
//                $force_aidata=true;
//            }
//            echo $gradenow->prepare_javascript($reviewmode,$force_aidata,$readonly);
//            echo $gradenowrenderer->render_hiddenaudioplayer();
//
//            $markeduppassage=$gradenowrenderer->render_markeduppassage($gradenow->attemptdetails('passage'));
//            echo $renderer->show_twocol_summary($moduleinstance,$cm,$gradenow,$markeduppassage);
//            break;
//    }
//}else{
//    echo $renderer->show_title_postattempt($moduleinstance,$moduleinstance->name);
//    echo $renderer->show_ungradedyet();
//    echo $renderer->show_twocol_summary($moduleinstance,$cm,$gradenow);
//}
//// Finish the page
//echo $renderer->footer();
