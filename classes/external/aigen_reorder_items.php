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
 * Puts the items of a minilesson in a new order.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_reorder_items extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID of the minilesson'),
            'itemids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'An item id'),
                'Every item id of the lesson, each once, in the new order (get them from aigen_get_items)'
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
     * Reorder the items.
     *
     * @param int $cmid the course module id
     * @param int[] $itemids the new order
     * @param bool $dryrun check only
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

        $current = $editor->get_order();
        $response = [
            'success' => false,
            'dryrun' => !empty($params['dryrun']),
            'changed' => false,
            'itemids' => $current,
            'warning' => '',
            'errormsg' => '',
        ];
        $given = array_map('intval', $params['itemids']);
        $a = $given;
        $b = $current;
        sort($a);
        sort($b);
        if ($a !== $b) {
            $response['errormsg'] = get_string('edit:badorder', constants::M_COMPONENT);
            return $response;
        }
        $response['changed'] = $given !== $current;
        if ($response['changed'] && empty($params['dryrun'])) {
            $editor->set_order($given);
            $response['itemids'] = $editor->get_order();
            $response['warning'] = $editor->attempts_warning();
        } else if ($response['changed']) {
            $response['itemids'] = $given;
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
            'success' => new external_value(PARAM_BOOL, 'True if the order was accepted'),
            'dryrun' => new external_value(PARAM_BOOL, 'True if nothing was changed'),
            'changed' => new external_value(PARAM_BOOL, 'True if the new order differs from the old'),
            'itemids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'An item id'),
                'The lesson\'s items in their order now (in a dry run, the order they would have)'
            ),
            'warning' => new external_value(
                PARAM_RAW,
                'Set when learners have attempts at the lesson: their results are stored by item position, so tell '
                . 'the teacher that earlier results may now show against the wrong items'
            ),
            'errormsg' => new external_value(
                PARAM_RAW,
                'Why the order was refused, when success is false. Nothing was changed.'
            ),
        ]);
    }
}
