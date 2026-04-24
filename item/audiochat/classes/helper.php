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

    // We forward an OpenAI RTC offer to the OpenAI API.
    // This is called from: openairtc.php
    // Which is called from the OpenAI RTC client side code in audiochat.
    // (in which we dont want to expose our openai key)
    // It expects an SDP offer in the request body and returns an SDP answer.
    public static function openai_forward_offer() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Get the secret from config.
        $apikey = get_config(constants::M_COMPONENT, 'openaikey');
        if (empty($apikey)) {
            return false;
        }

        $offer = file_get_contents("php://input");
        $model = "gpt-4o-mini-realtime-preview";
        $serverurl = "https://api.openai.com/v1/realtime/calls";

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
