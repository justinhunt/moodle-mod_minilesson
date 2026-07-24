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
use mod_minilesson\import;

/**
 * Class aigen_export_items_json
 *
 * Exports all items from a minilesson as a JSON string (same shape as the
 * import/export file used by the import page and the AIGEN template flow).
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_export_items_json extends external_api {
    /**
     * Describes the parameters for the export function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID'),
            'exclude_files' => new external_value(
                PARAM_BOOL,
                'When true, each embedded media file\'s base64 content is replaced with the placeholder '
                . '"QQQQ" (the files array, file areas and filenames are kept, only the bytes are dropped). '
                . 'Set this to true when you do not need the actual media - to read or transform the lesson\'s '
                . 'structure and text, to reuse the lesson as a template for other topics (where the original '
                . 'images are dropped anyway), or to keep the response under a client size limit. Leave it false '
                . '(the default) for a faithful, re-importable copy that includes the media.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Exports a minilesson's items as a JSON string.
     *
     * @param int $cmid The course module id.
     * @param bool $excludefiles When true, replace file base64 content with the "QQQQ" placeholder.
     * @return array success flag and the items JSON.
     */
    public static function execute($cmid, $excludefiles = false) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'exclude_files' => $excludefiles,
        ]);

        $cm = get_coursemodule_from_id('minilesson', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $moduleinstance = $DB->get_record('minilesson', ['id' => $cm->instance], '*', MUST_EXIST);

        $modulecontext = context_module::instance($cm->id);
        self::validate_context($modulecontext);
        require_capability('mod/minilesson:export', $modulecontext);

        $theimport = new import($moduleinstance, $modulecontext, $course, $cm);
        $itemsjson = $theimport->export_items(true);

        // Optionally strip the (potentially large) base64 file bytes, keeping the files array shape.
        // Same placeholder strategy as the AIGEN template flow (see aigen_uploadform.php).
        if (!empty($params['exclude_files'])) {
            $exportobj = json_decode($itemsjson);
            if ($exportobj !== null && !empty($exportobj->files)) {
                foreach ($exportobj->files as $fileareas) {
                    foreach ($fileareas as $areaname => $filearea) {
                        $fileareas->{$areaname} = array_fill_keys(array_keys((array) $filearea), 'QQQQ');
                    }
                }
                $itemsjson = json_encode($exportobj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return [
            'success' => true,
            'itemsjson' => $itemsjson,
        ];
    }

    /**
     * Describes the return value of the export function.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if export succeeded'),
            'itemsjson' => new external_value(PARAM_RAW, 'JSON-encoded items and files payload'),
        ]);
    }
}
