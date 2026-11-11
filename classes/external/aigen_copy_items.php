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
 * Copies items, with their media, from one minilesson into another (or the same one).
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_copy_items extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID of the minilesson to copy into'),
            'source_cmid' => new external_value(PARAM_INT, 'Course Module ID of the minilesson to copy from'),
            'itemids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'An item id'),
                'The items to copy, from the source lesson. They are copied in the source lesson\'s order.'
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
     * Copy items.
     *
     * @param int $cmid the lesson to copy into
     * @param int $sourcecmid the lesson to copy from
     * @param int[] $itemids the items
     * @param int $afteritemid where they go
     * @param bool $dryrun check only
     * @return array
     */
    public static function execute($cmid, $sourcecmid, $itemids, $afteritemid = -1, $dryrun = false) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'source_cmid' => $sourcecmid,
            'itemids' => $itemids,
            'after_itemid' => $afteritemid,
            'dryrun' => $dryrun,
        ]);
        $source = itemeditor::for_cmid($params['source_cmid']);
        self::validate_context($source->get_context());
        require_capability('mod/minilesson:export', $source->get_context());
        $editor = itemeditor::for_cmid($params['cmid']);
        self::validate_context($editor->get_context());
        require_capability('mod/minilesson:manage', $editor->get_context());

        $response = [
            'success' => false,
            'dryrun' => !empty($params['dryrun']),
            'itemids' => [],
            'notfound' => [],
            'warning' => '',
            'errormsg' => '',
        ];
        if (!$editor->is_valid_after($params['after_itemid'])) {
            $response['errormsg'] = get_string('edit:badafter', constants::M_COMPONENT, $params['after_itemid']);
            return $response;
        }
        if (!empty($params['dryrun'])) {
            $sourceorder = $source->get_order();
            $response['itemids'] = array_values(array_intersect($sourceorder, $params['itemids']));
            $response['notfound'] = array_values(array_diff($params['itemids'], $sourceorder));
            $response['success'] = true;
            return $response;
        }
        $result = $editor->copy_items($source, $params['itemids'], $params['after_itemid']);
        $response['itemids'] = $result->itemids;
        $response['notfound'] = $result->notfound;
        if ($result->itemids) {
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
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the copy ran'),
            'dryrun' => new external_value(PARAM_BOOL, 'True if nothing was copied'),
            'itemids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'An item id'),
                'The new copies (in a dry run, the source items that would be copied), in order'
            ),
            'notfound' => new external_multiple_structure(
                new external_value(PARAM_INT, 'An item id'),
                'Item ids the source lesson does not have'
            ),
            'warning' => new external_value(
                PARAM_RAW,
                'Set when learners have attempts at the lesson: their results are stored by item position, so tell '
                . 'the teacher that earlier results may now show against the wrong items'
            ),
            'errormsg' => new external_value(PARAM_RAW, 'Why nothing was copied, when success is false'),
        ]);
    }
}
