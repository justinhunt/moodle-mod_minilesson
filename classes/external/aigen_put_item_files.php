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
 * Uploads media files into one file area of an item.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_put_item_files extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course Module ID of the minilesson'),
            'itemid' => new external_value(PARAM_INT, 'The item'),
            'filearea' => new external_value(
                PARAM_ALPHANUMEXT,
                'The file area, as aigen_fetch_item_type_details lists it for the item type (e.g. itemmedia)'
            ),
            'filesjson' => new external_value(
                PARAM_RAW,
                'A JSON object of file name to base64 content: {"cover.svg": "PHN2Zy..."}'
            ),
            'replace' => new external_value(
                PARAM_BOOL,
                'When true (the default), the files already in the area are removed first. When false, the files '
                . 'are added, and one with the same name as a file already there replaces it.',
                VALUE_DEFAULT,
                true
            ),
        ]);
    }

    /**
     * Upload files.
     *
     * @param int $cmid the course module id
     * @param int $itemid the item
     * @param string $filearea the file area
     * @param string $filesjson the files
     * @param bool $replace empty the area first
     * @return array
     */
    public static function execute($cmid, $itemid, $filearea, $filesjson, $replace = true) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'itemid' => $itemid,
            'filearea' => $filearea,
            'filesjson' => $filesjson,
            'replace' => $replace,
        ]);
        $editor = itemeditor::for_cmid($params['cmid']);
        self::validate_context($editor->get_context());
        require_capability('mod/minilesson:manage', $editor->get_context());

        $files = json_decode($params['filesjson'], true);
        if (!is_array($files)) {
            $files = [];
        }
        $result = $editor->put_files($params['itemid'], $params['filearea'], $files, !empty($params['replace']));
        return [
            'success' => $result->status === itemeditor::STATUS_UPDATED,
            'status' => $result->status,
            'message' => $result->message,
            'version' => $result->version,
            'files' => array_map(function ($file) {
                return (array) $file;
            }, $result->files),
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the files were saved'),
            'status' => new external_value(PARAM_ALPHA, '"updated", "invalid" (see message) or "notfound"'),
            'message' => new external_value(PARAM_RAW, 'Why nothing was saved, when it was not'),
            'version' => new external_value(PARAM_INT, 'The item\'s version now'),
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_RAW, 'The file name'),
                    'size' => new external_value(PARAM_INT, 'Its size in bytes'),
                    'mimetype' => new external_value(PARAM_RAW, 'Its MIME type'),
                ]),
                'The files in the area now'
            ),
        ]);
    }
}
