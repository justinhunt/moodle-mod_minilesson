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

use context;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_minilesson\youtubetranscript;

/**
 * Web service that converts a transcript copied from YouTube's "Show transcript"
 * panel into WebVTT.
 *
 * This is the route authors use when YouTube refuses to serve subtitles to the
 * server. It performs no outbound request of its own - the author supplies the
 * text - so unlike fetching it is not gated by the subtitle fetch setting.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class convert_transcript extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The module context id'),
            'transcript' => new external_value(PARAM_RAW, 'The transcript copied from YouTube'),
        ]);
    }

    /**
     * Convert a pasted YouTube transcript into WebVTT.
     *
     * @param int $contextid the module context id
     * @param string $transcript the transcript copied from YouTube
     * @return array success flag, vtt content, cue count and error message
     */
    public static function execute($contextid, $transcript) {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['contextid' => $contextid, 'transcript' => $transcript]
        );

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('mod/minilesson:itemedit', $context);

        try {
            $vtt = youtubetranscript::transcript_to_vtt($params['transcript']);
        } catch (\moodle_exception $e) {
            return ['success' => false, 'vtt' => '', 'cuecount' => 0, 'message' => $e->getMessage()];
        }

        return [
            'success' => true,
            'vtt' => $vtt,
            'cuecount' => substr_count($vtt, '-->'),
            'message' => '',
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the transcript could be converted'),
            'vtt' => new external_value(PARAM_RAW, 'The WebVTT subtitle content'),
            'cuecount' => new external_value(PARAM_INT, 'How many subtitle lines were produced'),
            'message' => new external_value(PARAM_TEXT, 'Error message if unsuccessful'),
        ]);
    }
}
