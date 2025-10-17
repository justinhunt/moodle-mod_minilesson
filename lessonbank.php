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
 * TODO describe file lessonbank
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;
use mod_minilesson\constants;
use mod_minilesson\lessonbank_form;

require('../../config.php');
require_once($CFG->libdir . '/external/externallib.php');

$id = required_param('id', PARAM_INT);
$restore = optional_param('restore', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $moduleinstance = $DB->get_record(constants::M_TABLE, array('id' => $n), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $moduleinstance->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}


require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:manage', $modulecontext);

// Get admin settings.
$config = get_config(constants::M_COMPONENT);

if (!$config->setlessonbank) {
    redirect(new moodle_url('/mod/minilesson/view.php', ['id' => $id]));
}

$url = new moodle_url('/mod/minilesson/lessonbank.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($modulecontext);
$PAGE->set_title(get_string('lessonbank', constants::M_COMPONENT));
$PAGE->set_heading(get_string('lessonbank', constants::M_COMPONENT));

if ($moduleinstance->foriframe == 1 || $moduleinstance->pagelayout == 'embedded') {
    $PAGE->set_pagelayout('embedded');
} else if ($config->enablesetuptab || $moduleinstance->pagelayout == 'popup') {
    $PAGE->set_pagelayout('popup');
} else {
    $PAGE->set_pagelayout('incourse');
}

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

if ($restore && confirm_sesskey()) {
    $function = 'mod_minilesson_lessonbank';
    if ($externalfunctioninfo = core_external::external_function_info($function)) {
        $params = [
            'function' => 'local_lessonbank_fetch_minilesson',
            'args' => "id={$restore}",
        ];
        $params = core_external::validate_parameters(
            $externalfunctioninfo->parameters_desc,
            $params
        );

        $result = core_external::call_external_function($function, $params);
    } else {
        redirect($url, get_string('error:functionnotfound', constants::M_COMPONENT), null, 'warning');
    }

    if (empty($result['error'])) {
        $jsondata = json_decode($result['data']);
        $importdata = json_decode($jsondata->json);
        $theimport = new \mod_minilesson\import($moduleinstance, $modulecontext, $course, $cm);
        $errormessage = '';
        if (empty($importdata->items)) {
            $errormessage = get_string('error:noitemsinjson', constants::M_COMPONENT);
        } else {
            $theimport->set_reader($importdata, true);
        }
        if (empty($errormessage)) {
            $theimport->import_process();
            redirect($url, get_string('lessonitemcreate', constants::M_COMPONENT), null, 'success');
        }
        redirect($url, $errormessage, null, 'warning');
    } else {
        redirect($url, $result['error'], null, 'warning');
    }
}


$searchform = new lessonbank_form($url, [], 'post', '', ['id' => 'lessonbank_filters']);

$PAGE->requires->js_call_amd('mod_minilesson/searchlesson', 'registerFilter');

echo $renderer->header($moduleinstance, $cm, 'lessonbank', null, get_string('lessonbank', constants::M_COMPONENT));

if ($config->lessonbankurl) {

    echo html_writer::tag('p', get_string('lessonbank:desc', 'minilesson'));

    $searchform->display();

    echo html_writer::div(
        '',
        'position-relative',
        ['data-region' => 'cards-container']
    );

} else {

    echo $OUTPUT->notification(get_string('notconfigured', constants::M_COMPONENT), 'warning');

}

echo $renderer->footer();