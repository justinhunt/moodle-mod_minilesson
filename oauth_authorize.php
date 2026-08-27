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
 * OAuth 2.1 /authorize endpoint for the MCP authorization server.
 *
 * Interactive: requires a logged-in Moodle user holding mod/minilesson:usemcp, shows a
 * consent screen naming the client, and on approval issues a short-lived single-use
 * authorization code (minilesson_oauth_codes). PKCE (S256) is mandatory - there is no
 * non-PKCE code flow. The DCR/manual client is resolved from minilesson_oauth_clients;
 * an https:// client_id (a Client ID Metadata Document reference) is resolved separately,
 * see oauth_cimd resolution once added.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_minilesson\constants;

require(__DIR__ . '/../../config.php');

/** @var int How long an authorization code is valid for, in seconds. */
const OAUTH_AUTHCODE_TTL = 60;

/**
 * Whether a redirect_uri presented at /authorize matches one the client registered:
 * exact match, or the RFC 8252 section 7.3 loopback exception (scheme+host+path exact,
 * any port) for native/CLI clients such as Claude Code.
 *
 * @param string $presented
 * @param string $registered
 * @return bool
 */
function oauth_authorize_redirect_uri_matches(string $presented, string $registered): bool {
    if ($presented === $registered) {
        return true;
    }
    $loopbackhosts = ['127.0.0.1', '::1', 'localhost'];
    $p = parse_url($presented);
    $r = parse_url($registered);
    if ($p === false || $r === false) {
        return false;
    }
    $samehostpath = ($p['scheme'] ?? '') === ($r['scheme'] ?? '')
        && ($p['host'] ?? '') === ($r['host'] ?? '')
        && ($p['path'] ?? '') === ($r['path'] ?? '');
    return $samehostpath && ($p['scheme'] ?? '') === 'http' && in_array($p['host'] ?? '', $loopbackhosts, true);
}

/** @var int Max bytes accepted for a fetched CIMD document. */
const OAUTH_CIMD_MAX_BYTES = 51200;

/**
 * Validate an already-fetched, already-decoded CIMD document against the URL it was
 * fetched from. Pure function (no I/O) so the validation rules are testable without a
 * network fetch.
 *
 * @param mixed $doc the json_decode()'d body, or null/non-array if decoding failed
 * @param string $url the URL the document was fetched from
 * @return array|null a client-shaped array (clientname, clienturi, redirecturis as a JSON
 *         string), or null if the document fails validation
 */
function oauth_authorize_validate_cimd_doc($doc, string $url): ?array {
    if (!is_array($doc)) {
        return null;
    }
    // The document's own client_id must echo the URL it was fetched from - the CIMD
    // anti-spoofing requirement.
    if (($doc['client_id'] ?? null) !== $url) {
        return null;
    }
    $redirecturis = $doc['redirect_uris'] ?? null;
    if (!is_array($redirecturis) || empty($redirecturis)) {
        return null;
    }
    // Accept the client if "none" is its stated method, or if it lists "none" among what
    // it supports - our token endpoint never asks for client authentication regardless of
    // what else a client is capable of, so a client offering "none" as an option is usable
    // here even when it prefers something stronger for other authorization servers.
    $authmethod = $doc['token_endpoint_auth_method'] ?? 'none';
    $supportedmethods = $doc['token_endpoint_auth_methods_supported'] ?? null;
    $acceptsnone = $authmethod === 'none'
        || (is_array($supportedmethods) && in_array('none', $supportedmethods, true));
    if (!$acceptsnone) {
        return null;
    }

    return [
        'clientname' => isset($doc['client_name']) ? clean_param((string) $doc['client_name'], PARAM_TEXT) : null,
        'clienturi' => isset($doc['client_uri']) ? clean_param((string) $doc['client_uri'], PARAM_URL) : null,
        'redirecturis' => json_encode(array_values($redirecturis)),
    ];
}

