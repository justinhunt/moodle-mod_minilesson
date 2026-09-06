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
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_minilesson\constants;
use mod_minilesson\utils;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/externallib.php');

/**
 * Resolves a TTS (Polly) audio URL for a piece of text, using the shared server side media
 * cache (minilesson_media_cache).
 *
 * This replaces the direct browser -> CloudPoodll request that amd/src/pollyhelper.js used to
 * make: routing it through here means client fetched URLs are cached and reused across taps,
 * page loads, users and item types (exactly as the server side rendered TTS already is), the
 * durable "pollyfile" CDN URL is captured once known, and the CloudPoodll credentials stay on
 * the server.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fetch_polly_url extends external_api {
    /** @var int Longest text we will synthesise in one call. */
    const MAX_TEXT_LENGTH = 2000;

    /**
     * Describes the parameters for the fetch_polly_url web service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'The course module id'),
            'text' => new external_value(PARAM_RAW, 'The text to read aloud'),
            'voiceoption' => new external_value(
                PARAM_INT,
                'TTS speed option: 0 normal, 1 slow, 2 very slow, 3 ssml',
                VALUE_DEFAULT,
                0
            ),
            'voice' => new external_value(PARAM_TEXT, 'The TTS voice name'),
        ]);
    }

    /**
     * Resolve the audio URL for the given text and voice.
     *
     * @param int $cmid The course module id.
     * @param string $text The text to read aloud.
     * @param int $voiceoption The TTS speed option.
     * @param string $voice The TTS voice name.
     * @return array [url => string] the audio URL, or an empty string when it could not be resolved.
     */
    public static function execute($cmid, $text, $voiceoption, $voice) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'text' => $text,
            'voiceoption' => $voiceoption,
            'voice' => $voice,
        ]);

        $cm = get_coursemodule_from_id(constants::M_MODNAME, $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        // The learner facing player needs :view; the speech tester page needs :manage.
        $allowed = has_capability('mod/minilesson:view', $context)
            || has_capability('mod/minilesson:manage', $context);
        if (!$allowed) {
            throw new \required_capability_exception($context, 'mod/minilesson:view', 'nopermissions', '');
        }

        $text = trim($params['text']);
        $voice = trim($params['voice']);
        if ($text === '' || $voice === '' || \core_text::strlen($text) > self::MAX_TEXT_LENGTH) {
            return ['url' => ''];
        }

        $moduleinstance = $DB->get_record(constants::M_TABLE, ['id' => $cm->instance], '*', MUST_EXIST);

        $config = get_config(constants::M_COMPONENT);
        $token = utils::fetch_token($config->apiuser, $config->apisecret);
        if (empty($token)) {
            return ['url' => ''];
        }

        // The utils::fetch_polly_url helper does the MUC + minilesson_media_cache read, the
        // CloudPoodll call on a miss, and persists the result when it is a "pollyfile" CDN URL.
        $url = utils::fetch_polly_url(
            $token,
            $moduleinstance->region,
            $text,
            (int) $params['voiceoption'],
            $voice,
            $moduleinstance->id
        );

        return ['url' => $url ?: ''];
    }

    /**
     * Describes the return value of the fetch_polly_url web service.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'url' => new external_value(PARAM_RAW, 'The audio URL, or empty if it could not be resolved'),
        ]);
    }
}
