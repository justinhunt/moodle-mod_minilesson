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
use mod_minilesson\local\aigen\itemeditor;

/**
 * Reads items of a minilesson for editing: the export format, plus each item's id, version and position.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_get_items extends external_api {
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
                'The items to read. Leave empty to read every item of the lesson.',
                VALUE_DEFAULT,
                []
            ),
            'fields' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'An item field name, as aigen_fetch_item_type_details names it'),
                'Limit each item to these fields, to keep the response small - for example '
                . '["answers", "correctanswer", "correctfeedback"]. The itemid, version, position, type and name '
                . 'are always given. Leave empty for every field.',
                VALUE_DEFAULT,
                []
            ),
            'exclude_files' => new external_value(
                PARAM_BOOL,
                'When true (the default), each media file\'s base64 content is replaced with the placeholder '
                . '"QQQQ": you still see which file areas and file names an item has. Set it to false only when you '
                . 'need the media itself.',
                VALUE_DEFAULT,
                true
            ),
        ]);
    }

    /**
     * Read items of a minilesson.
     *
     * @param int $cmid the course module id
     * @param int[] $itemids the items wanted, or none for all
     * @param string[] $fields the fields wanted, or none for all
     * @param bool $excludefiles replace file content with a placeholder
     * @return array
     */
    public static function execute($cmid, $itemids = [], $fields = [], $excludefiles = true) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'itemids' => $itemids,
            'fields' => $fields,
            'exclude_files' => $excludefiles,
        ]);

        $cm = get_coursemodule_from_id('minilesson', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $moduleinstance = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);

        $modulecontext = context_module::instance($cm->id);
        self::validate_context($modulecontext);
        require_capability('mod/minilesson:export', $modulecontext);

        $editor = new itemeditor($cm, $moduleinstance, $course, $modulecontext);
        $export = $editor->export_items($params['itemids'], $params['fields'], !empty($params['exclude_files']));

        return [
            'success' => true,
            'itemcount' => count($export->items),
            'itemsjson' => json_encode(
                ['items' => $export->items, 'files' => $export->files],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'notfound' => $export->notfound,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the read succeeded'),
            'itemcount' => new external_value(PARAM_INT, 'Number of items returned'),
            'itemsjson' => new external_value(
                PARAM_RAW,
                'JSON: {"items": [...], "files": {...}}. Each item is in the import format, in lesson order, plus '
                . '"itemid", "version" (pass it back to aigen_update_items) and "position" (1-based place in the '
                . 'lesson). An item with media has a "filesid", and its files are under that key in "files" as '
                . '{file area: {file name: base64 content, or "QQQQ"}}.'
            ),
            'notfound' => new external_multiple_structure(
                new external_value(PARAM_INT, 'An item id'),
                'Requested item ids that this lesson does not have'
            ),
        ]);
    }
}
