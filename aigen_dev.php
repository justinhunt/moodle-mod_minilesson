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
use mod_minilesson\table\templates;

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$n = optional_param('n', 0, PARAM_INT);  // minilesson instance ID
$action = optional_param('action', null, PARAM_ALPHA);
$templateid = optional_param('templateid', 0, PARAM_INT);

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
        ['id' => $cm->id, 'action' => $action]);
require_login($course, true, $cm);
$modulecontext = context_module::instance($cm->id);

require_capability('mod/minilesson:managetemplate', $modulecontext);

// Get an admin settings.
$config = get_config(constants::M_COMPONENT);

/// Set up the page header
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('incourse');

// This puts all our display logic into the renderer.php files in this plugin
$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

$widgethtml = null;
switch($action) {
    case 'delete': {
        if ($templateid && confirm_sesskey()) {
            $DB->delete_records('minilesson_templates', ['id' => $templateid]);
            redirect(
                new moodle_url('/mod/minilesson/aigen_dev.php', ['id' => $cm->id]),
                get_string('templatedeleted', constants::M_COMPONENT)
            );
        }
        break;
    }
    case 'duplicate': {
        if ($templateid && confirm_sesskey()) {
            // Duplicate the template
            $template = $DB->get_record('minilesson_templates', ['id' => $templateid], '*', MUST_EXIST);
            $template->id = null; // Reset the ID to create a new record.
            $template->timemodified = time(); // Update the modified time.
            $template->name .= ' (copy)'; // Append '(copy)' to the name
            $DB->insert_record('minilesson_templates', $template);
            redirect(
                new moodle_url('/mod/minilesson/aigen_dev.php', ['id' => $cm->id]),
                get_string('templateduplicated', constants::M_COMPONENT)
            );
        }
        break;
    }
    case 'edit': {
        $aigenform = new \mod_minilesson\aigen_form();
        $aigenform->set_data_for_dynamic_submission();

        // If this page is from a form submission, process it.
        if ($aigenform->is_cancelled()) {
            // If the form is cancelled, redirect to the module page.
            redirect(new moodle_url('/mod/minilesson/aigen_dev.php', ['id' => $cm->id]));
        } else if ($template = $aigenform->process_dynamic_submission()) {
            redirect(new moodle_url('/mod/minilesson/aigen_dev.php', ['id' => $cm->id]));
        }

        $widgethtml = $aigenform->render();
        break;
    }
    default: {
        $tablefilterset = templates::get_filterset_object()
            ->upsert_filter('cmid', (int) $cm->id);
        $table = new templates();
        $table->set_filterset($tablefilterset);

        $addtemplatebtn = new single_button(
            new moodle_url('/mod/minilesson/aigen_dev.php', ['id' => $cm->id, 'action' => 'edit']),
            get_string('action:addtemplate', constants::M_COMPONENT)
        );
        $widgethtml = $renderer->container($renderer->render($addtemplatebtn), 'mb-3 text-right');
        $widgethtml .= $table->render();
        break;
    }
}

// From here we actually display the page.
echo $renderer->header($moduleinstance, $cm, 'aigen', null, get_string('aigen', constants::M_COMPONENT));

// Render main html
echo $widgethtml;

// Finish the page.
echo $renderer->footer();
return;
