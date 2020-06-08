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
 * Reports for poodlltime
 *
 *
 * @package    mod_poodlltime
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

use \mod_poodlltime\constants;
use \mod_poodlltime\utils;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // poodlltime instance ID
$action = optional_param('action', constants::M_PUSH_NONE, PARAM_INT);



if ($id) {
    $cm         = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}

$PAGE->set_url(constants::M_URL . '/push.php',
	array('id' => $cm->id));
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/poodlltime:manage', $modulecontext);

//Get an admin settings 
$config = get_config(constants::M_COMPONENT);

switch($action){

    case constants::M_PUSH_PASSAGE:
        $DB->set_field(constants::M_TABLE,'passage',$moduleinstance->passage,array('passagekey'=>$moduleinstance->passagekey,'passagemaster'=>0));
        redirect($PAGE->url,get_string('pushpassage_done',constants::M_COMPONENT),10);
        break;

    case constants::M_PUSH_ALTERNATIVES:
        $DB->set_field(constants::M_TABLE,'alternatives',$moduleinstance->alternatives,array('passagekey'=>$moduleinstance->passagekey,'passagemaster'=>0));
        redirect($PAGE->url,get_string('pushalternatives_done',constants::M_COMPONENT),10);
        break;

    case constants::M_PUSH_QUESTIONS:
        $sql ="UPDATE {" . constants::M_QTABLE. "} qt INNER JOIN {" . constants::M_TABLE . "} rt ON rt.id=qt.poodlltime AND rt.passagemaster=0 AND rt.passagekey= :passagekey ";
        $sql .= " INNER JOIN {" . constants::M_QTABLE . "} qtoriginal ON qtoriginal.name = qt.name AND qtoriginal.poodlltime = :poodlltimeid ";
        $sql .= " SET qt.itemtext = qtoriginal.itemtext, ";
        $sql .= " qt.itemorder = qtoriginal.itemorder, ";
        $sql .= " qt.customtext1 = qtoriginal.customtext1, ";
        $sql .= " qt.customtext2 = qtoriginal.customtext2, ";
        $sql .= " qt.customtext3 = qtoriginal.customtext3, ";
        $sql .= " qt.customtext4 = qtoriginal.customtext4, ";
        $sql .= " qt.customtext5 = qtoriginal.customtext5, ";
        $sql .= " qt.correctanswer = qtoriginal.correctanswer";

        $DB->execute($sql,array('passagekey'=>$moduleinstance->passagekey,'poodlltimeid'=>$moduleinstance->id));
        redirect($PAGE->url,get_string('pushquestions_done',constants::M_COMPONENT),10);
        break;

    case constants::M_PUSH_LEVEL:
        $DB->set_field(constants::M_TABLE,'level',$moduleinstance->level,array('passagekey'=>$moduleinstance->passagekey,'passagemaster'=>0));
        redirect($PAGE->url,get_string('pushlevel_done',constants::M_COMPONENT),10);
        break;

    case constants::M_PUSH_NONE:
    default:

}

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('course');
$mode = "push";

//This puts all our display logic into the renderer.php files in this plugin
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);


echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('pushpage', constants::M_COMPONENT));
if($moduleinstance->passagemaster){
    echo $renderer->pushpassage_button($cm);
    echo $renderer->pushalternatives_button($cm);
    echo $renderer->pushquestions_button($cm);
}else{
    echo get_string('notpassagemaster', constants::M_COMPONENT);
}

echo $renderer->footer();
return;
