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
 * Returns the media files of an item, with pictures scaled down so an AI can look at them.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_get_item_media extends external_api {
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
                'Only this file area (e.g. itemmedia). Leave empty for all of the item\'s files.',
                VALUE_DEFAULT,
                ''
            ),
            'maxwidth' => new external_value(
                PARAM_INT,
                'Pictures wider than this many pixels are scaled down to it (100 to 2000, default 800)',
                VALUE_DEFAULT,
                800
            ),
        ]);
    }

    /**
     * Get an item's media.
     *
     * @param int $cmid the course module id
     * @param int $itemid the item
     * @param string $filearea one file area, or ''
     * @param int $maxwidth the widest picture
     * @return array
     */
    public static function execute($cmid, $itemid, $filearea = '', $maxwidth = 800) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'itemid' => $itemid,
            'filearea' => $filearea,
            'maxwidth' => $maxwidth,
        ]);
        $editor = itemeditor::for_cmid($params['cmid']);
        self::validate_context($editor->get_context());
        require_capability('mod/minilesson:export', $editor->get_context());

        $maxwidth = min(2000, max(100, (int) $params['maxwidth']));
        $media = $editor->get_media($params['itemid'], (string) $params['filearea'], $maxwidth);
        if ($media === null) {
            return [
                'success' => false,
                'media' => [],
                'errormsg' => get_string('edit:notfound', constants::M_COMPONENT, $params['itemid']),
            ];
        }
        return [
            'success' => true,
            'media' => array_map(function ($entry) {
                return (array) $entry;
            }, $media),
            'errormsg' => '',
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the item was found'),
            'media' => new external_multiple_structure(
                new external_single_structure([
                    'filearea' => new external_value(PARAM_RAW, 'The file area'),
                    'filename' => new external_value(PARAM_RAW, 'The file name'),
                    'mimetype' => new external_value(PARAM_RAW, 'The stored file\'s MIME type'),
                    'size' => new external_value(PARAM_INT, 'The stored file\'s size in bytes'),
                    'url' => new external_value(PARAM_RAW, 'The file\'s address in Moodle (needs a logged in user)'),
                    'width' => new external_value(PARAM_INT, 'A picture\'s width as returned, in pixels, or 0'),
                    'height' => new external_value(PARAM_INT, 'A picture\'s height as returned, in pixels, or 0'),
                    'content' => new external_value(
                        PARAM_RAW,
                        'A picture as base64 (scaled down), an SVG as its text, or empty for audio, video and others'
                    ),
                    'contentmimetype' => new external_value(PARAM_RAW, 'The MIME type of content, when there is content'),
                ]),
                'The item\'s files'
            ),
            'errormsg' => new external_value(PARAM_RAW, 'Why nothing was returned, when success is false'),
        ]);
    }
}
