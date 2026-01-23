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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

use mod_minilesson\constants;
use mod_minilesson\utils;
use mod_minilesson\comprehensiontest;


/**
 * Push mode for no push.
 */
const PUSH_NONE = 0;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // minilesson instance ID
$scope  = optional_param('scope', constants::PUSHMODE_COURSE, PARAM_INT);  // push scope
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
    throw new moodle_exception('You must specify a course_module ID or an instance ID', constants::M_COMPONENT);
}

$PAGE->set_url(constants::M_URL . '/push.php', ['id' => $cm->id, 'scope' => $scope]);
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:push', $modulecontext);

// Get an admin settings.
$config = get_config(constants::M_COMPONENT);

if (!$config->enablepushtab) {
    throw new moodle_exception('The Push Tab is not enabled. This must be enabled in the minilesson admin settings.', constants::M_COMPONENT);
}

// Fetch the likely number of affected records.
$cloneconditions = [];
switch ($scope) {
    case CONSTANTS::PUSHMODE_MODULENAME:
        $cloneconditions['name'] = $moduleinstance->name;
        break;
    case CONSTANTS::PUSHMODE_COURSE:
        $cloneconditions['course'] = $moduleinstance->course;
        break;
    case CONSTANTS::PUSHMODE_SITE:
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
$clones = $DB->get_records_select(constants::M_TABLE, $whereclause);
$clonecount = count($clones);
//$clonecount = $DB->count_records_select(constants::M_TABLE, $whereclause);

switch ($action) {
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

    case constants::M_PUSH_ITEMS:
        $updatefields = ['items'];
        break;

    case constants::M_PUSH_NONE:
    default:
        $updatefields = [];
}

// PUSH_ITEMS is a special case.
if ($action == constants::M_PUSH_ITEMS) {
    $updatecount = 0;
    $activityids = $DB->get_fieldset_select(constants::M_TABLE, 'id', $whereclause);
    if (empty($activityids)) {
        redirect($PAGE->url, get_string('pushpage_noactivities', constants::M_COMPONENT, $updatecount), 10);
    }

    // Reset the source order of items in the current activity.
    utils::reset_item_order($moduleinstance->id);

    $comptest = new comprehensiontest($cm);
    $thisitems = $comptest->fetch_items();

    // We need to update the items in each clone.
    if (count($thisitems) == 0) {
        redirect($PAGE->url, get_string('pushpage_noitems', constants::M_COMPONENT, $updatecount), 10);
    }

    // Loop through the activities fetching the items.
    // If each items type, item order, item name match - and if all the items match, do the update.
    // We really want to avoid somebody doing a nuclear hit by mistake.
    $minilessons = [];
    $contexts = [];
    foreach ($activityids as $activityid) {
        // Get the module instance for the clone activity.
        $cloneinstance = $DB->get_record(constants::M_TABLE, ['id' => $activityid]);
        if (!$cloneinstance) {
            continue;
        }

        // Reset the item order. Because it can get out of sync if items are added/deleted in the source activity.
        // That is, the sequence of itemorders may be correct but there may be gaps in itemorder numbers.
        utils::reset_item_order($activityid);

        // Get the module context for the clone activity.
        $clonecm = get_coursemodule_from_instance('minilesson', $activityid, $cloneinstance->course, false, IGNORE_MISSING);
        $clonecontext = \context_module::instance($clonecm->id);
        // Do a preliminary fetch to see if we have items that match.
        $cloneitemids = [];
        foreach ($thisitems as $sourceitem) {
            try {
                $cloneitemid = $DB->get_field(
                    constants::M_QTABLE,
                    'id',
                    ['minilesson' => $activityid, 'itemorder' => $sourceitem->itemorder, 'type' => $sourceitem->type, 'name' => $sourceitem->name],
                    MUST_EXIST
                );
            } catch (\dml_exception $e) {
                // If the item does not exist in the clone, we will skip it.
                continue;
            }
            if ($cloneitemid) {
                $cloneitemids[$sourceitem->itemorder] = $cloneitemid;
            }
        }
        // If we have clone items that match the source items, we can proceed.
        if (count($cloneitemids) == count($thisitems)) {
            foreach ($thisitems as $sourceitem) {
                $cloneitemid = $cloneitemids[$sourceitem->itemorder];
                // Update the cloneitem with all the original item data except the id, timemodified and timecreated.
                $cloneobject = clone($sourceitem);
                unset($cloneobject->timecreated);
                $cloneobject->minilesson = $activityid;
                $cloneobject->timemodified = time();
                $cloneobject->id = $cloneitemid;
                $DB->update_record(constants::M_QTABLE, $cloneobject);

                // Now update the cloneitem with the images from the source item.
                $cloneitem = utils::fetch_item_from_itemrecord($cloneobject, $cloneinstance);
                // Get all the file areas
                $fileareas = [constants::TEXTQUESTION_FILEAREA, constants::MEDIAQUESTION, constants::AUDIOSTORY];
                for ($anumber = 1; $anumber <= constants::MAXANSWERS; $anumber++) {
                    $fileareas[] = constants::TEXTANSWER_FILEAREA . $anumber;
                    $fileareas[] = constants::FILEANSWER . $anumber;
                    $fileareas[] = constants::FILEANSWER . $anumber . '_image';
                    $fileareas[] = constants::FILEANSWER . $anumber . '_audio';
                }

                $fs = get_file_storage();
                foreach ($fileareas as $filearea) {
                    //Get the source files
                    $files = $fs->get_area_files($modulecontext->id, constants::M_COMPONENT, $filearea, $sourceitem->id);
                    //Delete the old files
                    $fs->delete_area_files($clonecontext->id, constants::M_COMPONENT, $filearea, $cloneobject->id);

                    // Add each source file to the clone context.
                    foreach ($files as $file) {
                        $filename = $file->get_filename();
                        if ($filename == '.') {
                            continue;
                        }
                        // Create a copy in the new context
                        $filerecord = new \stdClass();
                        $filerecord->contextid = $clonecontext->id;
                        $filerecord->itemid = $cloneobject->id;
                        $fs->create_file_from_storedfile($filerecord, $file);
                    }
                }
            } //end of items loop
            $updatecount++;
        } //end of if clone items count matches
    } //end of activities loop
    redirect($PAGE->url, get_string('pushpage_done', constants::M_COMPONENT, $updatecount), delay: 10);
} else {
    // Do the DB updates and then refresh.
    if ($updatefields && count($updatefields) > 0) {
        foreach ($updatefields as $thefield) {
            $DB->set_field_select(constants::M_TABLE, $thefield, $moduleinstance->{$thefield}, $whereclause);
        }
        redirect($PAGE->url, get_string('pushpage_done', constants::M_COMPONENT, $clonecount), 10);
    }
}

// Set up the page header.
$pagetitle = get_string('pushpage', constants::M_COMPONENT);
$PAGE->set_title(format_string($moduleinstance->name . ' ' . $pagetitle));
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
    CONSTANTS::PUSHMODE_MODULENAME => get_string('pushpage_scopemodule', constants::M_COMPONENT, $moduleinstance->name),
    CONSTANTS::PUSHMODE_COURSE => get_string('pushpage_scopecourse', constants::M_COMPONENT, $course->fullname),
    CONSTANTS::PUSHMODE_SITE => get_string('pushpage_scopesite', constants::M_COMPONENT),
    CONSTANTS::PUSHMODE_NONE => get_string('pushpage_scopenone', constants::M_COMPONENT),
];
$theurl = new \moodle_url(constants::M_URL . '/push.php', ['id' => $cm->id]);
$scopeselector = new \single_select($theurl, 'scope', $scopeopts, $scope);
$scopeselector->set_label(get_string('scopeselector', constants::M_COMPONENT));
echo $renderer->render($scopeselector);

if ($clonecount > 0) {
    echo html_writer::div(get_string('pushpage_clonecount', constants::M_COMPONENT, $clonecount), constants::M_COMPONENT . '_clonecount' . ' mb-2');
    echo $renderer->push_buttons_menu($cm, $clonecount, $scope);
} else {
    echo html_writer::div(get_string('pushpage_noclones', constants::M_COMPONENT, $clonecount), constants::M_COMPONENT . '_clonecount' . ' mb-2');
}

echo $renderer->footer();
return;
