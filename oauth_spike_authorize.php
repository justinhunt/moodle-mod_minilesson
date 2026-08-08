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
 * THROWAWAY SPIKE - the human-facing half: login and consent.
 *
 * Separate from oauth_spike.php because this needs a Moodle session and that must not have
 * one. require_login() means the site's own authentication (SSO, MFA, whatever is
 * configured) does the work and the agent never sees a credential - which is the whole
 * argument for OAuth over handing tokens around.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/oauth_spike_lib.php');

use mod_minilesson\local\aigen\facade;

ml_spike_require_enabled();

$clientid = optional_param('client_id', '', PARAM_RAW_TRIMMED);
$redirecturi = optional_param('redirect_uri', '', PARAM_RAW_TRIMMED);
$responsetype = optional_param('response_type', '', PARAM_ALPHANUMEXT);
$scope = optional_param('scope', '', PARAM_RAW_TRIMMED);
$oauthstate = optional_param('state', '', PARAM_RAW_TRIMMED);
$challenge = optional_param('code_challenge', '', PARAM_RAW_TRIMMED);
$challengemethod = optional_param('code_challenge_method', '', PARAM_ALPHANUMEXT);
$resource = optional_param('resource', '', PARAM_RAW_TRIMMED);
$consent = optional_param('consent', '', PARAM_ALPHA);

// Log the authorization request before anything can reject it - this capture is the point
// of the spike, and a request we bounce is often the most informative one.
ml_spike_log('authorize_request', [
    'client_id' => $clientid,
    'redirect_uri' => $redirecturi,
    'response_type' => $responsetype,
    'scope' => $scope,
    'code_challenge_method' => $challengemethod,
    'has_code_challenge' => $challenge !== '',
    'has_state' => $oauthstate !== '',
    'resource' => $resource,
    'all_params' => array_diff_key($_GET, ['sesskey' => 1]),
]);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/mod/minilesson/oauth_spike_authorize.php'));
$PAGE->set_pagelayout('login');
$PAGE->set_title('Authorize application (spike)');

// The site's own login. Query parameters survive the round trip via wantsurl.
require_login();

/**
 * Fail without redirecting. Used only where the client identity or redirect target is
 * untrustworthy - bouncing an error to an unvalidated URI would make us an open redirector.
 *
 * @param string $reason
 * @return never
 */
