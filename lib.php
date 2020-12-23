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
 * Library of interface functions and constants for module poodlltime
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the poodlltime specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \mod_poodlltime\constants;
use \mod_poodlltime\utils;

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function poodlltime_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:         return true;
        case FEATURE_SHOW_DESCRIPTION:  return true;
		case FEATURE_COMPLETION_HAS_RULES: return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_GROUPINGS: return false;
        case FEATURE_GROUPS: return true;
        default:                        return null;
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the poodlltime.
 *
 * @param $mform form passed by reference
 */
function poodlltime_reset_course_form_definition(&$mform) {
    $mform->addElement('header', constants::M_MODNAME . 'header', get_string('modulenameplural', constants::M_COMPONENT));
    $mform->addElement('advcheckbox', 'reset_' . constants::M_MODNAME , get_string('deletealluserdata',constants::M_COMPONENT));
}

/**
 * Course reset form defaults.
 * @param object $course
 * @return array
 */
function poodlltime_reset_course_form_defaults($course) {
    return array('reset_' . constants::M_MODNAME =>1);
}


function poodlltime_editor_with_files_options($context){
	return array('maxfiles' => EDITOR_UNLIMITED_FILES,
               'noclean' => true, 'context' => $context, 'subdirs' => true);
}

function poodlltime_editor_no_files_options($context){
	return array('maxfiles' => 0, 'noclean' => true,'context'=>$context);
}
function poodlltime_picturefile_options($context){
    return array('maxfiles' => EDITOR_UNLIMITED_FILES,
        'noclean' => true, 'context' => $context, 'subdirs' => true, 'accepted_types' => array('image'));
}

/**
 * Removes all grades from gradebook
 *
 * @global stdClass
 * @global object
 * @param int $courseid
 * @param string optional type
 */
