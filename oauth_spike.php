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
 * THROWAWAY SPIKE - the machine-facing half of an OAuth authorization server.
 *
 * Routed by PATH_INFO, deliberately: the whole point of the spike is to find out whether
 * real MCP clients will reach discovery documents at a path a Moodle plugin can actually
 * serve. Routes:
 *
 *   GET  /.well-known/openid-configuration          <- OIDC path-appended (the one that matters)
 *   GET  /.well-known/oauth-authorization-server    <- path-appended variant, some clients try it
 *   GET  /.well-known/oauth-protected-resource      <- pointed at by mcp.php's 401 challenge
 *   POST /register                                  <- RFC 7591 dynamic client registration
 *   POST /token                                     <- authorization_code + refresh_token grants
 *
 * The consent screen lives in oauth_spike_authorize.php, because it needs a Moodle session
 * and this script must not have one.
 *
 * See oauth_spike_lib.php for how to enable, and oauth_spike_README.md for the runbook.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public OAuth discovery/token endpoints; auth is per-grant.
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/oauth_spike_lib.php');

ml_spike_require_enabled();

$route = trim($_SERVER['PATH_INFO'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Some clients preflight the discovery documents from a browser context.
if ($method === 'OPTIONS') {
    ml_spike_log('preflight', ['route' => $route]);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    http_response_code(204);
    die;
}

switch ($route) {
    // ---------------------------------------------------------------- discovery documents.
    case '.well-known/openid-configuration':
    case '.well-known/oauth-authorization-server':
        ml_spike_log('discovery_as', ['route' => $route]);
        ml_spike_json(ml_spike_as_document());
        break;

    case '.well-known/oauth-protected-resource':
        ml_spike_log('discovery_prm', ['route' => $route]);
        ml_spike_json(ml_spike_prm_document());
        break;

    // ------------------------------------------------------- dynamic client registration.
    case 'register':
        ml_spike_register();
        break;

    // ------------------------------------------------------------------ token issuance.
    case 'token':
        ml_spike_token();
        break;

    default:
        ml_spike_log('unknown_route', ['route' => $route]);
        ml_spike_json(['error' => 'not_found', 'error_description' => 'No such endpoint: ' . $route], 404);
}

/**
 * RFC 7591 dynamic client registration. Accepts whatever the client sends and logs all of
 * it - this is how we learn each agent's redirect_uri and how it identifies itself.
 *
 * @return never
 */
function ml_spike_register() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        ml_spike_oauth_error('invalid_request', 'Registration is POST only', 405);
    }

    // RFC 7591 s3.1: registration requests are JSON, unlike the form-encoded token endpoint.
    $raw = (string) file_get_contents('php://input');
    $metadata = json_decode($raw, true);
    ml_spike_log('register_request', ['raw' => $raw, 'parsed' => $metadata]);

    if (!is_array($metadata)) {
        ml_spike_oauth_error('invalid_client_metadata', 'Body must be a JSON object');
    }

    $redirecturis = $metadata['redirect_uris'] ?? [];
    if (empty($redirecturis) || !is_array($redirecturis)) {
        ml_spike_oauth_error('invalid_redirect_uri', 'redirect_uris is required');
    }

    $clientid = 'spike-' . ml_spike_random(16);
    $authmethod = $metadata['token_endpoint_auth_method'] ?? 'none';

    $client = [
        'client_id' => $clientid,
        'client_name' => $metadata['client_name'] ?? '(unnamed)',
        'redirect_uris' => array_values($redirecturis),
        'grant_types' => $metadata['grant_types'] ?? ['authorization_code', 'refresh_token'],
        'token_endpoint_auth_method' => $authmethod,
        'source' => 'dcr',
        'registered' => time(),
        'raw_metadata' => $metadata,
    ];

    // A confidential client gets a secret; a public one (the MCP norm) does not.
    $response = $client;
    if ($authmethod !== 'none') {
        $secret = ml_spike_random(32);
        $client['client_secret'] = $secret;
        $response['client_secret'] = $secret;
        $response['client_secret_expires_at'] = 0;
    }
    unset($response['raw_metadata'], $response['source'], $response['registered']);
    $response['client_id_issued_at'] = time();

    ml_spike_state_update(function (array $state) use ($clientid, $client) {
        $state['clients'][$clientid] = $client;
        return $state;
    });

    ml_spike_log('register_issued', ['client_id' => $clientid, 'auth_method' => $authmethod]);
    ml_spike_json($response, 201);
}

