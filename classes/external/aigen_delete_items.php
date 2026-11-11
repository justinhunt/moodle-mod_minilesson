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
 * Deletes items, with their media, from a minilesson.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_delete_items extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID of the minilesson'),
            'itemids' => new external_multiple_structure(new external_value(PARAM_INT, 'An item id'), 'The items to delete'),
            'dryrun' => new external_value(
                PARAM_BOOL,
                'When true, nothing is changed: the call is checked and reports what it would do.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Delete items.
     *
     * @param int $cmid the course module id
     * @param int[] $itemids the items
     * @param bool $dryrun list only
     * @return array
     */
    public static function execute($cmid, $itemids, $dryrun = false) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'itemids' => $itemids,
            'dryrun' => $dryrun,
        ]);
        $editor = itemeditor::for_cmid($params['cmid']);
        self::validate_context($editor->get_context());
        require_capability('mod/minilesson:manage', $editor->get_context());

        $result = $editor->delete_items($params['itemids'], !empty($params['dryrun']));
        return [
            'success' => true,
            'dryrun' => !empty($params['dryrun']),
            'deleted' => array_map(function ($entry) {
                return (array) $entry;
            }, $result->deleted),
            'notfound' => $result->notfound,
            'warning' => $result->deleted && empty($params['dryrun']) ? $editor->attempts_warning() : '',
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the call ran'),
            'dryrun' => new external_value(PARAM_BOOL, 'True if nothing was deleted'),
            'deleted' => new external_multiple_structure(
                new external_single_structure([
                    'itemid' => new external_value(PARAM_INT, 'The item id'),
                    'name' => new external_value(PARAM_RAW, 'The item name'),
                    'type' => new external_value(PARAM_RAW, 'The item type'),
                    'position' => new external_value(PARAM_INT, 'Its 1-based position before the delete'),
                ]),
                'The items deleted (in a dry run, that would be)'
            ),
            'notfound' => new external_multiple_structure(
                new external_value(PARAM_INT, 'An item id'),
                'Item ids this lesson does not have'
            ),
            'warning' => new external_value(
                PARAM_RAW,
                'Set when learners have attempts at the lesson: their results are stored by item position, so tell '
                . 'the teacher that earlier results may now show against the wrong items'
            ),
        ]);
    }
}
