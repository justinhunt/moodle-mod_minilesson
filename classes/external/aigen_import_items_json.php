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
use core_external\external_multiple_structure;
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
    /**
     * parameters for import items json
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID'),
            'itemsjson' => new external_value(PARAM_RAW, 'JSON-encoded items and files payload'),
        ]);
    }

    /**
     * Import a JSON items payload into a minilesson.
     * @param int $cmid the course module id of the target minilesson
     * @param string $itemsjson the JSON-encoded items and files payload
     * @return array
     */
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
        $results = $theimport->import_process();

        $ret = [
            'success' => true,
            'itemcount' => $results->imported,
            'total' => $results->total,
            'failed' => $results->failed,
            'errors' => array_map(function ($error) {
                return [
                    'itemnum' => $error->itemnum,
                    'type' => $error->type,
                    'name' => $error->name,
                    'message' => $error->message,
                ];
            }, $results->errors),
        ];
        if ($results->failed > 0) {
            $ret['errormsg'] = get_string(
                'importpartial',
                constants::M_COMPONENT,
                ['imported' => $results->imported, 'total' => $results->total]
            );
        }
        return $ret;
    }

    /**
     * return structure of the import results
     * @return external_single_structure
     */
    public static function execute_returns() {
        $errorstructure = new external_single_structure([
            'itemnum' => new external_value(PARAM_INT, '1-based position of the rejected item in the submitted items array'),
            'type' => new external_value(PARAM_RAW, 'The item type of the rejected item'),
            'name' => new external_value(PARAM_RAW, 'The name of the rejected item'),
            'message' => new external_value(
                PARAM_RAW,
                'Why the item was rejected, prefixed with the offending import field name where known. '
                . 'Fix the item and resubmit it (already imported items should not be resubmitted)'
            ),
        ]);
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the import ran without a fatal error'),
            'itemcount' => new external_value(PARAM_INT, 'Number of items successfully imported'),
            'total' => new external_value(PARAM_INT, 'Number of items in the supplied payload', VALUE_OPTIONAL),
            'failed' => new external_value(PARAM_INT, 'Number of items that were rejected', VALUE_OPTIONAL),
            'errors' => new external_multiple_structure($errorstructure, 'Per-item import failures', VALUE_OPTIONAL),
            'errormsg' => new external_value(
                PARAM_RAW,
                'Error message when success is false or some items failed',
                VALUE_OPTIONAL
            ),
        ]);
    }
}
