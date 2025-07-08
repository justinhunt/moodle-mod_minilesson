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
 * AIGEN settings page for Minilesson
 *
 *
 * @package    mod_minilesson
 * @copyright  2024 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');

use mod_minilesson\constants;
use mod_minilesson\utils;
use mod_minilesson\aigen;


/**
 * AIGEN actions
 */
const AIGEN_LIST = 0;
const AIGEN_SUBMIT = 1;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // minilesson instance ID
$action = optional_param('action', AIGEN_LIST, PARAM_INT);
$keyname = optional_param('keyname', '', PARAM_TEXT);

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

$PAGE->set_url(constants::M_URL . '/aigen.php', ['id' => $cm->id]);
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:canuseaigen', $modulecontext);

// Get an admin settings.
$config = get_config(constants::M_COMPONENT);

// Fetch templates.
$lessontemplates = aigen::fetch_lesson_templates();
$templatecount = count($lessontemplates);

// Testing data. // hard coded here and in aigen_dev.php
$contextdata = [
    'target_language' => $moduleinstance->ttslanguage,
    'user_topic' => 'Glass Bridges in China',
    'user_level' => 'CEFR A2',
    'user_text' => 'On my way to school I met a dog. We became friends. But he met a cat and ran after it. Was he my friend?',
    'user_keywords' => 'dog, cat, school, friend',
    'user_customdata1' => 'French',
    'user_customdata2' => '',
    'user_customdata3' => '',
];

switch($action){

    case AIGEN_SUBMIT:
        $lessontemplates = aigen::fetch_lesson_templates();
        if (!array_key_exists($keyname, $lessontemplates)) {
            throw new moodle_exception('Invalid template keyname', constants::M_COMPONENT);
        } else {
            $thetemplate = $lessontemplates[$keyname];
            if (!isset($thetemplate['config']) || !isset($thetemplate['template'])) {
                throw new moodle_exception('Invalid template structure', constants::M_COMPONENT);
            }
            $config = $thetemplate['config'];
            $template = $thetemplate['template'];
        }
        if ($postdata = data_submitted()) {
            foreach(mod_minilesson\aigen_form::mappings() as $fieldname) {
                if (isset($postdata->{$fieldname})) {
                    $contextdata[$fieldname] = $postdata->{$fieldname};
                }
            }
        }
        $aigen = new aigen($cm);
        $importdata = $aigen->make_import_data(
            $config,
            $template,
            $contextdata
        );

        // Do the import -- TO DO error checking.
        $theimport = new \mod_minilesson\import($moduleinstance, $modulecontext, $course, $cm);
        $theimport->set_reader($importdata, true);
        $theimport->import_process();


        $insertcount = count($template->items);
        redirect($PAGE->url, get_string('aigenpage_done', constants::M_COMPONENT, $insertcount), 10);
        break;

    case AIGEN_LIST:
    default:
        break;
}

// Set up the page header.
$pagetitle = get_string('aigenpage', constants::M_COMPONENT);
$PAGE->set_title(format_string($moduleinstance->name. ' ' . $pagetitle ));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('incourse');


// This puts all our display logic into the renderer.php files in this plugin.
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);
$mode = "aigen";
echo $renderer->header($moduleinstance, $cm, $mode, null, get_string('aigen', constants::M_COMPONENT));
echo $renderer->heading($pagetitle);

echo html_writer::div(get_string('aigenpage_explanation', constants::M_COMPONENT), constants::M_COMPONENT . '_aigenpageexplanation');



if ($templatecount > 0) {
    echo html_writer::div("For testing:<br> <pre>" . print_r($contextdata, true) . '</pre>',  ' mb-2');
    echo $renderer->aigen_buttons_menu($cm, $lessontemplates);
} else {
    echo html_writer::div(get_string('aigenpage_notemplates', constants::M_COMPONENT, $templatecount), constants::M_COMPONENT . '_clonecount' . ' mb-2');
}

echo $renderer->footer();
return;
