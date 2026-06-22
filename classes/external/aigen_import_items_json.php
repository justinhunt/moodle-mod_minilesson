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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\constants;
use mod_minilesson\import;
use mod_minilesson\utils;

/**
 * Class aigen_import_items_json
 *
 * Imports a JSON items payload (same shape produced by aigen_export_items_json)
 * into a target minilesson.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_import_items_json extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID'),
            'itemsjson' => new external_value(PARAM_RAW, 'JSON-encoded items and files payload'),
        ]);
    }

    public static function execute($cmid, $itemsjson) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'itemsjson' => $itemsjson,
        ]);

        $cm = get_coursemodule_from_id('minilesson', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $moduleinstance = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);

        $modulecontext = context_module::instance($cm->id);
        self::validate_context($modulecontext);
        require_capability('mod/minilesson:manage', $modulecontext);

        if (!utils::is_json($params['itemsjson'])) {
            return [
                'success' => false,
                'itemcount' => 0,
                'errormsg' => get_string('error:invalidjson', constants::M_COMPONENT),
            ];
        }

        $importdata = json_decode($params['itemsjson']);
        if (!isset($importdata->items) || !is_array($importdata->items)) {
            return [
                'success' => false,
                'itemcount' => 0,
                'errormsg' => get_string('error:noitemsinjson', constants::M_COMPONENT),
            ];
        }

        raise_memory_limit(MEMORY_HUGE);

        $theimport = new import($moduleinstance, $modulecontext, $course, $cm);
        $theimport->set_reader($importdata, true);

        // import_process() writes a results table to output; swallow it so the
        // web service response stays valid JSON.
        ob_start();
        try {
            $theimport->import_process();
        } finally {
            ob_end_clean();
        }

        return [
            'success' => true,
            'itemcount' => count($importdata->items),
        ];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the import ran without a fatal error'),
            'itemcount' => new external_value(PARAM_INT, 'Number of items in the supplied payload'),
            'errormsg' => new external_value(PARAM_RAW, 'Error message when success is false', VALUE_OPTIONAL),
        ]);
    }
}
