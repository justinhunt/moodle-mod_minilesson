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
 * Provides the interface for overall managing of items
 *
 * @package mod_minilesson
 * @copyright  2014 Justin Hunt  {@link http://poodll.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/minilesson/lib.php');

use \mod_minilesson\constants;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHA);

$cm = get_coursemodule_from_id('minilesson', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
//$minilesson = new minilesson($DB->get_record('minilesson', array('id' => $cm->instance), '*', MUST_EXIST));
$minilesson = $DB->get_record('minilesson', array('id' => $cm->instance), '*', MUST_EXIST);

$comprehensiontest = new \mod_minilesson\comprehensiontest($cm);
$items = $comprehensiontest->fetch_items();

//mode is necessary for tabs
$mode='rsquestions';
//Set page url before require login, so post login will return here
$PAGE->set_url('/mod/minilesson/rsquestion/rsquestions.php', array('id'=>$cm->id,'mode'=>$mode));


//require login for this page
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

//Get an admin settings
$config = get_config(constants::M_COMPONENT);

if($minilesson->foriframe==1  || $minilesson->pagelayout=='embedded') {
    $PAGE->set_pagelayout('embedded');
}elseif($config->enablesetuptab  || $minilesson->pagelayout=='popup'){
    $PAGE->set_pagelayout('popup');
}else{
    $PAGE->set_pagelayout('course');
}


//Not GPL3 compat. so cant be distributed with plugin. Hence we load it from CDN
if($config->animations==constants::M_ANIM_FANCY) {
    $PAGE->requires->css(new moodle_url('https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css'));
}

$renderer = $PAGE->get_renderer('mod_minilesson');
$rsquestion_renderer = $PAGE->get_renderer('mod_minilesson','rsquestion');

if ($action === 'bulkdelete') {
    confirm_sesskey();
    $questionids = optional_param_array('deletequestionid', [], PARAM_INT);
    foreach ($questionids as $questionid) {
        \mod_minilesson\local\itemtype\item::delete_item($questionid, $context);
    }
    if (!empty($questionids)) {
        \mod_minilesson\utils::reset_item_order($minilesson->id);
        redirect($PAGE->url);
    }
}

//if we have items, Data tables will make them pretty
//Prepare datatable(before header printed)
$tableid =  constants::M_ITEMS_TABLE;
$rsquestion_renderer->setup_datatables($tableid);

$PAGE->navbar->add(get_string('rsquestions', 'minilesson'));
echo $renderer->header($minilesson, $cm, $mode, null, get_string('rsquestions', 'minilesson'));


    // We need view permission to be here
    require_capability('mod/minilesson:itemview', $context);
    
    //if have edit permission, show edit buttons
    if(has_capability('mod/minilesson:itemview', $context)){
    	echo $rsquestion_renderer ->add_edit_page_links($context,$tableid);
    }

//if we have items, show em
$itemsvisible = $items && count($items);
echo $rsquestion_renderer->show_items_list($items,$minilesson,$cm, $itemsvisible);
echo $rsquestion_renderer->show_noitems_message($items,$minilesson,$cm, $itemsvisible);

echo $renderer->footer();
