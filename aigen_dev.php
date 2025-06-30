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
 * AIGEN mod_minilesson
 *
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

use mod_minilesson\constants;
use mod_minilesson\utils;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n = optional_param('n', 0, PARAM_INT);  // minilesson instance ID



if ($id) {
    $cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);
} else if ($n) {
    $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $n], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id, $course->id, false, MUST_EXIST);
} else {
    print_error(0, 'You must specify a course_module ID or an instance ID');
}

$PAGE->set_url(constants::M_URL . '/aigen_dev.php',
        ['id' => $cm->id]);
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:manage', $modulecontext);

// Get an admin settings.
$config = get_config(constants::M_COMPONENT);

// Get token.
$token = utils::fetch_token($config->apiuser, $config->apisecret);

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('incourse');



// This puts all our display logic into the renderer.php files in this plugin
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

// Prepare the page template data.
$tdata = [];
$tdata['cloudpoodllurl'] = utils::get_cloud_poodll_server();
$tdata['pageurl'] = $PAGE->url->out(false);
$tdata['language'] =  $moduleinstance->ttslanguage;
$tdata['token'] = $token;
$tdata['region'] = $moduleinstance->region;
$tdata['cmid'] = $cm->id;



// From here we actually display the page.
echo $renderer->header($moduleinstance, $cm, 'aigen', null, get_string('aigen', constants::M_COMPONENT));

$aigenform = new \mod_minilesson\aigen_form(null, [
    'id' => $cm->id,
    'moduleinstance' => $moduleinstance,
    'cm' => $cm,
    'modulecontext' => $modulecontext,
    'token' => $token,
]);
// Add the form to the template data.
$tdata['aigenform'] = $aigenform->render();

// If this page is from a form submission, process it.
if ($aigenform->is_cancelled()) {
    // If the form is cancelled, redirect to the module page.
    redirect(new moodle_url('/mod/minilesson/aigen.php', ['id' => $cm->id]));
} else if ($data = $aigenform->get_data()) {
    // If the form is submitted, process the data.
    $importjson = $data->importjson;
    $theactivity = json_decode($importjson, true);
    // These are the items in the imported activity (that is the template lesson)
    $tdata['items'] = $theactivity['items'];

    // This is the set of data that can be used in the AI generation prompt.
    // Since each item is generated in sequence, we can use the previous item data in the next item.
    // that is why the context grows, as we loop through the items.
    // Here we are building the AI Lesson Generation (AIGEN) config. 
    // So it is the location or name of the data. not the data itself.
    $availablecontext = [];
    $availablecontext[] = 'user_topic'; // Sample data that the user might provide. eg "Your plan for the weekend"
    $availablecontext[] = 'user_level'; // Sample data that the user might provide. eg "A1" or "Intermediate"
    $availablecontext[] = 'user_text'; // Sample data that the user might provide. eg " One fine day I decided .."
    $availablecontext[] = 'system_language'; // Data from the activity settings, language is required

    // We will also need to fetch the file areas for each item.
    $contextfileareas = [];


    // Now we loop through the items in the activity and fetch the AI generation prompt for each item.
    // We also fetch the placeholders for each item, and update the available context fields.
    // We also parse the prompt to get the prompt fields that we will match with availablecontext to make the full AI generation prompt.
    foreach ($tdata['items'] as $itemnumber => $item) {
        $itemtype = $item['type'];
        $itemclass = '\\mod_minilesson\\local\\itemtype\\item_' . $itemtype;
        if (class_exists($itemclass)) {
            $tdata['items'][$itemnumber]['itemnumber'] = $itemnumber;
            // Fetch the prompt
            $generatemethods = ['generate', 'extract'];
            foreach ($generatemethods as $method) {
                $theprompt = $itemclass::aigen_fetch_prompt($theactivity, $method);
                $tdata['items'][$itemnumber]['aigenprompt' . $method] = $theprompt;
                // Parse the prompt to get the fields that we will use in the AI generation
                // Extract fields which are words in curly brackets from the prompt.
                $tdata['items'][$itemnumber]['promptfields' . $method] = utils::extract_curly_fields($theprompt);
            }
            // By default we will set prompt fields and generate methds to 'generate'.
            $tdata['items'][$itemnumber]['aigenprompt'] = $tdata['items'][$itemnumber]['aigenpromptgenerate'];
            $tdata['items'][$itemnumber]['aigenpromptfields'] = $tdata['items'][$itemnumber]['promptfieldsgenerate'];

            // Fetch the placeholders for this item.
            $thisplaceholders = $itemclass::aigen_fetch_placeholders($item);
            $tdata['items'][$itemnumber]['aigenplaceholders'] = $thisplaceholders;
            $tdata['items'][$itemnumber]['availablecontext'] = $availablecontext;

            // Fetch the file areas for this item.
            $thefiles = $theactivity['files'];
            $thisfileareas = $itemclass::aigen_fetch_fileareas($item, $thefiles, $contextfileareas);
            $tdata['items'][$itemnumber]['aigenfileareas'] = $thisfileareas;
            $tdata['items'][$itemnumber]['contextfileareas'] = $contextfileareas;

            // Update available context.
            $thiscontext = array_map(function($placeholder) use ($itemnumber) {
                return 'item' . $itemnumber . '_' . $placeholder;
            }, $thisplaceholders);
            $availablecontext = array_merge($availablecontext, $thiscontext);

            // Update available file areas.
             $itemfileareas = array_map(function($filearea) use ($itemnumber) {
                return 'item' . $itemnumber . '_' . $filearea;
            }, $thisfileareas);
            $contextfileareas = array_merge($contextfileareas, $itemfileareas);

        } else {
            debugging('Item type ' . $itemtype . ' does not exist', DEBUG_DEVELOPER);
        }
    }
}



// Merge data with template and output to page.
echo $renderer->render_from_template(constants::M_COMPONENT . '/aigen', $tdata);


// Finish the page.
echo $renderer->footer();
return;
