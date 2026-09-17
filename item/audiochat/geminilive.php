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
use minilessonitem_audiochat\itemtype;

define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__, 5) . '/config.php');
require_once($CFG->libdir . '/filelib.php');

$contextid = required_param('contextid', PARAM_INT);
$voice = optional_param('voice', 'Aoede', PARAM_ALPHANUMEXT);
// True when the item has Auto Send off, ie the student decides when the turn is
// sent rather than the model replying as soon as they stop speaking.
$disablevad = optional_param('disablevad', false, PARAM_BOOL);
// Opaque session-resumption handle (from a previous connection's
// sessionResumptionUpdate). Sent only when reconnecting to resume the session.
$resumehandle = optional_param('resumehandle', '', PARAM_RAW_TRIMMED);

// Silence, in milliseconds, before the server VAD commits end-of-speech in hybrid
// turn mode. Deliberately far longer than any pause a student would leave, so the
// turn is only ever finalised by the audioStreamEnd the client sends when the
// student releases the mic. See the hybrid mode note below.
define('MINILESSON_AUDIOCHAT_HYBRID_SILENCE_MS', 60000);

// Silence, in milliseconds, before the server VAD commits end-of-speech in auto turn
// mode, ie how long a student may pause before the model takes its turn. Admin
// configurable, and shared with the OpenAI driver so a lesson paces the same way
// whichever provider is behind it. Note the API documents no default of its own for
// this field, so leaving it out does not mean "3.5 seconds", it means whatever Google
// happens to use.
$autosilencems = itemtype::get_silenceduration();

// Auto Send off uses hybrid turn mode: the VAD stays on so it still decides whether
// speech happened at all, but its silence window is pushed out of reach so the turn
// is finalised by the audioStreamEnd the client sends when the student releases the
// mic. Fully disabling the VAD instead would make the client delimit turns by hand,
// and a hand-delimited turn holding only silence makes the model hallucinate a
// transcript for it.
$turnmode = $disablevad ? 'hybrid' : 'auto';

$context = context::instance_by_id($contextid);
$PAGE->set_context($context);

require_login();
require_sesskey();

// Based on provider we fetch the token in a different way.
// For cloudpoodll we get it from our cloud poodll server.
$provider = get_config(constants::M_COMPONENT, 'provider');
if ($provider == itemtype::PROVIDER_CLOUDPOODLL) {
    $jsontoken = \mod_minilesson\utils::fetch_cloudpoodll_audiochat_token(
        $contextid,
        $voice,
        $disablevad,
        $resumehandle,
        $autosilencems,
        $turnmode
    );
    if ($jsontoken) {
        header('Content-Type: application/json');
        echo $jsontoken;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Could not fetch token from CloudPoodll']);
    }
    die;
}

// Otherwise, hopefully we have a Gemini API key and can generate our own token
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
                        'voiceName' => $voice,
                    ],
                ],
            ],
        ],
        // See the turn mode note above. Hybrid keeps the VAD on and pushes its silence
        // window out of reach; auto lets it end the turn after the configured pause.
        'realtimeInputConfig' => [
            'automaticActivityDetection' => [
                'disabled' => false,
                'silenceDurationMs' => $turnmode === 'hybrid'
                    ? MINILESSON_AUDIOCHAT_HYBRID_SILENCE_MS
                    : $autosilencems,
            ],
        ],
        // Allow the session to outlive a single connection (resumption) and the
        // raw context window (sliding-window compression), so long conversations
        // do not stall at the ~10 minute connection limit. On a reconnect the
        // resume handle is baked in here so the Constrained endpoint restores the
        // prior conversation rather than starting fresh.
        'sessionResumption' => $resumehandle !== ''
            ? ['handle' => $resumehandle]
            : new \stdClass(),
        'contextWindowCompression' => [
            'slidingWindow' => new \stdClass(),
        ],
        'inputAudioTranscription' => new \stdClass(),
        'outputAudioTranscription' => new \stdClass(),
    ],
];

$curl = new curl();
$curl->setHeader([
    "x-goog-api-key: {$apikey}",
    "content-type: application/json",
]);
$response = $curl->post('https://generativelanguage.googleapis.com/v1alpha/auth_tokens', json_encode($payload));
$response = json_decode($response);

header('Content-Type: application/json');
// The turn mode actually baked into the token. The Constrained endpoint honours the
// token's setup rather than anything we add over the wire, so the client must match
// its turn signalling to this. A provider that does not report one (CloudPoodll)
// leaves the client on its manual default.
echo json_encode([
    'ephemeralToken' => $response->name,
    'url' => 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained',
    'model' => $model,
    'turnmode' => $turnmode,
]);
die;
