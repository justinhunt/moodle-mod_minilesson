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
 * Push settings page for Minilesson
 *
 *
 * @package    mod_minilesson
 * @copyright  2024 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

use mod_minilesson\constants;
use mod_minilesson\utils;

const PUSHMODE_NONE = 0;
const PUSHMODE_MODULENAME = 1;
const PUSHMODE_COURSE = 2;
const PUSHMODE_SITE = 3;
const PUSH_NONE = 0;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // minilesson instance ID
$scope  = optional_param('scope', PUSHMODE_COURSE, PARAM_INT);  // push scope
$action = optional_param('action', constants::M_PUSH_NONE, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $moduleinstance  = $DB->get_record(constants::M_TABLE, array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('You must specify a course_module ID or an instance ID', constants::M_COMPONENT);
}

$PAGE->set_url(constants::M_URL . '/push.php', ['id' => $cm->id,'scope'=>$scope]);
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:push', $modulecontext);

// Get an admin settings.
$config = get_config(constants::M_COMPONENT);

if(!$config->enablepushtab){
    throw new moodle_exception('The Push Tab is not enabled. This must be enabled in the minilesson admin settings.', constants::M_COMPONENT);
}

// Fetch the likely number of affected records.
$cloneconditions = [];
switch($scope){
    case PUSHMODE_MODULENAME:
        $cloneconditions['name'] = $moduleinstance->name;
        break;
    case PUSHMODE_COURSE:
        $cloneconditions['course'] = $moduleinstance->course;
        break;
    case PUSHMODE_SITE:
        // There are no conditions for a site wide push.
        break;
    default:
        // We should never get here, nor should we push anything if we do.
        $cloneconditions['id'] = 0;
        break;
}

$whereclause = ' NOT id = ' . $moduleinstance->id;
foreach ($cloneconditions as $key => $value) {
    $whereclause .= " AND $key = '$value'";
}
$clonecount = $DB->count_records_select(constants::M_TABLE, $whereclause);

switch($action){
    case constants::M_PUSH_TRANSCRIBER:
        $updatefields = ['transcriber'];
        break;

    case constants::M_PUSH_SHOWITEMREVIEW:
        $updatefields = ['showitemreview'];
        break;

    case constants::M_PUSH_MAXATTEMPTS:
        $updatefields = ['maxattempts'];
        break;

    case constants::M_PUSH_REGION:
        $updatefields = ['region'];
        break;

    case constants::M_PUSH_CONTAINERWIDTH:
        $updatefields = ['containerwidth'];
        break;

    case constants::M_PUSH_CSSKEY:
        $updatefields = ['csskey'];
        break;

    case constants::M_PUSH_FINISHSCREEN:
        $updatefields = ['finishscreen'];
        break;

    case constants::M_PUSH_FINISHSCREENCUSTOM:
        $updatefields = ['finishscreencustom'];
        break;

    case constants::M_PUSH_LESSONFONT:
        $updatefields = ['lessonfont'];
        break;

    case constants::M_PUSH_NONE:
    default:
        $updatefields = [];
}

// Do the DB updates and then refresh.
if ($updatefields && count($updatefields) > 0) {
    foreach ($updatefields as $thefield) {
        $DB->set_field_select(constants::M_TABLE, $thefield, $moduleinstance->{$thefield}, $whereclause);
    }
    redirect($PAGE->url, get_string('pushpage_done', constants::M_COMPONENT, $clonecount), 10);
}

// Set up the page header.
$pagetitle = get_string('pushpage', constants::M_COMPONENT);
$PAGE->set_title(format_string($moduleinstance->name. ' ' . $pagetitle ));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('incourse');


// This puts all our display logic into the renderer.php files in this plugin.
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);
$mode = "push";
echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('push', constants::M_COMPONENT));
echo $renderer->heading($pagetitle);

echo html_writer::div(get_string('pushpage_explanation', constants::M_COMPONENT), constants::M_COMPONENT . '_pushpageexplanation');

//scope selector
$scopeopts = [
    PUSHMODE_MODULENAME => get_string('pushpage_scopemodule', constants::M_COMPONENT, $moduleinstance->name),
    PUSHMODE_COURSE => get_string('pushpage_scopecourse', constants::M_COMPONENT, $course->fullname),
    PUSHMODE_SITE => get_string('pushpage_scopesite', constants::M_COMPONENT),
    PUSHMODE_NONE => get_string('pushpage_scopenone', constants::M_COMPONENT),
];
$scopeselector = new \single_select($PAGE->url, 'scope', $scopeopts, $scope);
$scopeselector->set_label(get_string('scopeselector', constants::M_COMPONENT));
echo $renderer->render($scopeselector);

if ($clonecount > 0) {
    echo html_writer::div(get_string('pushpage_clonecount', constants::M_COMPONENT, $clonecount), constants::M_COMPONENT . '_clonecount' . ' mb-2');
    echo $renderer->push_buttons_menu($cm, $clonecount);
} else {
    echo html_writer::div(get_string('pushpage_noclones', constants::M_COMPONENT, $clonecount), constants::M_COMPONENT . '_clonecount' . ' mb-2');
}

echo $renderer->footer();
return;