function ml_spike_authorize_fail(string $reason) {
    global $OUTPUT, $PAGE;
    ml_spike_log('authorize_rejected', ['reason' => $reason]);
    echo $OUTPUT->header();
    echo $OUTPUT->notification($reason, \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->footer();
    die;
}

/**
 * Return to the client with an error, per RFC 6749 s4.1.2.1, including the RFC 9207 iss.
 *
 * @param string $redirecturi validated redirect target
 * @param string $error
 * @param string $description
 * @param string $oauthstate
 * @return never
 */
function ml_spike_authorize_error(string $redirecturi, string $error, string $description, string $oauthstate) {
    ml_spike_log('authorize_error_redirect', ['error' => $error, 'description' => $description]);
    $params = ['error' => $error, 'error_description' => $description, 'iss' => ml_spike_issuer()];
    if ($oauthstate !== '') {
        $params['state'] = $oauthstate;
    }
    $glue = strpos($redirecturi, '?') === false ? '?' : '&';
    header('Location: ' . $redirecturi . $glue . http_build_query($params));
    die;
}

// Resolve the client: a Client ID Metadata Document URL, or a dynamically registered client.
$client = ml_spike_resolve_client($clientid);
if ($client === null) {
    ml_spike_authorize_fail('Unknown client_id: ' . s($clientid)
        . '. It is neither a registered client nor a resolvable Client ID Metadata Document.');
}

$registeredredirects = $client['redirect_uris'] ?? [];
if (!ml_spike_redirect_uri_allowed($registeredredirects, $redirecturi)) {
    ml_spike_authorize_fail('redirect_uri "' . s($redirecturi) . '" is not registered for this client. '
        . 'Registered: ' . s(implode(', ', $registeredredirects)));
}

// From here the redirect target is trusted, so failures can be reported to the client.
if ($responsetype !== 'code') {
    ml_spike_authorize_error($redirecturi, 'unsupported_response_type', 'Only "code" is supported', $oauthstate);
}
if ($challenge === '' || $challengemethod !== 'S256') {
    ml_spike_authorize_error($redirecturi, 'invalid_request', 'PKCE with S256 is required', $oauthstate);
}

$granted = $scope !== '' ? $scope : 'aigen.read aigen.write';

// Informational only: the spike hands out a fixed token, so the allowed-users list is not
// enforced here. But whether the consenting user WOULD pass it is exactly what the real
// implementation has to gate on, so record it.
$service = facade::get_service();
$isallowed = false;
if ($service) {
    $isallowed = $DB->record_exists('external_services_users', [
        'externalserviceid' => $service->id,
        'userid' => $USER->id,
    ]);
}
ml_spike_log('authorize_user', [
    'userid' => $USER->id,
    'username' => $USER->username,
    'on_aigenservice_allowed_users' => $isallowed,
    'service_found' => (bool) $service,
]);

// The consent decision.
if ($consent !== '') {
    require_sesskey();

    if ($consent !== 'allow') {
        ml_spike_authorize_error($redirecturi, 'access_denied', 'The user declined', $oauthstate);
    }

    $code = ml_spike_random();
    ml_spike_state_update(function (array $state) use ($code, $clientid, $redirecturi, $challenge, $granted, $resource) {
        global $USER;
        $state['codes'][$code] = [
            'client_id' => $clientid,
            'userid' => $USER->id,
            'redirect_uri' => $redirecturi,
            'code_challenge' => $challenge,
            'scope' => $granted,
            'resource' => $resource,
            'expires' => time() + ML_SPIKE_CODE_TTL,
        ];
        return $state;
    });

    ml_spike_log('authorize_granted', [
        'client_id' => $clientid,
        'userid' => $USER->id,
        'scope' => $granted,
        'redirect_uri' => $redirecturi,
    ]);

    $params = ['code' => $code, 'iss' => ml_spike_issuer()];
    if ($oauthstate !== '') {
        $params['state'] = $oauthstate;
    }
    $glue = strpos($redirecturi, '?') === false ? '?' : '&';
    redirect(new moodle_url($redirecturi . $glue . http_build_query($params)));
}

// Show the consent screen. For a CIMD client the metadata is self-asserted, so the host of
// the client_id URL is the only trustworthy identifier - show that, not client_name.
$displayname = $client['client_name'] ?? '(unnamed application)';
$identity = ($client['source'] ?? '') === 'cimd'
    ? parse_url($clientid, PHP_URL_HOST)
    : $displayname;
$redirecthost = parse_url($redirecturi, PHP_URL_HOST);

echo $OUTPUT->header();
echo $OUTPUT->heading('Authorize application');

echo html_writer::start_div('card p-3 mb-3');
echo html_writer::tag('p', '<strong>' . s($identity) . '</strong> is asking to access '
    . 'MiniLesson AI generation on this site as <strong>' . s(fullname($USER)) . '</strong>.', ['class' => 'lead']);
echo html_writer::tag('p', 'Requested permissions: <code>' . s($granted) . '</code>');
echo html_writer::tag('p', 'You will be returned to: <code>' . s($redirecthost) . '</code>',
    ['class' => ml_spike_is_loopback($redirecturi) ? 'text-warning' : '']);
if (ml_spike_is_loopback($redirecturi)) {
    echo $OUTPUT->notification('This application runs on your own computer. Only continue if you started it yourself.',
        \core\output\notification::NOTIFY_WARNING);
}
if (!$isallowed) {
    echo $OUTPUT->notification('Note: your account is not on the AI Generation Service allowed-users list. '
        . 'The spike ignores this, but the real implementation would stop here.',
        \core\output\notification::NOTIFY_INFO);
}
echo html_writer::end_div();

// Carry every parameter through the consent POST unchanged.
$hidden = [
    'client_id' => $clientid,
    'redirect_uri' => $redirecturi,
    'response_type' => $responsetype,
    'scope' => $scope,
    'state' => $oauthstate,
    'code_challenge' => $challenge,
    'code_challenge_method' => $challengemethod,
    'resource' => $resource,
    'sesskey' => sesskey(),
];

foreach (['allow' => 'Allow', 'deny' => 'Deny'] as $value => $label) {
    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => $PAGE->url->out(false),
        'style' => 'display:inline-block;margin-right:.5rem',
    ]);
    foreach ($hidden + ['consent' => $value] as $name => $val) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $val]);
    }
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'value' => $label,
        'class' => $value === 'allow' ? 'btn btn-primary' : 'btn btn-secondary',
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->footer();
