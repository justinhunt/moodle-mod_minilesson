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

namespace mod_minilesson\task;

use context_module;
use core\task\adhoc_task;
use mod_minilesson\aigen;
use mod_minilesson\aigen_tool;
use mod_minilesson\constants;
use mod_minilesson\import;
use mod_minilesson\local\exception\textgenerationfailed;
use mod_minilesson\local\progress\db_updater;

/**
 * Class process_aigen
 *
 * @package    mod_minilesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_aigen extends adhoc_task {
    public function get_name() {
        return get_string('processaigentask', constants::M_COMPONENT);
    }

    public function execute() {
        global $DB;
        $customdata = $this->get_custom_data();
        if (!empty($customdata->usageid)) {
            $usage = $DB->get_record(constants::M_TEMPL_USAGES_TABLE, ['id' => $customdata->usageid]);
            $lessontemplates = aigen::fetch_lesson_templates();
            if (!array_key_exists($usage->templateid, $lessontemplates)) {
                return;
            }
            $thetemplate = $lessontemplates[$usage->templateid];
            if (!isset($thetemplate['config']) || !isset($thetemplate['template'])) {
                return;
            }
            $config = $thetemplate['config'];
            $template = $thetemplate['template'];

            $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $usage->minilessonid]);
            if (empty($moduleinstance)) {
                return;
            }

            $cm = get_coursemodule_from_instance(constants::M_TABLE, $moduleinstance->id);
            if (empty($cm)) {
                return;
            }

            $course = $DB->get_record('course', ['id' => $cm->course]);
            if (empty($course)) {
                return;
            }

            $usage->timemodified = time();
            $DB->update_record(constants::M_TEMPL_USAGES_TABLE, $usage);

            $modulecontext = context_module::instance($cm->id);
            $contextdata = json_decode($usage->contextdata, true);

            $progressbar = new db_updater($usage->id, constants::M_TEMPL_USAGES_TABLE, 'progress', 0);
            $progressbar->start_progress('Starting generation', count($config->items));

            // Make the AI generator object.
            $aigen = new aigen($cm, $progressbar);

            // Call any tools whose results need to be added to the context data
            // Loop through contextdata looking for fields that start with tool_
            // It will be like tool_user_custonmdata2 with value "fetch_vtt PHP_EOL user_customdata1"
            // That will tell the tool to call the fetch_vtt function with the value of the usercustomdata1 field.
            foreach ($contextdata as $fieldname => $fieldvalue) {
                if (strpos($fieldname, 'tool_') === 0) {
                    // Get the options, e.g ["fetch_vtt", "user_customdata1"]
                    $fieldoptions = $contextdata[$fieldname];
                    // Get the true field name, eg'tool_user_customdata2' -> 'user_customdata2'
                    $truefieldname = substr($fieldname, 5);

                    if (!empty($fieldoptions)) {
                        $tooloptions = explode(PHP_EOL, $fieldoptions);
                        $tool = new aigen_tool($tooloptions, $contextdata);
                        $toolresult = $tool->run();
                        $contextdata[$truefieldname] = $toolresult;
                    }
                    // Remove any  context data with tool_ its useless from here on
                    unset($contextdata[$fieldname]);
                }
            }

            try {
                $importdata = $aigen->make_import_data(
                    $config,
                    $template,
                    $contextdata
                );
                // Make custom field data (if we are a lessonbank and data has not been made yet)
                $aigen->add_custom_field_data($importdata, $contextdata, $usage->templateid);
            } catch (textgenerationfailed $e) {
                $usage->progress = -1;
                $usage->error = $e->getMessage();
                mtrace('Error: --> ' . json_encode(get_exception_info($e)));
                $DB->update_record(constants::M_TEMPL_USAGES_TABLE, $usage);
                return;
            }

            // Do the import.
            $theimport = new import($moduleinstance, $modulecontext, $course, $cm);
            $theimport->set_reader($importdata, true);
            $theimport->import_process();

            // Complete Progress bar.
            $progressbar->end_progress();
        }
    }
}
