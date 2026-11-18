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

namespace minilessonitem_audiochat;

use mod_minilesson\constants;

/**
 * Class helper
 *
 * @package    minilessonitem_audiochat
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /** @var string OpenAI's default API address. */
    const OPENAI_BASE = 'https://api.openai.com/v1';

    /** @var string OpenAI's Europe address. It only accepts keys from a project with Europe data residency. */
    const OPENAI_BASE_EU = 'https://eu.api.openai.com/v1';

    /** @var array Poodll regions whose audio chats go to OPENAI_BASE_EU. */
    const OPENAI_EU_REGIONS = ['dublin', 'frankfurt', 'london'];

    /**
     * The OpenAI API address for an activity in this Poodll region.
     *
     * @param string $region The activity's Poodll region, such as frankfurt.
     * @return string
     */
    public static function openai_base_url($region) {
        if (in_array($region, self::OPENAI_EU_REGIONS, true)) {
            return self::OPENAI_BASE_EU;
        }
        return self::OPENAI_BASE;
    }

    /**
     * The Poodll region of the lesson a context belongs to.
     *
     * @param int $contextid A MiniLesson activity's context id.
     * @return string The region, or '' if the context is not a MiniLesson activity.
     */
    public static function region_from_contextid($contextid) {
        global $DB;
        if (!$contextid) {
            return '';
        }
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context || $context->contextlevel != CONTEXT_MODULE) {
            return '';
        }
        $cm = get_coursemodule_from_id(constants::M_MODNAME, $context->instanceid);
        if (!$cm) {
            return '';
        }
        return (string)$DB->get_field(constants::M_TABLE, 'region', ['id' => $cm->instance]);
    }

    // We forward an OpenAI RTC offer to the OpenAI API.
    // This is called from: openairtc.php
    // Which is called from the OpenAI RTC client side code in audiochat.
    // (in which we dont want to expose our openai key)
    // It expects an SDP offer in the request body and returns an SDP answer.
    public static function openai_forward_offer($region = '') {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Get the secret from config.
        $apikey = get_config(constants::M_COMPONENT, 'openaikey');
        if (empty($apikey)) {
            return false;
        }

        $offer = file_get_contents("php://input");
        $model = "gpt-realtime-mini";
        $serverurl = self::openai_base_url($region) . "/realtime/calls";

        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . $apikey);
        // $curl->setHeader(['Content-type: application/sdp']);
        $result = $curl->post($serverurl, [
            'sdp' => $offer,
            'session' => json_encode([
                'type' => 'realtime',
                'model' => $model,
            ]),
        ]);
        header("Content-Type: application/sdp");
        echo $result;
        die;
    }

}
