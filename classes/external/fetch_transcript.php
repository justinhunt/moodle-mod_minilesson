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
 * Web service that fetches the subtitles (WebVTT) of a YouTube video.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_transcript extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The module context id'),
            'url' => new external_value(PARAM_RAW_TRIMMED, 'The YouTube video URL or ID'),
            'lang' => new external_value(PARAM_TEXT, 'Preferred subtitle language, e.g. en-US', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Fetch the WebVTT subtitles of a YouTube video.
     *
     * @param int $contextid the module context id
     * @param string $url the YouTube video URL or ID
     * @param string $lang preferred subtitle language
     * @return array success flag, vtt content and error message
     */
    public static function execute($contextid, $url, $lang = '') {
        $params = self::validate_parameters(self::execute_parameters(),
            ['contextid' => $contextid, 'url' => $url, 'lang' => $lang]);

        $context = context::instance_by_id($params['contextid']);
        self::validate_context($context);
        require_capability('mod/minilesson:itemedit', $context);

        // The only consumer is the shadow item form, whose fetch button is gated
        // by this admin setting — enforce it here too.
        if (empty(get_config('minilessonitem_shadow', 'enablesubtitlefetch'))) {
            return ['success' => false, 'vtt' => '',
                'message' => get_string('error:subtitlefetchdisabled', 'minilessonitem_shadow')];
        }

        // Malformed or malicious URLs are rejected outright.
        $videoid = youtubetranscript::get_video_id($params['url']);
        if ($videoid === null) {
            throw new \invalid_parameter_exception('Not a valid YouTube URL or video ID');
        }

        // Try the activity's language first, then the defaults.
        $preflangs = [];
        if (preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/', $params['lang'])) {
            $preflangs[] = $params['lang'];
            $preflangs[] = explode('-', $params['lang'])[0];
        }
        $preflangs = array_values(array_unique(array_merge($preflangs, youtubetranscript::DEFAULT_LANGS)));

        try {
            $fetcher = new youtubetranscript();
            $result = $fetcher->fetch($videoid, youtubetranscript::FORMAT_VTT, $preflangs);
            return ['success' => true, 'vtt' => $result['vtt'], 'message' => ''];
        } catch (\moodle_exception $e) {
            return ['success' => false, 'vtt' => '', 'message' => $e->getMessage()];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether subtitles were fetched'),
            'vtt' => new external_value(PARAM_RAW, 'The WebVTT subtitle content'),
            'message' => new external_value(PARAM_TEXT, 'Error message if unsuccessful'),
        ]);
    }
}