/**
 * The token endpoint: authorization_code and refresh_token grants.
 *
 * The access token handed back is the pre-existing web service token from config.php. The
 * spike issues no credentials of its own, so there is nothing here to leak or clean up
 * beyond the config line.
 *
 * @return never
 */
function ml_spike_token() {
    global $CFG;

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        ml_spike_oauth_error('invalid_request', 'Token endpoint is POST only', 405);
    }

    // RFC 6749 s4.1.3: form-urlencoded, not JSON. Log the raw body in case a client differs.
    $raw = (string) file_get_contents('php://input');
    $params = $_POST;
    if (empty($params) && $raw !== '') {
        parse_str($raw, $params);
    }

    $loggable = $params;
    foreach (['client_secret', 'code', 'refresh_token', 'code_verifier'] as $secret) {
        if (isset($loggable[$secret])) {
            $loggable[$secret] = '[present, ' . strlen((string) $loggable[$secret]) . ' chars]';
        }
    }
    ml_spike_log('token_request', [
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '(none)',
        'params' => $loggable,
        'raw_was_parsed' => empty($_POST) && $raw !== '',
    ]);

    $granttype = $params['grant_type'] ?? '';

    if ($granttype === 'refresh_token') {
        $presented = $params['refresh_token'] ?? '';
        $state = ml_spike_state_read();
        if (!isset($state['refresh'][$presented])) {
            // Must be invalid_grant specifically, or clients will not re-run the auth flow.
            ml_spike_oauth_error('invalid_grant', 'Unknown or already-rotated refresh token');
        }
        $grant = $state['refresh'][$presented];

        // Public clients require rotation: the old token dies as the new one is issued.
        $new = ml_spike_random();
        ml_spike_state_update(function (array $state) use ($presented, $new, $grant) {
            unset($state['refresh'][$presented]);
            $state['refresh'][$new] = $grant;
            return $state;
        });

        ml_spike_log('token_refreshed', ['client_id' => $grant['client_id'], 'scope' => $grant['scope']]);
        ml_spike_json([
            'access_token' => $CFG->minilesson_oauth_spike_token,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $new,
            'scope' => $grant['scope'],
        ]);
    }

    if ($granttype !== 'authorization_code') {
        ml_spike_oauth_error('unsupported_grant_type', 'Got: ' . $granttype);
    }

    $code = $params['code'] ?? '';
    $state = ml_spike_state_read();
    if (!isset($state['codes'][$code])) {
        ml_spike_oauth_error('invalid_grant', 'Unknown, expired or already-used authorization code');
    }
    $grant = $state['codes'][$code];

    // Single use: burn the code before doing anything else with it.
    ml_spike_state_update(function (array $state) use ($code) {
        unset($state['codes'][$code]);
        return $state;
    });

    if (($grant['expires'] ?? 0) < time()) {
        ml_spike_oauth_error('invalid_grant', 'Authorization code expired');
    }

    // The redirect_uri must match the one the code was issued against.
    $redirecturi = $params['redirect_uri'] ?? '';
    if ($redirecturi !== '' && $redirecturi !== $grant['redirect_uri']) {
        ml_spike_log('token_redirect_mismatch', [
            'issued_for' => $grant['redirect_uri'],
            'presented' => $redirecturi,
        ]);
        ml_spike_oauth_error('invalid_grant', 'redirect_uri does not match the authorization request');
    }

    // PKCE S256. Mandatory for every client, per OAuth 2.1.
    $verifier = (string) ($params['code_verifier'] ?? '');
    if ($verifier === '') {
        ml_spike_oauth_error('invalid_request', 'code_verifier is required');
    }
    $expected = ml_spike_b64url(hash('sha256', $verifier, true));
    if (!hash_equals($grant['code_challenge'], $expected)) {
        ml_spike_log('pkce_failed', ['challenge' => $grant['code_challenge'], 'derived' => $expected]);
        ml_spike_oauth_error('invalid_grant', 'PKCE verification failed');
    }

    $refresh = ml_spike_random();
    ml_spike_state_update(function (array $state) use ($refresh, $grant) {
        $state['refresh'][$refresh] = [
            'client_id' => $grant['client_id'],
            'userid' => $grant['userid'],
            'scope' => $grant['scope'],
            'resource' => $grant['resource'],
        ];
        return $state;
    });

    ml_spike_log('token_issued', [
        'client_id' => $grant['client_id'],
        'userid' => $grant['userid'],
        'scope' => $grant['scope'],
        'resource_requested' => $grant['resource'],
    ]);

    ml_spike_json([
        'access_token' => $CFG->minilesson_oauth_spike_token,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'refresh_token' => $refresh,
        'scope' => $grant['scope'],
    ]);
}
