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
 * replace i) MOD_minilesson eg MOD_CST, then ii) minilesson eg cst, then iii) rsquestion eg rsquestion
 *
 * @package mod_minilesson
 * @copyright  2014 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

use mod_minilesson\constants;
use mod_minilesson\utils;
use mod_minilesson\local\itemtype\item;

require_once("../../../config.php");
require_once($CFG->dirroot.'/mod/minilesson/lib.php');


global $USER, $DB;

// first get the nfo passed in to set up the page
$itemid = optional_param('itemid', 0 , PARAM_INT);
$id     = required_param('id', PARAM_INT);         // Course Module ID
$type  = optional_param('type', constants::NONE, PARAM_TEXT);
$action = optional_param('action', 'edit', PARAM_TEXT);

// get the objects we need
$cm = get_coursemodule_from_id('minilesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$minilesson = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);

// make sure we are logged in and can see this form
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/minilesson:itemedit', $context);

// set up the page object
$PAGE->set_url('/mod/minilesson/rsquestion/managersquestions.php', ['itemid' => $itemid, 'id' => $id, 'type' => $type]);
$PAGE->set_title(format_string($minilesson->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

// are we in new or edit mode?
if ($itemid) {
    $item = $DB->get_record(constants::M_QTABLE, ['id' => $itemid, constants::M_MODNAME => $cm->instance], '*', MUST_EXIST);
    if(!$item){
        print_error('could not find item of id:' . $itemid);
    }
    $type = $item->type;
    $edit = true;
} else {
    $edit = false;
}

// we always head back to the minilesson items page
$redirecturl = new moodle_url('/mod/minilesson/rsquestion/rsquestions.php', ['id' => $cm->id]);

    // handle delete actions
if($action == 'confirmdelete'){
    // TODO more intelligent detection of question usage
    $usecount = $DB->count_records(constants::M_ATTEMPTSTABLE, [constants::M_MODNAME .'id' => $cm->instance]);
    if($usecount > 0){
         redirect($redirecturl, get_string('iteminuse', constants::M_COMPONENT), 10);
    }

        $renderer = $PAGE->get_renderer(constants::M_COMPONENT);
        $rsquestionrenderer = $PAGE->get_renderer(constants::M_COMPONENT, 'rsquestion');
        echo $renderer->header($minilesson, $cm, 'rsquestions', null, get_string('confirmitemdeletetitle', constants::M_COMPONENT));
        echo $rsquestionrenderer->confirm(get_string("confirmitemdelete", constants::M_COMPONENT, $item->name),
        new moodle_url('/mod/minilesson/rsquestion/managersquestions.php', ['action' => 'delete', 'id' => $cm->id, 'itemid' => $itemid]),
        $redirecturl);
    echo $renderer->footer();
    return;

    /////// Delete item NOW////////
}else if ($action == 'delete'){
    require_sesskey();
    $success = \mod_minilesson\local\itemtype\item::delete_item($itemid, $context);
    redirect($redirecturl);
}else if($action == "up" || $action == "down"){
    \mod_minilesson\local\itemform\helper::move_item($minilesson, $itemid, $action);
    redirect($redirecturl);
}



// get filechooser and html editor options
$editoroptions = \mod_minilesson\local\itemtype\item::fetch_editor_options($course, $context);
$filemanageroptions = \mod_minilesson\local\itemtype\item::fetch_filemanager_options($course, 3);

$itemformclass  = utils::fetch_itemform_classname($type);
if(!$itemformclass){
    print_error('No item type specified');
    return 0;
}
$mform = new $itemformclass(null,
    ['editoroptions' => $editoroptions,
        'filemanageroptions' => $filemanageroptions,
        'moduleinstance' => $minilesson]
);


// if the cancel button was pressed, we are out of here
if ($mform->is_cancelled()) {
    redirect($redirecturl);
    exit;
}

// if we have data, then our job here is to save it and return to the quiz edit page
if ($data = $mform->get_data()) {
    require_sesskey();
        $data->type = $type;

    if($edit){
        $theitem = utils::fetch_item_from_itemrecord($data, $minilesson);
        $olditem = $item;
    }else{
        $theitem = utils::fetch_item_from_itemrecord($data, $minilesson);
        $olditem = false;
        // echo json_encode($data,JSON_PRETTY_PRINT);
        // die;

    }
    /*
        if($edit){
            $olditem=$item;
        }else{
            $olditem=false;
        }
    */
    // remove bad accents and things that mess up transcription (kind of like clear but permanent)
    // $data = \mod_minilesson\local\itemform\helper::deaccent_item($minilesson,$data);
    $theitem->deaccent();

    // xreate passage hash
    // $data->passagehash = \mod_minilesson\local\itemform\helper::update_create_langmodel($minilesson,$olditem,$data);
    $theitem->update_create_langmodel($olditem);

        // lets update the phonetics
        // $data->phonetic = \mod_minilesson\local\itemform\helper::update_create_phonetic($minilesson,$olditem,$data);
    $theitem->update_create_phonetic($olditem);

    $result = $theitem->update_insert_item();
    if($result->error == true){
        print_error($result->message);
        redirect($redirecturl);

    }else{
        $theitem = $result->item;
    }

    // go back to edit quiz page
    redirect($redirecturl);
}


// if  we got here, there was no cancel, and no form data, so we are showing the form
// if edit mode load up the item into a data object
if ($edit) {
    $data = $item;
    $data->itemid = $item->id;

    // If rich text, use editor otherwise use filepicker
    if($minilesson->richtextprompt == constants::M_PROMPT_RICHTEXT) {
        // init our editor
        $data = file_prepare_standard_editor($data, constants::TEXTQUESTION, $editoroptions, $context, constants::M_COMPONENT,
                constants::TEXTQUESTION_FILEAREA, $data->itemid);

    }else {

        // make sure the media upload fields are in the correct state
        $fs = get_file_storage();
        $files = $fs->get_area_files( $context->id,  constants::M_COMPONENT, constants::MEDIAQUESTION, $data->itemid);
        if($files){
            $data->addmedia = 1;
        }else{
            $data->addmedia = 0;
        }
        if(!empty($data->{constants::TTSQUESTION})){
            $data->addttsaudio = 1;
        }else{
            $data->addttsaudio = 0;
        }
        if(!empty($data->{constants::MEDIAIFRAME})){
            $data->addiframe = 1;
        }else{
            $data->addiframe = 0;
        }
        if(!empty($data->{constants::YTVIDEOID})){
            $data->addyoutubeclip = 1;
        }else{
            $data->addyoutubeclip = 0;
        }
        if(!empty($data->{constants::QUESTIONTEXTAREA})){
            $edoptions = constants::ITEMTEXTAREA_EDOPTIONS;
            $edoptions['context'] = $context;
            $data->{constants::QUESTIONTEXTAREA. 'format'} = FORMAT_HTML;
            $data = file_prepare_standard_editor($data, constants::QUESTIONTEXTAREA, $edoptions, $context, constants::M_COMPONENT,
                    constants::TEXTQUESTION_FILEAREA, $data->itemid);
            $data->addtextarea = 1;
        }else{
            $data->addtextarea = 0;
        }
        if(!empty($data->{constants::TTSDIALOG})){
            $data->addttsdialog = 1;
            // expand opts
            $data = utils::unpack_ttsdialogopts($data);
        }else{
            $data->addttsdialog = 0;
        }
        if(!empty($data->{constants::TTSPASSAGE})){
            $data->addttspassage = 1;
            // expand opts
            $data = utils::unpack_ttspassageopts($data);
        }else{
            $data->addttspassage = 0;
        }

        // init our itemmedia upload file field
        $draftitemid = file_get_submitted_draft_itemid(constants::MEDIAQUESTION);
        file_prepare_draft_area($draftitemid, $context->id, constants::M_COMPONENT,
                constants::MEDIAQUESTION, $data->itemid,
                $filemanageroptions);
        $data->{constants::MEDIAQUESTION} = $draftitemid;

        // show the fields by default if they have some content
        $visibility = ['addmedia' => $data->addmedia,
        'addiframe' => $data->addiframe,
        'addttsaudio' => $data->addttsaudio,
        'addtextarea' => $data->addtextarea,
        'addyoutubeclip' => $data->addyoutubeclip,
        'addttsdialog' => $data->addttsdialog,
        'addttspassage' => $data->addttspassage];

        // Init file upload areas for item answers
        for ($i = 1; $i <= constants::MAXANSWERS; $i++){
            $draftitemid = file_get_submitted_draft_itemid(constants::FILEANSWER . $i);
            file_prepare_draft_area($draftitemid, $context->id, constants::M_COMPONENT,
                    constants::FILEANSWER . $i, $data->itemid,
                    $filemanageroptions);
            $data->{constants::FILEANSWER . $i} = $draftitemid;
        }

        $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/mediaprompts', 'init', [$visibility]);

    }


} else {

    $data = new stdClass;
    $data->itemid = null;
    $data->visible = 1;
    $data->type = $type;

    // If rich text, use editor otherwise use filepicker
    if($minilesson->richtextprompt == constants::M_PROMPT_RICHTEXT) {
        // init our editor
        $data = file_prepare_standard_editor($data, constants::TEXTQUESTION, $editoroptions, $context, constants::M_COMPONENT,
                constants::TEXTQUESTION_FILEAREA, $data->itemid);

    }else {

        // init media prompts - all hidden initiall
        $visibility = ['addmedia' => 0,
            'addiframe' => 0,
            'addttsaudio' => 0,
            'addtextarea' => 0,
            'addyoutubeclip' => 0,
            'addttsdialog' => 0,
            'addttspassage' => 0];
    }
        $PAGE->requires->js_call_amd(constants::M_COMPONENT . '/mediaprompts', 'init', [$visibility]);
}

    // init our item, we move the id fields around a little
    $data->id = $cm->id;

    $mform->set_data($data);
    $PAGE->navbar->add(get_string('edit'), new moodle_url('/mod/minilesson/rsquestion/rsquestions.php', ['id' => $id]));
    $PAGE->navbar->add(get_string('editingitem', constants::M_COMPONENT, get_string($mform->type, constants::M_COMPONENT)));
    $renderer = $PAGE->get_renderer('mod_minilesson');
    $mode = 'rsquestions';
    echo $renderer->header($minilesson, $cm, $mode, null, get_string('edit', constants::M_COMPONENT));
if($edit){
       echo $renderer->heading(get_string('editingitem', constants::M_COMPONENT, get_string($mform->type, constants::M_COMPONENT)));
}else{
    echo $renderer->heading(get_string('addingitem', constants::M_COMPONENT, get_string($mform->type, constants::M_COMPONENT)));
}
    $mform->display();
    echo $renderer->footer();
