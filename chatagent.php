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
 * The chat agent page: build a lesson by talking to an assistant.
 *
 * The conversation sits on the left and the lesson it is changing on the right, so the thing a
 * teacher wants to check is beside the conversation that produced it rather than behind it.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/filelib.php');

use mod_minilesson\chatagent_attachform;
use mod_minilesson\constants;
use mod_minilesson\local\chatagent\attachments;
use mod_minilesson\local\chatagent\conversation_store;
use mod_minilesson\utils;

$id = required_param('id', PARAM_INT); // Course module id.

$cm = get_coursemodule_from_id(constants::M_MODNAME, $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);
require_capability('mod/minilesson:canuseaigen', $modulecontext);

if (!utils::chatagent_available()) {
    throw new moodle_exception('chatagent_error_unavailable', constants::M_COMPONENT);
}

$PAGE->set_url(constants::M_URL . '/chatagent.php', ['id' => $cm->id]);
$PAGE->set_context($modulecontext);
$PAGE->set_title(format_string($moduleinstance->name . ': ' . get_string('chatagent', constants::M_COMPONENT)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);
$items = $DB->get_records(constants::M_QTABLE, ['minilesson' => $moduleinstance->id], 'itemorder');

// Open the conversation here rather than waiting for the panel's first web service call, so the
// file manager can be prepared against the files this conversation already holds. chatagent_start
// then hands the panel the same conversation.
$conversation = conversation_store::get_or_create($USER->id, $cm->id, $course->id);

$draftitemid = 0;
file_prepare_draft_area(
    $draftitemid,
    $modulecontext->id,
    constants::M_COMPONENT,
    attachments::FILEAREA,
    $conversation->id,
    chatagent_attachform::filemanager_options()
);
$attachform = new chatagent_attachform(null, ['id' => $cm->id]);
$attachform->set_data(['chatagent_filemanager' => $draftitemid]);

// A queued generation job that never runs is the likeliest way for this to look broken, so say
// so up front rather than leaving the teacher watching a progress bar that cannot move.
$lastcron = (int) get_config('tool_task', 'lastcronstart');
$cronwarning = $lastcron === 0 || (time() - $lastcron) > (DAYSECS / 2);

$PAGE->requires->js_call_amd('mod_minilesson/chatagent', 'init', [$cm->id, $modulecontext->id]);

echo $renderer->header($moduleinstance, $cm, 'chatagent', null, get_string('chatagent', constants::M_COMPONENT));
echo $renderer->render_from_template('mod_minilesson/chatagent', [
    'cmid' => $cm->id,
    'contextid' => $modulecontext->id,
    'lessonname' => format_string($moduleinstance->name),
    'itemlist' => $renderer->chatagent_item_list($items, $cm),
    'attachform' => $attachform->render(),
    'cronwarning' => $cronwarning,
]);
echo $renderer->footer();