function poodlltime_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $sql = "SELECT l.*, cm.idnumber as cmidnumber, l.course as courseid
              FROM {" . constants::M_TABLE . "} l, {course_modules} cm, {modules} m
             WHERE m.name='" . constants::M_MODNAME . "' AND m.id=cm.module AND cm.instance=l.id AND l.course=:course";
    $params = array ("course" => $courseid);
    if ($moduleinstances = $DB->get_records_sql($sql,$params)) {
        foreach ($moduleinstances as $moduleinstance) {
            poodlltime_grade_item_update($moduleinstance, 'reset');
        }
    }
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * poodlltime attempts for course $data->courseid.
 *
 * @global stdClass
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function poodlltime_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', constants::M_COMPONENT);
    $status = array();

    if (!empty($data->{'reset_' . constants::M_MODNAME})) {
        $sql = "SELECT l.id
                         FROM {".constants::M_TABLE."} l
                        WHERE l.course=:course";

        $params = array ("course" => $data->courseid);
        $DB->delete_records_select(constants::M_ATTEMPTSTABLE, constants::M_MODNAME . "id IN ($sql)", $params);


        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            poodlltime_reset_gradebook($data->courseid);
        }

        $status[] = array('component'=>$componentstr, 'item'=>get_string('deletealluserdata', constants::M_COMPONENT), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates(constants::M_MODNAME, array('available', 'deadline'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}


/**
 * Create grade item for activity instance
 *
 * @category grade
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_NONE
 * @param object $moduleinstance object with extra cmidnumber
 * @param array|object $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function poodlltime_grade_item_update($moduleinstance, $grades=null) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $moduleinstance)) { //it may not be always present
        $params = array('itemname'=>$moduleinstance->name, 'idnumber'=>$moduleinstance->cmidnumber);
    } else {
        $params = array('itemname'=>$moduleinstance->name);
    }


    if ($moduleinstance->grade > 0) {
        $params['gradetype']  = GRADE_TYPE_VALUE;
        $params['grademax']   = $moduleinstance->grade;
        $params['grademin']   = 0;
    } else if ($moduleinstance->grade < 0) {
        $params['gradetype']  = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$moduleinstance->grade;

        // Make sure current grade fetched correctly from $grades
        $currentgrade = null;
        if (!empty($grades)) {
            if (is_array($grades)) {
                $currentgrade = reset($grades);
            } else {
                $currentgrade = $grades;
            }
        }

        // When converting a score to a scale, use scale's grade maximum to calculate it.
        if (!empty($currentgrade) && $currentgrade->rawgrade !== null) {
            $grade = grade_get_grades($moduleinstance->course, 'mod',
                    constants::M_MODNAME, $moduleinstance->id, $currentgrade->userid);
            $params['grademax']   = reset($grade->items)->grademax;
        }
    } else {
        $params['gradetype']  = GRADE_TYPE_NONE;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    } else if (!empty($grades)) {
        // Need to calculate raw grade (Note: $grades has many forms)
        if (is_object($grades)) {
            $grades = array($grades->userid => $grades);
        } else if (array_key_exists('userid', $grades)) {
            $grades = array($grades['userid'] => $grades);
        }
        foreach ($grades as $key => $grade) {
            if (!is_array($grade)) {
                $grades[$key] = $grade = (array) $grade;
            }
            //check raw grade isnt null otherwise we insert a grade of 0
            if ($grade['rawgrade'] !== null) {
                $grades[$key]['rawgrade'] = ($grade['rawgrade'] * $params['grademax'] / 100);
            } else {
                //setting rawgrade to null just in case user is deleting a grade
                $grades[$key]['rawgrade'] = null;
            }
        }
    }

    return grade_update('mod/' . constants::M_MODNAME,
            $moduleinstance->course, 'mod', constants::M_MODNAME, $moduleinstance->id, 0, $grades, $params);
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $moduleinstance
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone
 */
function poodlltime_update_grades($moduleinstance, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if ($moduleinstance->grade == 0) {
        poodlltime_grade_item_update($moduleinstance);

    } else if ($grades = poodlltime_get_user_grades($moduleinstance, $userid)) {
        poodlltime_grade_item_update($moduleinstance, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = null;
        poodlltime_grade_item_update($moduleinstance, $grade);

    } else {
        poodlltime_grade_item_update($moduleinstance);
    }
}

/**
 * Return grade for given user or all users.
 *
 * @global stdClass
 * @global object
 * @param int $moduleinstance
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function poodlltime_get_user_grades($moduleinstance, $userid=0) {
    global $CFG, $DB;

    $params = array("moduleid" => $moduleinstance->id);
    $cantranscribe = utils::can_transcribe($moduleinstance);

    if (!empty($userid)) {
        $params["userid"] = $userid;
        $user = "AND u.id = :userid";
    }
    else {
        $user="";

    }

    //human_sql
    $human_sql = "SELECT u.id, u.id AS userid, a.sessionscore AS rawgrade
                      FROM {user} u, {". constants::M_ATTEMPTSTABLE ."} a
                     WHERE a.id= (SELECT max(id) FROM {". constants::M_ATTEMPTSTABLE ."} ia WHERE ia.userid=u.id AND ia.poodlltimeid = a.poodlltimeid AND ia.status = " . constants::M_STATE_COMPLETE . ") ".
                     " AND u.id = a.userid AND a.poodlltimeid = :moduleid 
                           $user
                  GROUP BY u.id, a.sessionscore";


     $results = $DB->get_records_sql($human_sql, $params);

    //return results
    return $results;
}


function poodlltime_get_completion_state($course,$cm,$userid,$type) {
	return poodlltime_is_complete($course,$cm,$userid,$type);
}


//this is called internally only 
function poodlltime_is_complete($course,$cm,$userid,$type) {
	 global $CFG,$DB;
	 
	  global $CFG,$DB;

	// Get module object
    if(!($moduleinstance=$DB->get_record(constants::M_TABLE,array('id'=>$cm->instance)))) {
        throw new Exception("Can't find module with cmid: {$cm->instance}");
    }

    //check if the min grade condition is enabled
    if($moduleinstance->mingrade==0){
        return $type;
    }

	$idfield = 'a.' . constants::M_MODNAME . 'id';
	$params = array('moduleid'=>$moduleinstance->id, 'userid'=>$userid);
	$sql = "SELECT  MAX( sessionscore  ) AS grade
                      FROM {". constants::M_ATTEMPTSTABLE ."}
                     WHERE userid = :userid AND " . constants::M_MODNAME . "id = :moduleid" .
                     " AND status=" .constants::M_STATE_COMPLETE;
	$result = $DB->get_field_sql($sql, $params);
	if($result===false){return false;}
	 
	//check completion reqs against satisfied conditions
	switch ($type){
		case COMPLETION_AND:
			$success = $result >= $moduleinstance->mingrade;
			break;
		case COMPLETION_OR:
			$success = $result >= $moduleinstance->mingrade;
	}
	//return our success flag
	return $success;
}


/**
 * A task called from scheduled or adhoc
 *
 * @param progress_trace trace object
 *
 */
function poodlltime_dotask(progress_trace $trace) {
    $trace->output('executing dotask');
}

function poodlltime_get_editornames(){
	//return array('welcome');
    return array();
}

/**
 * Saves a new instance of the poodlltime into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $poodlltime An object from the form in mod_form.php
 * @param mod_poodlltime_mod_form $mform
 * @return int The id of the newly inserted poodlltime record
 */
function poodlltime_add_instance(stdClass $poodlltime, mod_poodlltime_mod_form $mform = null) {
    global $DB;

    $poodlltime->timecreated = time();
	$poodlltime = poodlltime_process_files($poodlltime,$mform);
    $poodlltime->id = $DB->insert_record(constants::M_TABLE, $poodlltime);

    if(!isset($poodlltime->cmidnumber)){
        $poodlltime->cmidnumber=null;
    }
    poodlltime_grade_item_update($poodlltime);

    return  $poodlltime->id;

}


function poodlltime_process_files(stdClass $poodlltime, mod_poodlltime_mod_form $mform = null) {
	global $DB;
    $cmid = $poodlltime->coursemodule;
    $context = context_module::instance($cmid);
	$editors = poodlltime_get_editornames();
	$itemid=0;
	$edoptions = poodlltime_editor_no_files_options($context);
	foreach($editors as $editor){
		$poodlltime = file_postupdate_standard_editor( $poodlltime, $editor, $edoptions,$context,constants::M_COMPONENT,$editor,$itemid);
	}

	return $poodlltime;
}

/**
 * Updates an instance of the poodlltime in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $poodlltime An object from the form in mod_form.php
 * @param mod_poodlltime_mod_form $mform
 * @return boolean Success/Fail
 */
function poodlltime_update_instance(stdClass $poodlltime, mod_poodlltime_mod_form $mform = null) {

    global $DB;

    $poodlltime->timemodified = time();
    $poodlltime->id = $poodlltime->instance;
    $poodlltime = poodlltime_process_files($poodlltime,$mform);
    $params = array('id' => $poodlltime->instance);
    $oldgradefield = $DB->get_field(constants::M_TABLE, 'grade', $params);


    $success = $DB->update_record(constants::M_TABLE, $poodlltime);

    if(!isset($poodlltime->cmidnumber)){
        $poodlltime->cmidnumber=null;
    }
    poodlltime_grade_item_update($poodlltime);
    $update_grades = ($poodlltime->grade === $oldgradefield ? false : true);
    if ($update_grades) {
        poodlltime_update_grades($poodlltime, 0, false);
    }

    return $success;
}

/**
 * Removes an instance of the poodlltime from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function poodlltime_delete_instance($id) {
    global $DB;

    if (! $poodlltime = $DB->get_record(constants::M_TABLE, array('id' => $id))) {
        return false;
    }

    # Delete any dependent records here #

    $DB->delete_records(constants::M_TABLE, array('id' => $poodlltime->id));

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function poodlltime_user_outline($course, $user, $mod, $poodlltime) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $poodlltime the module instance record
 * @return void, is supposed to echp directly
 */
function poodlltime_user_complete($course, $user, $mod, $poodlltime) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in poodlltime activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function poodlltime_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link poodlltime_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function poodlltime_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@see poodlltime_get_recent_mod_activity()}

 * @return void
 */
function poodlltime_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function poodlltime_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function poodlltime_get_extra_capabilities() {
    return array();
}

////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * Is a given scale used by the instance of poodlltime?
 *
 * This function returns if a scale is being used by one poodlltime
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $poodlltimeid ID of an instance of this module
 * @return bool true if the scale is used by the given poodlltime instance
 */
function poodlltime_scale_used($poodlltimeid, $scaleid) {
    global $DB;

    /** @example */
    if ($scaleid and $DB->record_exists(constants::M_TABLE, array('id' => $poodlltimeid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of poodlltime.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param $scaleid int
 * @return boolean true if the scale is used by any poodlltime instance
 */
function poodlltime_scale_used_anywhere($scaleid) {
    global $DB;

    /** @example */
    if ($scaleid and $DB->record_exists(constants::M_TABLE, array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}



////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function poodlltime_get_file_areas($course, $cm, $context) {
    return poodlltime_get_editornames();
}

/**
 * File browsing support for poodlltime file areas
 *
 * @package mod_poodlltime
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function poodlltime_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the poodlltime file areas
 *
 * @package mod_poodlltime
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the poodlltime's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function poodlltime_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
       global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);
	
	$itemid = (int)array_shift($args);

    require_course_login($course, true, $cm);

    if (!has_capability('mod/poodlltime:view', $context)) {
        return false;
    }


        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_poodlltime/$filearea/$itemid/$relativepath";

        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
          return false;
        }

        // finally send the file
        send_stored_file($file, null, 0, $forcedownload, $options);
}

function poodlltime_output_fragment_preview($args){
    global $DB,$PAGE;
    $args = (object) $args;
    $context = $args->context;

    $cm         = get_coursemodule_from_id('poodlltime', $context->instanceid, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record('poodlltime', array('id' => $cm->instance), '*', MUST_EXIST);

    $renderer = $PAGE->get_renderer('mod_poodlltime');
    $comp_test =  new \mod_poodlltime\comprehensiontest($cm);
    $ret = $renderer->show_quiz_preview($comp_test, $args->itemid);
    $ret .= $renderer->fetch_activity_amd($cm, $moduleinstance,$args->itemid);
    return $ret;
}

function poodlltime_output_fragment_mform($args) {
    global $CFG, $PAGE, $DB;

    $args = (object) $args;
    $context = $args->context;
    $formname = $args->formname;
    $mform= null;
    $o = '';



    list($ignored, $course) = get_context_info_array($context->id);

    //get filechooser and html editor options
    $editoroptions = \mod_poodlltime\rsquestion\helper::fetch_editor_options($course, $context);
    $filemanageroptions = \mod_poodlltime\rsquestion\helper::fetch_filemanager_options($course,3);

    // get the objects we need
    $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);

    if($args->itemid){
        $item = $DB->get_record(constants::M_QTABLE, array('id'=>$args->itemid,constants::M_MODNAME => $cm->instance),
                '*', MUST_EXIST);
        if($item) {
            $data = $item;
            $data->itemid = $item->id;
            $data->id = $cm->id;

            //If rich text, use editor otherwise use filepicker
            if($moduleinstance->richtextprompt==constants::M_PROMPT_RICHTEXT) {
                //init our editor field
                $data = file_prepare_standard_editor($data, constants::TEXTQUESTION, $editoroptions, $context,
                        constants::M_COMPONENT,
                        constants::TEXTQUESTION_FILEAREA, $data->itemid);
            }else{
                //init our itemmedia field
                $draftitemid = file_get_submitted_draft_itemid(constants::MEDIAQUESTION);
                file_prepare_draft_area($draftitemid, $context->id, constants::M_COMPONENT,
                        constants::MEDIAQUESTION, $data->itemid,
                        $filemanageroptions);
                $data->{constants::MEDIAQUESTION} = $draftitemid;

            }
        }
    }


    //get the mform for our item
    switch($formname){


        case constants::TYPE_MULTICHOICE:
            $mform = new \mod_poodlltime\rsquestion\multichoiceform(null,
                    array('editoroptions'=>$editoroptions,
                            'filemanageroptions'=>$filemanageroptions,
                            'moduleinstance'=>$moduleinstance)
            );
            break;

        case constants::TYPE_DICTATIONCHAT:
            $mform = new \mod_poodlltime\rsquestion\dictationchatform(null,
                    array('editoroptions'=>$editoroptions,
                            'filemanageroptions'=>$filemanageroptions,
                            'moduleinstance'=>$moduleinstance)
            );
            break;

        case constants::TYPE_DICTATION:
            $mform = new \mod_poodlltime\rsquestion\dictationform(null,
                    array('editoroptions'=>$editoroptions,
                            'filemanageroptions'=>$filemanageroptions,
                            'moduleinstance'=>$moduleinstance)
            );
            break;

        case constants::TYPE_SPEECHCARDS:
            $mform = new \mod_poodlltime\rsquestion\speechcardsform(null,
                    array('editoroptions'=>$editoroptions,
                            'filemanageroptions'=>$filemanageroptions,
                            'moduleinstance'=>$moduleinstance)
            );
            break;

        case constants::TYPE_LISTENREPEAT:
            $mform = new \mod_poodlltime\rsquestion\listenrepeatform(null,
                    array('editoroptions'=>$editoroptions,
                            'filemanageroptions'=>$filemanageroptions,
                            'moduleinstance'=>$moduleinstance)
            );
            break;

        case constants::TYPE_PAGE:
            $mform = new \mod_poodlltime\rsquestion\pageform(null,
                    array('editoroptions'=>$editoroptions,
                            'filemanageroptions'=>$filemanageroptions,
                            'moduleinstance'=>$moduleinstance)
            );
            break;

        case constants::TYPE_TEACHERTOOLS:
            $mform = new \mod_poodlltime\rsquestion\teachertoolsform(null,
                    array('editoroptions'=>$editoroptions,
                            'filemanageroptions'=>$filemanageroptions,
                            'moduleinstance'=>$moduleinstance)
            );
            break;

        case constants::TYPE_SHORTANSWER:
            $mform = new \mod_poodlltime\rsquestion\shortanswerform(null,
                    array('editoroptions'=>$editoroptions,
                            'filemanageroptions'=>$filemanageroptions,
                            'moduleinstance'=>$moduleinstance)
            );
            break;

        case constants::NONE:
        default:
            print_error('No item type specifified');
            return 0;

    }

   //if we have item data set it
    if($item){
        $mform->set_data($data);
    }

    if(!empty($mform)) {
        ob_start();
        $mform->display();
        $o .= ob_get_contents();
        ob_end_clean();
    }

    return $o;
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding poodlltime nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the poodlltime module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function poodlltime_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the poodlltime settings
 *
 * This function is called when the context for the page is a poodlltime module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $poodlltimenode {@link navigation_node}
 */
function poodlltime_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $poodlltimenode=null) {
}

function mod_poodlltime_get_fontawesome_icon_map() {
    return [
        'mod_poodlltime:print' => 'fa-print',
        'mod_poodlltime:volume-up' => 'fa-volume-up',
        'mod_poodlltime:close' => 'fa-close'
    ];
}
