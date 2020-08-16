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
 * Action for adding/editing a rsquestion.
 * replace i) MOD_poodlltime eg MOD_CST, then ii) poodlltime eg cst, then iii) rsquestion eg rsquestion
 *
 * @package mod_poodlltime
 * @copyright  2014 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

use \mod_poodlltime\constants;
use \mod_poodlltime\utils;

require_once("../../../config.php");
require_once($CFG->dirroot.'/mod/poodlltime/lib.php');


global $USER,$DB;

// first get the nfo passed in to set up the page
$itemid = optional_param('itemid',0 ,PARAM_INT);
$id     = required_param('id', PARAM_INT);         // Course Module ID
$type  = optional_param('type', constants::NONE, PARAM_TEXT);
$action = optional_param('action','edit',PARAM_TEXT);

// get the objects we need
$cm = get_coursemodule_from_id('poodlltime', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$poodlltime = $DB->get_record('poodlltime', array('id' => $cm->instance), '*', MUST_EXIST);

//make sure we are logged in and can see this form
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/poodlltime:itemedit', $context);

//set up the page object
$PAGE->set_url('/mod/poodlltime/rsquestion/managersquestions.php', array('itemid'=>$itemid, 'id'=>$id, 'type'=>$type));
$PAGE->set_title(format_string($poodlltime->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('course');

//are we in new or edit mode?
if ($itemid) {
    $item = $DB->get_record(constants::M_QTABLE, array('id'=>$itemid,constants::M_MODNAME => $cm->instance), '*', MUST_EXIST);
	if(!$item){
		print_error('could not find item of id:' . $itemid);
	}
    $type = $item->type;
    $edit = true;
} else {
    $edit = false;
}

//we always head back to the poodlltime items page
$redirecturl = new moodle_url('/mod/poodlltime/rsquestion/rsquestions.php', array('id'=>$cm->id));

	//handle delete actions
    if($action == 'confirmdelete'){
        //TODO more intelligent detection of question usage
    	$usecount = $DB->count_records(constants::M_ATTEMPTSTABLE,array(constants::M_MODNAME .'id'=>$cm->instance));
    	if($usecount>0){
    		redirect($redirecturl,get_string('iteminuse',constants::M_COMPONENT),10);
    	}

		$renderer = $PAGE->get_renderer(constants::M_COMPONENT);
		$rsquestion_renderer = $PAGE->get_renderer(constants::M_COMPONENT,'rsquestion');
		echo $renderer->header($poodlltime, $cm, 'rsquestions', null, get_string('confirmitemdeletetitle', constants::M_COMPONENT));
		echo $rsquestion_renderer->confirm(get_string("confirmitemdelete",constants::M_COMPONENT,$item->name),
			new moodle_url('/mod/poodlltime/rsquestion/managersquestions.php', array('action'=>'delete','id'=>$cm->id,'itemid'=>$itemid)),
			$redirecturl);
		echo $renderer->footer();
		return;

	/////// Delete item NOW////////
    }elseif ($action == 'delete'){
    	require_sesskey();
		$success = \mod_poodlltime\rsquestion\helper::delete_item($poodlltime,$itemid,$context);
        redirect($redirecturl);
    }elseif($action=="up" || $action=="down"){
        \mod_poodlltime\rsquestion\helper::move_item($poodlltime,$itemid,$action);
        redirect($redirecturl);
    }



//get filechooser and html editor options
$editoroptions = \mod_poodlltime\rsquestion\helper::fetch_editor_options($course, $context);
$filemanageroptions = \mod_poodlltime\rsquestion\helper::fetch_filemanager_options($course,1);


//get the mform for our item
switch($type){


    case constants::TYPE_MULTICHOICE:
        $mform = new \mod_poodlltime\rsquestion\multichoiceform(null,
                array('editoroptions'=>$editoroptions,
                        'filemanageroptions'=>$filemanageroptions,
                        'moduleinstance'=>$poodlltime)
        );
        break;

    case constants::TYPE_DICTATIONCHAT:
        $mform = new \mod_poodlltime\rsquestion\dictationchatform(null,
                array('editoroptions'=>$editoroptions,
                        'filemanageroptions'=>$filemanageroptions,
                        'moduleinstance'=>$poodlltime)
        );
        break;
    
    case constants::TYPE_DICTATION:
        $mform = new \mod_poodlltime\rsquestion\dictationform(null,
                array('editoroptions'=>$editoroptions,
                        'filemanageroptions'=>$filemanageroptions,
                        'moduleinstance'=>$poodlltime)
        );
        break;

    case constants::TYPE_SPEECHCARDS:
        $mform = new \mod_poodlltime\rsquestion\speechcardsform(null,
                array('editoroptions'=>$editoroptions,
                        'filemanageroptions'=>$filemanageroptions,
                        'moduleinstance'=>$poodlltime)
        );
        break;

    case constants::TYPE_LISTENREPEAT:
        $mform = new \mod_poodlltime\rsquestion\listenrepeatform(null,
                array('editoroptions'=>$editoroptions,
                        'filemanageroptions'=>$filemanageroptions,
                        'moduleinstance'=>$poodlltime)
        );
        break;

    case constants::TYPE_PAGE:
        $mform = new \mod_poodlltime\rsquestion\pageform(null,
                array('editoroptions'=>$editoroptions,
                        'filemanageroptions'=>$filemanageroptions,
                        'moduleinstance'=>$poodlltime)
        );
        break;

	case constants::NONE:
	default:
		print_error('No item type specifified');

}

//if the cancel button was pressed, we are out of here
if ($mform->is_cancelled()) {
    redirect($redirecturl);
    exit;
}

//if we have data, then our job here is to save it and return to the quiz edit page
if ($data = $mform->get_data()) {
		require_sesskey();
        $data->type=$type;
		$result = \mod_poodlltime\rsquestion\helper::update_insert_question($poodlltime,$data,$edit,$context,$cm,$editoroptions,$filemanageroptions);
		if($result->error==true){
            print_error($result->message);
            redirect($redirecturl);

        }else{
		    $theitem=$result->item;
        }

		//go back to edit quiz page
		redirect($redirecturl);
}


//if  we got here, there was no cancel, and no form data, so we are showing the form
//if edit mode load up the item into a data object
if ($edit) {
	$data = $item;		
	$data->itemid = $item->id;

    //init our item, we move the id fields around a little
    $data = file_prepare_standard_editor($data, constants::TEXTQUESTION , $editoroptions, $context, constants::M_COMPONENT,
            constants::TEXTQUESTION_FILEAREA,  $data->itemid);


}else{
	$data=new stdClass;
	$data->itemid = null;
	$data->visible = 1;
	$data->type=$type;
}
		
	//init our item, we move the id fields around a little 
    $data->id = $cm->id;
		

	//Set up the item type specific parts of the form data
	switch($type){
        case constants::TYPE_MULTICHOICE:
        case constants::TYPE_DICTATIONCHAT:
        case constants::TYPE_DICTATION:
        case constants::TYPE_SPEECHCARDS:
        case constants::TYPE_LISTENREPEAT:
        case constants::TYPE_PAGE:
		default:
	}
    $mform->set_data($data);
    $PAGE->navbar->add(get_string('edit'), new moodle_url('/mod/poodlltime/rsquestion/rsquestions.php', array('id'=>$id)));
    $PAGE->navbar->add(get_string('editingitem', constants::M_COMPONENT, get_string($mform->type, constants::M_COMPONENT)));
	$renderer = $PAGE->get_renderer('mod_poodlltime');
	$mode='rsquestions';
	echo $renderer->header($poodlltime, $cm,$mode, null, get_string('edit', constants::M_COMPONENT));
	$mform->display();
	echo $renderer->footer();