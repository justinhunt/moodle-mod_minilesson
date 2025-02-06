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
 * Prints a particular instance of minilesson
 *
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
use mod_minilesson\constants;
use mod_minilesson\utils;
use mod_minilesson\mobile_auth;


$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$retake = optional_param('retake', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // minilesson instance ID - it should be named as the first character of the module
$embed = optional_param('embed', 0, PARAM_INT); // course_module ID, or

// Allow login through an authentication token.
$userid = optional_param('user_id', null, PARAM_ALPHANUMEXT);
$secret  = optional_param('secret', null, PARAM_RAW);
// formerly had !isloggedin() check, but we want tologin afresh on each embedded access
if(!empty($userid) && !empty($secret) ) {
    if (mobile_auth::has_valid_token($userid, $secret)) {
        $user = get_complete_user_data('id', $userid);
        complete_user_login($user);
        $embed = 2;
    }
}

if ($id) {
    $cm         = get_coursemodule_from_id('minilesson', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $moduleinstance  = $DB->get_record('minilesson', ['id' => $n], '*', MUST_EXIST);
    $course     = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('minilesson', $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}

$PAGE->set_url('/mod/minilesson/view.php', ['id' => $cm->id, 'retake' => $retake, 'embed' => $embed]);
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

// Trigger module viewed event.
$event = \mod_minilesson\event\course_module_viewed::create([
   'objectid' => $moduleinstance->id,
   'context' => $modulecontext,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('minilesson', $moduleinstance);
$event->trigger();


// if we got this far, we can consider the activity "viewed"
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// log usage to CloudPoodll
utils::stage_remote_process_job($moduleinstance->ttslanguage, $cm->id);

// are we a teacher or a student?
$mode = "view";

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

// Get an admin settings
$config = get_config(constants::M_COMPONENT);

// we want minilesson to embed nicely, or display according to layout settings
if($moduleinstance->foriframe == 1  || $moduleinstance->pagelayout == 'embedded' || $embed == 1){
    $PAGE->set_pagelayout('embedded');
}else if($config->enablesetuptab || $moduleinstance->pagelayout == 'popup' || $embed == 2){
    $PAGE->set_pagelayout('popup');
    $PAGE->add_body_class('poodll-minilesson-embed');
}else{
    if(has_capability('mod/' . constants::M_MODNAME . ':' . 'manage', $modulecontext)) {
        $PAGE->set_pagelayout('incourse');
    }else{
        $PAGE->set_pagelayout($moduleinstance->pagelayout);
    }
}


// Get our renderers
$renderer = $PAGE->get_renderer('mod_minilesson');

// get attempts
$attempts = $DB->get_records(constants::M_ATTEMPTSTABLE, ['moduleid' => $moduleinstance->id, 'userid' => $USER->id], 'timecreated DESC');


// can make a new attempt ?
$canattempt = true;
$canpreview = has_capability('mod/minilesson:canpreview', $modulecontext);
if(!$canpreview && $moduleinstance->maxattempts > 0){
    if($attempts && count($attempts) >= $moduleinstance->maxattempts){
        $canattempt = false;
    }
}

// create a new attempt or just fall through to no-items or finished modes
if(!$attempts || ($canattempt && $retake == 1)){
    $latestattempt = utils::create_new_attempt($moduleinstance->course, $moduleinstance->id);
}else{
    $latestattempt = reset($attempts);
}

// this library is licensed with the hippocratic license (https://github.com/EthicalSource/hippocratic-license/)
// which is not GPL3 compat. so cant be distributed with plugin. Hence we load it from CDN
if($config->animations == constants::M_ANIM_FANCY) {
    $PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css'));
}

// if we need a non standard font we can do that from here
if(!empty($moduleinstance->lessonfont)){
    if(!in_array($moduleinstance->lessonfont, constants::M_STANDARD_FONTS)){
        $PAGE->requires->css(new moodle_url('https://fonts.googleapis.com/css?family=' . $moduleinstance->lessonfont));
    }
}

// From here we actually display the page.
// if we are teacher we see tabs. If student we just see the quiz
// in mobile no tabs are shown
if(has_capability('mod/minilesson:evaluate', $modulecontext) && $embed != 2){
    echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('view', constants::M_COMPONENT));
}else{
    echo $renderer->notabsheader($moduleinstance, $embed);
}

$comptest = new \mod_minilesson\comprehensiontest($cm);
$itemcount = $comptest->fetch_item_count();

// show open close dates
$hasopenclosedates = $moduleinstance->viewend > 0 || $moduleinstance->viewstart > 0;
if($hasopenclosedates){
    echo $renderer->box($renderer->show_open_close_dates($moduleinstance), 'generalbox');

    $currenttime = time();
    $closed = false;
    if ( $currenttime > $moduleinstance->viewend && $moduleinstance->viewend > 0){
        echo get_string('activityisclosed', constants::M_COMPONENT);
        $closed = true;
    }else if($currenttime < $moduleinstance->viewstart){
        echo get_string('activityisnotopenyet', constants::M_COMPONENT);
        $closed = true;
    }
    // if we are not a teacher and the activity is closed/not-open leave at this point
    if(!has_capability('mod/minilesson:canpreview', $modulecontext) && $closed){
        echo $renderer->footer();
        exit;
    }
}

// instructions /intro if less then Moodle 4.0 show
if($CFG->version < 2022041900) {
    $introcontent = $renderer->show_intro($moduleinstance, $cm);
    echo $introcontent;
}else {
    $introcontent = '';
}

if($latestattempt->status == constants::M_STATE_COMPLETE){
    $teacherreport = false;
    echo $renderer->show_finished_results($comptest, $latestattempt, $cm, $canattempt, $embed, $teacherreport);
}else if($itemcount > 0) {
    echo $renderer->show_quiz($comptest, $moduleinstance);
    $previewid = 0;
    echo $renderer->fetch_activity_amd($cm, $moduleinstance, $previewid, $canattempt, $embed);
}else{
    $showadditemlinks = has_capability('mod/minilesson:evaluate', $modulecontext);
    echo $renderer->show_no_items($cm, $showadditemlinks);
}

// echo $renderer->load_app($cm, $moduleinstance, $latestattempt);

// backtotop
/*echo $renderer->backtotopbutton($course->id);*/

// Finish the page
echo $renderer->footer();
