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
 * @package mod_poodlltime
 * @copyright  2014 Justin Hunt  {@link http://poodll.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/poodlltime/lib.php');

use \mod_poodlltime\constants;


$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('poodlltime', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
//$poodlltime = new poodlltime($DB->get_record('poodlltime', array('id' => $cm->instance), '*', MUST_EXIST));
$poodlltime = $DB->get_record('poodlltime', array('id' => $cm->instance), '*', MUST_EXIST);

//mode is necessary for tabs
$mode='rsquestions';
//Set page url before require login, so post login will return here
$PAGE->set_url('/mod/poodlltime/rsquestion/rsquestions.php', array('id'=>$cm->id,'mode'=>$mode));

//require login for this page
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

$mis = $DB->get_records('poodlltime');
$updates = 0;
foreach($mis as $moduleinstance){
    $items = $DB->get_records(constants:: M_QTABLE,array('poodlltime'=>$moduleinstance->id));
    foreach($items as $olditem) {
        $newitem = new \stdClass();
        $newitem->customtext1 = $olditem->customtext1;
        $newitem->type = $olditem->type;
        $passagehash = \mod_poodlltime\rsquestion\helper::update_create_langmodel($moduleinstance, $olditem, $newitem);
        if(!empty($passagehash)){
            $DB->update_record(constants::M_QTABLE,array('id'=>$olditem->id,'passagehash'=>$passagehash));
            $updates++;
            sleep(7);
        }
    }
}

$renderer = $PAGE->get_renderer('mod_poodlltime');


$PAGE->navbar->add(get_string('rsquestions', 'poodlltime'));
echo $renderer->header($poodlltime, $cm, $mode, null, get_string('rsquestions', 'poodlltime'));

echo '<h2> Updates' . $updates . '</h2>';

echo $renderer->footer();
