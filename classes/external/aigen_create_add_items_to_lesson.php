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

namespace mod_minilesson\external;

use context_module;
use core_external\external_single_structure;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use mod_minilesson\aigen;
use mod_minilesson\aigen_form;
use mod_minilesson\constants;
use mod_minilesson\task\process_aigen;
use mod_minilesson\utils;
use stdClass;
use core\task\manager;

/**
 * Class aigen_create_add_items_to_lesson
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_create_add_items_to_lesson extends external_api {
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, "Course Module ID"),
            'templateid' => new external_value(PARAM_INT, 'Template ID'),
            'contextdata' => new external_single_structure([
                'target_language' => new external_value(PARAM_RAW, 'Target Language', VALUE_OPTIONAL),
                'user_topic' => new external_value(PARAM_RAW, 'User Topic', VALUE_OPTIONAL),
                'user_level' => new external_value(PARAM_RAW, 'User Level', VALUE_OPTIONAL),
                'user_text' => new external_value(PARAM_RAW, 'User Text', VALUE_OPTIONAL),
                'user_keywords' => new external_value(PARAM_RAW, 'User Keywords', VALUE_OPTIONAL),
                'user_customdata1' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata2' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata3' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata4' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata5' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata6' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata7' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata8' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata9' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
                'user_customdata10' => new external_value(PARAM_RAW, 'User Custom Data', VALUE_OPTIONAL),
            ], '', VALUE_OPTIONAL),
        ]);
    }

    public static function execute($cmid, $templateid, $contextdata) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'templateid' => $templateid,
            'contextdata' => $contextdata,
        ]);

        $modulecontext = context_module::instance($params['cmid']);
        self::validate_context($modulecontext);

        require_capability('mod/minilesson:canuseaigen', $modulecontext);

        $cm = get_coursemodule_from_id('minilesson', $params['cmid'], 0, false, MUST_EXIST);
        $moduleinstance = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);

        $othercontextdata = utils::fetch_usercontext_fields($modulecontext->ttslanguage);
        // The contextdata argument is optional, so a caller that sends none reaches the required-input
        // check below with nothing set, and is told which inputs are missing rather than hitting a warning.
        $submittedcontextdata = $params['contextdata'] ?? [];
        foreach (aigen_form::mappings() as $fieldname) {
            if (array_key_exists($fieldname, $submittedcontextdata)) {
                $othercontextdata[$fieldname] = $submittedcontextdata[$fieldname];
            }
        }

        self::require_template_inputs($params['templateid'], $othercontextdata);

        $usagesdata = new stdClass();
        $usagesdata->minilessonid = $moduleinstance->id;
        $usagesdata->templateid = $params['templateid'];
        $usagesdata->contextdata = json_encode($othercontextdata);
        $usagesdata->timecreated = time();
        $usagesdata->id = $DB->insert_record(constants::M_TEMPL_USAGES_TABLE, $usagesdata);

        $task = new process_aigen();
        $task->set_component(constants::M_COMPONENT);
        $task->set_custom_data(['usageid' => $usagesdata->id]);
        $task->set_userid($USER->id);
        manager::queue_adhoc_task($task);
        return ['jobid' => $usagesdata->id];
    }

    /**
     * Reject a generation run that leaves a required template input empty.
     *
     * An input the template actually consumes has no default to fall back on, so an empty value
     * does not simply get skipped - it generates plausible looking content with a hole where the
     * teaching point should be (a vocabulary card whose translation repeats the word, a gap-fill
     * whose hint is missing). That is invisible in the finished lesson, so it is caught here
     * rather than left for the user to discover.
     *
     * @param int $templateid The template about to be run.
     * @param array $contextdata The merged context data the template will be run with.
     * @throws \moodle_exception If any required input is empty.
     */
    protected static function require_template_inputs($templateid, array $contextdata) {
        global $DB;

        $template = $DB->get_record('minilesson_templates', ['id' => $templateid], '*', MUST_EXIST);
        $config = json_decode($template->config);
        if (empty($config)) {
            return;
        }

        $missing = [];
        foreach (aigen::template_required_inputs($config) as $fieldname) {
            $value = $contextdata[$fieldname] ?? '';
            if (is_scalar($value) && trim((string) $value) !== '') {
                continue;
            }
            $title = $config->fieldmappings->{$fieldname}->title ?? '';
            $missing[] = $title === '' ? $fieldname : $fieldname . ' (' . $title . ')';
        }

        if ($missing) {
            // A moodle_exception, not an invalid_parameter_exception: the latter carries its detail in
            // debuginfo, which is stripped from the web service response unless the site is in developer
            // debugging. The caller has to be told which inputs were empty for the error to be actionable.
            $a = (object) [
                'template' => $template->name,
                'missing' => implode(', ', $missing),
            ];
            throw new \moodle_exception('aigenmissingrequiredinputs', constants::M_COMPONENT, '', $a);
        }
    }

    public static function execute_returns() {
        return new external_single_structure([
            'jobid' => new external_value(PARAM_INT, 'Job ID'),
        ]);
    }
}
