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
$retake = optional_param('retake', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // poodlltime instance ID - it should be named as the first character of the module

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

$PAGE->set_url('/mod/poodlltime/view.php', array('id' => $cm->id));
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

// Trigger module viewed event.
$event = \mod_poodlltime\event\course_module_viewed::create(array(
   'objectid' => $moduleinstance->id,
   'context' => $modulecontext
));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('poodlltime', $moduleinstance);
$event->trigger();


//if we got this far, we can consider the activity "viewed"
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

//are we a teacher or a student?
$mode= "view";

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);


//if admin allow around site and to see edit stuff
//if(has_capability('mod/' . constants::M_MODNAME . ':' . 'canmanageattempts',$modulecontext)) {
if(has_capability('mod/' . constants::M_MODNAME . ':' . 'manage',$modulecontext)) {
    $PAGE->set_pagelayout('standard');
}else{
    $PAGE->set_pagelayout($moduleinstance->pagelayout);
}

//Get an admin settings 
$config = get_config(constants::M_COMPONENT);

//Get our renderers
$renderer = $PAGE->get_renderer('mod_poodlltime');

//if we are in review mode, lets review
$attempts = $DB->get_records(constants::M_USERTABLE,array('userid'=>$USER->id,'poodlltimeid'=>$moduleinstance->id),'id DESC');
$ai_evals = \mod_poodlltime\utils::get_aieval_byuser($moduleinstance->id,$USER->id);

//can attempt ?
$canattempt = true;
$canpreview = has_capability('mod/poodlltime:canpreview',$modulecontext);
if(!$canpreview && $moduleinstance->maxattempts > 0){
	$attempts =  $DB->get_records(constants::M_USERTABLE,array('userid'=>$USER->id, constants::M_MODNAME.'id'=>$moduleinstance->id),'timecreated DESC');
	if($attempts && count($attempts)>=$moduleinstance->maxattempts){
		$canattempt=false;
	}
}

//reset our retake flag if we cant reatempt
if(!$canattempt){$retake=0;}

// Get the last attempt if there is one, and we're not re-attempting the quiz.
$latestattempt = !$retake ? ($attempts ? array_shift($attempts) : null) : null;

// Check if last attempt is finished, at this point we only check if at least an answer was provided.
$islastattemptfinished = !$latestattempt || !empty($latestattempt->qanswer1);


//From here we actually display the page.
//if we are teacher we see tabs. If student we just see the quiz
if(has_capability('mod/poodlltime:evaluate',$modulecontext)){
	echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('view', constants::M_COMPONENT));
}else{
	echo $renderer->notabsheader();
}

//the module AMD code
echo $renderer->show_quiz($cm,$moduleinstance);
echo $renderer->fetch_activity_amd($cm, $moduleinstance);

//echo $renderer->load_app($cm, $moduleinstance, $latestattempt);

//backtotop
echo $renderer->backtotopbutton($course->id);

// Finish the page
echo $renderer->footer();
