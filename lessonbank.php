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
 * The Lesson Bank.
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_minilesson\constants;
use mod_minilesson\lessonbank_form;
use mod_minilesson\utils;

require('../../config.php');
require_once($CFG->libdir . '/external/externallib.php');

$id = required_param('id', PARAM_INT);
$restore = optional_param('restore', 0, PARAM_INT);
$translateimportid = optional_param('translateimportid', 0, PARAM_INT);

$cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
if (!$cm) {
    throw new \moodle_exception('invalidcoursemodule');
}
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);


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
} elseif ($config->enablesetuptab || $moduleinstance->pagelayout == 'popup') {
    $PAGE->set_pagelayout('popup');
} else {
    $PAGE->set_pagelayout('incourse');
}

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

if (!empty($translateimportid) || ($restore && confirm_sesskey())) {
    $function = 'mod_minilesson_lessonbank';
    if ($externalfunctioninfo = core_external::external_function_info($function)) {
        $params = [
            'function' => 'local_lessonbank_fetch_minilesson',
            'args' => !empty($translateimportid) ? "id={$translateimportid}" : "id={$restore}",
        ];

        $result = mod_minilesson_external::lessonbank($params['function'], $params['args']);
    } else {
        redirect($url, get_string('error:functionnotfound', constants::M_COMPONENT), null, 'warning');
    }

    if (empty($result->error)) {
        $jsondata = json_decode($result->data);
        $importdata = json_decode($jsondata->json);
        $theimport = new \mod_minilesson\import($moduleinstance, $modulecontext, $course, $cm);
        $errormessage = '';
        if (empty($importdata->items)) {
            $errormessage = get_string('error:noitemsinjson', constants::M_COMPONENT);
        } else {
            if (!empty($translateimportid)) {
                $importfromlang = required_param('sourcelanguage', PARAM_TEXT);
                $importtolang = required_param('targetlanguage', PARAM_TEXT);
                $itemsjson = json_encode($importdata->items);
                $translateditems = $theimport->call_translate($itemsjson, $importfromlang, $importtolang);
                if (is_array($translateditems)) {
                    $importdata->items = $translateditems;
                } elseif ($translateditems && utils::is_json($translateditems)) {
                    $importdata->items = json_decode($translateditems);
                }
            }
            $theimport->set_reader($importdata, true);
        }
        if (empty($errormessage)) {
            $theimport->import_process();
            redirect($url, get_string('lessonitemcreate', constants::M_COMPONENT), null, 'success');
        }
        redirect($url, $errormessage, null, 'warning');
    } else {
        redirect($url, $result->error, null, 'warning');
    }
}


$searchform = new lessonbank_form($url, [], 'post', '', ['id' => 'lessonbank_filters']);
$searchform->set_data(['searchgroup[language]' => $moduleinstance->ttslanguage]);

$PAGE->requires->js_call_amd('mod_minilesson/searchlesson', 'registerFilter');
$lessonbankcontrolsdata = [
    'lessonbankitemcount' => get_string('foundlessons', constants::M_COMPONENT, 0),
    'paginationoptions' => [10, 25, 50, 100]
];

echo $renderer->header($moduleinstance, $cm, 'lessonbank', null, get_string('lessonbank', constants::M_COMPONENT));

if ($config->lessonbankurl) {
    echo html_writer::tag('p', get_string('lessonbank:desc', 'minilesson'));

    echo $searchform->render();

    echo $OUTPUT->render_from_template('mod_minilesson/lessonbankcontrols', $lessonbankcontrolsdata);

    echo html_writer::div(
        '',
        'position-relative',
        ['data-region' => 'cards-container']
    );
} else {
    echo $OUTPUT->notification(get_string('notconfigured', constants::M_COMPONENT), 'warning');
}

echo $renderer->footer();
