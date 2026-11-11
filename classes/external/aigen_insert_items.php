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
use mod_minilesson\local\aigen\itemeditor;

/**
 * Adds new items to a minilesson at a chosen place.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_insert_items extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID of the minilesson'),
            'itemsjson' => new external_value(
                PARAM_RAW,
                'The new items, in exactly the format aigen_import_items_json takes: {"items": [...], "files": {...}, '
                . '"fileprompts": {...}}'
            ),
            'after_itemid' => new external_value(
                PARAM_INT,
                'Where the items go: after the item with this id, 0 for the start of the lesson, or -1 (the default) '
                . 'for the end. They keep the order given.',
                VALUE_DEFAULT,
                -1
            ),
            'dryrun' => new external_value(
                PARAM_BOOL,
                'When true, nothing is changed: the call is checked and reports what it would do.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Add new items and place them.
     *
     * @param int $cmid the course module id
     * @param string $itemsjson the items payload
     * @param int $afteritemid where they go
     * @param bool $dryrun check only
     * @return array
     */
    public static function execute($cmid, $itemsjson, $afteritemid = -1, $dryrun = false) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'itemsjson' => $itemsjson,
            'after_itemid' => $afteritemid,
            'dryrun' => $dryrun,
        ]);
        $editor = itemeditor::for_cmid($params['cmid']);
        self::validate_context($editor->get_context());
        require_capability('mod/minilesson:manage', $editor->get_context());

        $response = [
            'success' => false,
            'dryrun' => !empty($params['dryrun']),
            'itemcount' => 0,
            'total' => 0,
            'failed' => 0,
            'itemids' => [],
            'errors' => [],
            'warnings' => [],
            'imagefailures' => [],
            'warning' => '',
            'errormsg' => '',
        ];
        $payload = json_decode($params['itemsjson']);
        if (!is_object($payload) || !isset($payload->items) || !is_array($payload->items)) {
            $response['errormsg'] = get_string('error:noitemsinjson', constants::M_COMPONENT);
            return $response;
        }
        if (!$editor->is_valid_after($params['after_itemid'])) {
            $response['errormsg'] = get_string('edit:badafter', constants::M_COMPONENT, $params['after_itemid']);
            return $response;
        }
        $response['total'] = count($payload->items);
        $tidy = function ($entry) {
            return [
                'itemnum' => (int) ($entry->itemnum ?? 0),
                'type' => (string) ($entry->type ?? ''),
                'name' => (string) ($entry->name ?? ''),
                'message' => (string) ($entry->message ?? ''),
            ];
        };

        if (!empty($params['dryrun'])) {
            $problems = $editor->check_new_items($payload);
            $response['errors'] = array_map($tidy, $problems);
            $response['failed'] = count($problems);
            $response['itemcount'] = $response['total'] - $response['failed'];
            $response['success'] = true;
            return $response;
        }

        raise_memory_limit(MEMORY_HUGE);
        $results = $editor->insert_items($payload, $params['after_itemid']);
        $response['itemcount'] = (int) $results->imported;
        $response['failed'] = (int) $results->failed;
        $response['itemids'] = $results->itemids;
        $response['errors'] = array_map($tidy, $results->errors);
        $response['warnings'] = array_map($tidy, $results->warnings);
        $response['imagefailures'] = array_map(function ($failure) {
            return [
                'filesid' => (string) $failure['filesid'],
                'filearea' => (string) $failure['filearea'],
                'filename' => (string) $failure['filename'],
                'message' => (string) $failure['message'],
            ];
        }, array_map(function ($failure) {
            return (array) $failure;
        }, $results->imagefailures ?: []));
        if ($results->itemids) {
            $response['warning'] = $editor->attempts_warning();
        }
        $response['success'] = true;
        return $response;
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        $entry = new external_single_structure([
            'itemnum' => new external_value(PARAM_INT, '1-based position of the item in the items you sent'),
            'type' => new external_value(PARAM_RAW, 'The item type'),
            'name' => new external_value(PARAM_RAW, 'The item name'),
            'message' => new external_value(PARAM_RAW, 'What is wrong, prefixed with the field name where known'),
        ]);
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the items were read and tried. Check errors.'),
            'dryrun' => new external_value(PARAM_BOOL, 'True if nothing was added'),
            'itemcount' => new external_value(PARAM_INT, 'Number of items added (or, in a dry run, that would be)'),
            'total' => new external_value(PARAM_INT, 'Number of items sent'),
            'failed' => new external_value(PARAM_INT, 'Number of items refused'),
            'itemids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'An item id'),
                'The new items, in the order sent'
            ),
            'errors' => new external_multiple_structure($entry, 'The refused items: fix them and send only those again'),
            'warnings' => new external_multiple_structure($entry, 'Things worth fixing in items that were added'),
            'imagefailures' => new external_multiple_structure(
                new external_single_structure([
                    'filesid' => new external_value(PARAM_RAW, 'The filesid of the prompt'),
                    'filearea' => new external_value(PARAM_RAW, 'The file area of the prompt'),
                    'filename' => new external_value(PARAM_RAW, 'The file name of the prompt'),
                    'message' => new external_value(PARAM_RAW, 'Why no image was made'),
                ]),
                'Image prompts that made no picture'
            ),
            'warning' => new external_value(
                PARAM_RAW,
                'Set when learners have attempts at the lesson: their results are stored by item position, so tell '
                . 'the teacher that earlier results may now show against the wrong items'
            ),
            'errormsg' => new external_value(PARAM_RAW, 'Why nothing was tried, when success is false'),
        ]);
    }
}
