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
 * Changes fields of existing items of a minilesson, leaving the rest of each item as it is.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_update_items extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID of the minilesson'),
            'updatesjson' => new external_value(
                PARAM_RAW,
                'A JSON array with one entry per item to change: '
                . '[{"itemid": 123, "version": 1731234567, "changes": {"correctfeedback": "Well done."}}]. '
                . '"itemid" and "version" come from aigen_get_items. "changes" holds only the fields to change, '
                . 'named and formatted as aigen_fetch_item_type_details documents for that item type; every '
                . 'field you leave out keeps its stored value. "version" is optional: when given, the item is '
                . 'not changed if someone else has edited it since you read it. The item type and media files '
                . 'cannot be changed.'
            ),
            'dryrun' => new external_value(
                PARAM_BOOL,
                'When true, nothing is saved: each item is checked and the changes it would get are returned. '
                . 'Use it to show the teacher what will change before applying it.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Change fields of existing items.
     *
     * @param int $cmid the course module id
     * @param string $updatesjson the updates, as JSON
     * @param bool $dryrun report the changes without saving them
     * @return array
     */
    public static function execute($cmid, $updatesjson, $dryrun = false) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'updatesjson' => $updatesjson,
            'dryrun' => $dryrun,
        ]);

        $cm = get_coursemodule_from_id('minilesson', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $moduleinstance = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);

        $modulecontext = context_module::instance($cm->id);
        self::validate_context($modulecontext);
        require_capability('mod/minilesson:manage', $modulecontext);

        $response = [
            'success' => false,
            'dryrun' => !empty($params['dryrun']),
            'updated' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'results' => [],
            'errormsg' => '',
        ];

        $updates = json_decode($params['updatesjson']);
        // One update sent on its own, not in an array, is clear enough to accept.
        if (is_object($updates) && isset($updates->itemid)) {
            $updates = [$updates];
        }
        if (!is_array($updates) || !$updates) {
            $response['errormsg'] = get_string('edit:badupdates', constants::M_COMPONENT);
            return $response;
        }

        $editor = new itemeditor($cm, $moduleinstance, $course, $modulecontext);
        foreach ($updates as $update) {
            $itemid = is_object($update) ? (int) ($update->itemid ?? 0) : 0;
            $changes = is_object($update) ? ($update->changes ?? null) : null;
            if (!$itemid || !is_object($changes)) {
                $response['failed']++;
                $response['results'][] = [
                    'itemid' => $itemid,
                    'status' => itemeditor::STATUS_INVALID,
                    'message' => get_string('edit:badupdate', constants::M_COMPONENT),
                    'version' => 0,
                    'changes' => [],
                    'warnings' => [],
                ];
                continue;
            }
            $version = empty($update->version) ? null : (int) $update->version;
            $result = $editor->update_item($itemid, $changes, $version, !empty($params['dryrun']));

            if (in_array($result->status, [itemeditor::STATUS_UPDATED, itemeditor::STATUS_WOULDUPDATE])) {
                $response['updated']++;
            } else if ($result->status === itemeditor::STATUS_UNCHANGED) {
                $response['unchanged']++;
            } else {
                $response['failed']++;
            }
            $response['results'][] = [
                'itemid' => $result->itemid,
                'status' => $result->status,
                'message' => $result->message,
                'version' => $result->version,
                'changes' => array_map(function ($change) {
                    return (array) $change;
                }, $result->changes),
                'warnings' => $result->warnings,
            ];
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
            'success' => new external_value(PARAM_BOOL, 'True if the updates were read and tried. Check each result.'),
            'dryrun' => new external_value(PARAM_BOOL, 'True if this was a dry run and nothing was saved'),
            'updated' => new external_value(PARAM_INT, 'Number of items changed (or, in a dry run, that would be)'),
            'unchanged' => new external_value(PARAM_INT, 'Number of items that already held the values sent'),
            'failed' => new external_value(PARAM_INT, 'Number of items not changed: see their status and message'),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'itemid' => new external_value(PARAM_INT, 'The item id'),
                    'status' => new external_value(
                        PARAM_ALPHA,
                        '"updated"; "wouldupdate" (dry run); "unchanged" (it already held these values); '
                        . '"conflict" (edited by someone else since you read it: read it again with '
                        . 'aigen_get_items, then decide); "invalid" (the changes were refused: fix what the '
                        . 'message says and resend this item); "notfound"; or "error" (it could not be saved)'
                    ),
                    'message' => new external_value(PARAM_RAW, 'Why the item was not changed, when it was not'),
                    'version' => new external_value(PARAM_INT, 'The item\'s version now'),
                    'changes' => new external_multiple_structure(
                        new external_single_structure([
                            'field' => new external_value(PARAM_RAW, 'The field name'),
                            'before' => new external_value(PARAM_RAW, 'Its value before (a list is given as JSON)'),
                            'after' => new external_value(PARAM_RAW, 'Its value after (a list is given as JSON)'),
                        ]),
                        'The fields that changed, or would change. A field you sent that is not listed already '
                        . 'held that value.'
                    ),
                    'warnings' => new external_multiple_structure(
                        new external_value(PARAM_RAW, 'A warning'),
                        'Things worth fixing in the item that did not stop the update'
                    ),
                ]),
                'One result per update, in the order sent'
            ),
            'errormsg' => new external_value(PARAM_RAW, 'Why nothing was tried, when success is false'),
        ]);
    }
}
