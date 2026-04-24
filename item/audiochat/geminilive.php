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

/**
 * Generate credentails for Gemini live API.
 *
 * @package mod_minilesson
 * @copyright  2014 Justin Hunt  {@link http://poodll.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */

use mod_minilesson\constants;

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__, 5) . '/config.php');
require_once($CFG->libdir . '/filelib.php');

$contextid = required_param('contextid', PARAM_INT);
$voice = optional_param('voice', 'Aoede', PARAM_ALPHANUMEXT);
$disablevad = optional_param('disablevad', false, PARAM_BOOL);

$context = context::instance_by_id($contextid);
$PAGE->set_context($context);

require_login();
require_sesskey();

$apikey = get_config(constants::M_COMPONENT, 'geminiapikey');
$model = 'gemini-3.1-flash-live-preview';
$now = time();
$expiretime = gmdate("Y-m-d\TH:i:s\Z", $now + 1800); // 30 mins from now
$payload = [
    'expireTime' => $expiretime,
    'uses' => 1,
    'bidiGenerateContentSetup' => [
        'model' => "models/{$model}",
        'generationConfig' => [
            'responseModalities' => ['AUDIO'],
            'temperature' => 0.7,
            'speechConfig' => [
                'voiceConfig' => [
                    'prebuiltVoiceConfig' => [
                        'voiceName' => $voice
                    ]
                ]
            ]
        ],
        'realtimeInputConfig' => [
            'automaticActivityDetection' => [
                'disabled' => $disablevad,
            ]
        ],
        'inputAudioTranscription' => new \stdClass(),
        'outputAudioTranscription' => new \stdClass(),
    ]
];

$curl = new curl();
$curl->setHeader([
    "x-goog-api-key: {$apikey}",
    "content-type: application/json"
]);
$response = $curl->post('https://generativelanguage.googleapis.com/v1alpha/auth_tokens', json_encode($payload));
$response = json_decode($response);

header('Content-Type: application/json');
echo json_encode([
    'ephemeralToken' => $response->name,
    'url' => 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained',
    'model' => $model,
]);
die;