/**
 * Resolve a Client ID Metadata Document (CIMD): a client_id that is itself an https:// URL
 * to a small JSON document describing the client, letting Claude/Claude Code skip DCR
 * entirely. Fetched via the standard \curl class, which applies Moodle's curl_security_helper
 * blocklist automatically - this is fetching an arbitrary externally-supplied URL server-side,
 * so it is treated with the same suspicion as any other SSRF-adjacent fetch. Successful and
 * failed resolutions are both cached briefly to avoid refetching on every page view/retry.
 *
 * @param string $url
 * @return \stdClass|null a client-shaped object (clientname, clienturi, redirecturis as a
 *         JSON string, matching the minilesson_oauth_clients row shape), or null if the
 *         document could not be fetched or failed validation
 */
function oauth_authorize_resolve_cimd(string $url): ?\stdClass {
    global $CFG;

    $cache = \cache::make(\mod_minilesson\constants::M_COMPONENT, 'cimdclient');
    $cachekey = sha1($url);
    $cached = $cache->get($cachekey);
    if ($cached !== false) {
        return $cached === null ? null : (object) $cached;
    }

    require_once($CFG->libdir . '/filelib.php');
    $valid = false;
    $body = null;
    try {
        // Blocked hosts (private/local ranges, per $CFG->curlsecurityblockedhosts) are
        // rejected by the security helper \curl applies automatically; on sites that
        // promote debugging() notices to exceptions this can throw rather than just
        // returning an error, so treat any exception here the same as a failed fetch.
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 3,
            'CURLOPT_CONNECTTIMEOUT' => 3,
            'CURLOPT_MAXREDIRS' => 1,
            'CURLOPT_FOLLOWLOCATION' => 1,
        ]);
        $body = $curl->get($url);
        $info = $curl->get_info();
        $valid = !$curl->get_errno()
            && (int) ($info['http_code'] ?? 0) === 200
            && is_string($body)
            && strlen($body) <= OAUTH_CIMD_MAX_BYTES;
    } catch (\Throwable $e) {
        $valid = false;
    }

    $result = $valid ? oauth_authorize_validate_cimd_doc(json_decode($body, true), $url) : null;

    $cache->set($cachekey, $result);
    return $result === null ? null : (object) $result;
}

/**
 * Fail the request. If a verified redirect_uri is available, redirect back to the client
 * with an OAuth error per RFC 6749 4.1.2.1; otherwise (client_id/redirect_uri themselves
 * could not be trusted) render a plain error page - redirecting to an unverified URI would
 * be an open-redirect risk.
 *
 * @param string $error
 * @param string $description
 * @param string|null $redirecturi verified redirect_uri, or null to show an error page
 * @param string|null $state
 * @return never
 */
function oauth_authorize_fail(string $error, string $description, ?string $redirecturi, ?string $state) {
    global $OUTPUT;

    if ($redirecturi !== null) {
        $params = ['error' => $error, 'error_description' => $description];
        if ($state !== null) {
            $params['state'] = $state;
        }
        redirect(new moodle_url($redirecturi, $params));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification(s($description), 'notifyproblem');
    echo $OUTPUT->footer();
    die;
}

require_login(0, false);

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url(new moodle_url('/mod/minilesson/oauth_authorize.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('oauthauthorizetitle', constants::M_COMPONENT));

$renderer = $PAGE->get_renderer(constants::M_COMPONENT);

if (!has_capability('mod/minilesson:usemcp', $systemcontext)) {
    echo $OUTPUT->header();
    echo $renderer->render_oauthnotpermitted([]);
    echo $OUTPUT->footer();
    die;
}

$confirm = optional_param('confirm', '', PARAM_ALPHA);

if ($confirm !== '') {
    // The consent form POST - re-read everything from the hidden fields it carried.
    require_sesskey();
    $responsetype = required_param('response_type', PARAM_ALPHA);
    $clientid = required_param('client_id', PARAM_RAW_TRIMMED);
    $redirecturiparam = required_param('redirect_uri', PARAM_URL);
    $codechallenge = required_param('code_challenge', PARAM_RAW_TRIMMED);
    $codechallengemethod = required_param('code_challenge_method', PARAM_ALPHANUMEXT);
    $state = optional_param('state', null, PARAM_RAW_TRIMMED);
    $resource = optional_param('resource', null, PARAM_URL);
    $scope = optional_param('scope', null, PARAM_RAW_TRIMMED);
} else {
    $responsetype = optional_param('response_type', '', PARAM_ALPHA);
    $clientid = optional_param('client_id', '', PARAM_RAW_TRIMMED);
    $redirecturiparam = optional_param('redirect_uri', '', PARAM_URL);
    $codechallenge = optional_param('code_challenge', '', PARAM_RAW_TRIMMED);
    $codechallengemethod = optional_param('code_challenge_method', '', PARAM_ALPHANUMEXT);
    $state = optional_param('state', null, PARAM_RAW_TRIMMED);
    $resource = optional_param('resource', null, PARAM_URL);
    $scope = optional_param('scope', null, PARAM_RAW_TRIMMED);
}

if ($clientid === '' || $redirecturiparam === '') {
    oauth_authorize_fail('invalid_request', 'client_id and redirect_uri are required', null, null);
}

// Resolve the client: a DCR/manual row, or (if client_id is itself an https:// URL) a CIMD
// document fetched and validated on the fly - see oauth_authorize_resolve_cimd().
if (preg_match('#^https://#i', $clientid)) {
    $client = oauth_authorize_resolve_cimd($clientid);
} else {
    $client = $DB->get_record('minilesson_oauth_clients', ['clientid' => $clientid]);
}
if (!$client) {
    oauth_authorize_fail('invalid_client', 'Unknown client_id, or its CIMD document could not be resolved', null, null);
}

$registereduris = json_decode($client->redirecturis, true) ?: [];
$matched = false;
foreach ($registereduris as $registereduri) {
    if (oauth_authorize_redirect_uri_matches($redirecturiparam, $registereduri)) {
        $matched = true;
        break;
    }
}
if (!$matched) {
    oauth_authorize_fail('invalid_request', 'redirect_uri does not match this client\'s registered redirect URIs', null, null);
}

// From here on redirect_uri is trusted, so failures can redirect back to the client.
if ($responsetype !== 'code') {
    oauth_authorize_fail('unsupported_response_type', 'Only response_type=code is supported', $redirecturiparam, $state);
}
if ($codechallenge === '' || $codechallengemethod !== 'S256') {
    oauth_authorize_fail('invalid_request', 'PKCE with code_challenge_method=S256 is required', $redirecturiparam, $state);
}
$expectedresource = $CFG->wwwroot . '/mod/minilesson/mcp.php';
if ($resource !== null && $resource !== $expectedresource) {
    oauth_authorize_fail('invalid_target', 'resource must be ' . $expectedresource, $redirecturiparam, $state);
}

if ($confirm === 'approve') {
    $rawcode = random_string(64);
    $DB->insert_record('minilesson_oauth_codes', (object) [
        'codehash' => hash('sha256', $rawcode),
        'clientid' => $clientid,
        'userid' => $USER->id,
        'redirecturi' => $redirecturiparam,
        'codechallenge' => $codechallenge,
        'codechallengemethod' => $codechallengemethod,
        'resource' => $resource,
        'scope' => $scope,
        'clientnamesnapshot' => $client->clientname,
        'expires' => time() + OAUTH_AUTHCODE_TTL,
        'timecreated' => time(),
    ]);

    $params = ['code' => $rawcode, 'iss' => $CFG->wwwroot . '/mod/minilesson/oauth_metadata.php'];
    if ($state !== null) {
        $params['state'] = $state;
    }
    redirect(new moodle_url($redirecturiparam, $params));
} else if ($confirm === 'deny') {
    oauth_authorize_fail('access_denied', 'The user denied the request', $redirecturiparam, $state);
}

// GET (or an unrecognised confirm value): show the consent screen.
echo $OUTPUT->header();
echo $renderer->render_oauthconsent([
    'clientname' => $client->clientname ?: get_string('oauthunnamedclient', constants::M_COMPONENT),
    'clienturi' => $client->clienturi,
    'formurl' => (new moodle_url('/mod/minilesson/oauth_authorize.php'))->out(false),
    'sesskey' => sesskey(),
    'response_type' => $responsetype,
    'client_id' => $clientid,
    'redirect_uri' => $redirecturiparam,
    'code_challenge' => $codechallenge,
    'code_challenge_method' => $codechallengemethod,
    'state' => $state,
    'resource' => $resource,
    'scope' => $scope,
]);
echo $OUTPUT->footer();
