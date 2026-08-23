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
 * OAuth 2.1 /token endpoint for the MCP authorization server.
 *
 * Server-to-server (no Moodle session involved): exchanges an authorization_code (with PKCE
 * verification) for an access token + refresh token, or rotates a refresh_token for a fresh
 * pair. The "access token" it hands out is simply a real aigenservice web service token
 * (see facade::mint_or_reuse_token()), so mcp.php/aigen_rest.php need no changes at all to
 * accept it.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_minilesson\local\aigen\facade;

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Server-to-server OAuth token endpoint; no Moodle session is involved.
require(__DIR__ . '/../../config.php');

/** @var int Outer absolute lifetime of a refresh token (reset on each rotation). */
const OAUTH_REFRESH_TTL = 90 * DAYSECS;

/**
 * Send an RFC 6749 token-endpoint error and stop.
 *
 * @param string $error
 * @param string $description
 * @return never
 */
function oauth_token_error(string $error, string $description) {
    header('Content-Type: application/json; charset=utf-8', true, 400);
    echo json_encode(['error' => $error, 'error_description' => $description], JSON_UNESCAPED_SLASHES);
    die;
}

/**
 * Resolve the client presenting the request and check its credentials if it is a
 * confidential (manual, client_secret_post) client. A client_id that looks like an https://
 * URL is a CIMD client - always public/PKCE-only by design, so there is nothing to look up.
 *
 * @param string $clientid
 * @param string|null $suppliedsecret
 * @return void
 */
function oauth_token_check_client(string $clientid, ?string $suppliedsecret): void {
    global $DB;

    if (preg_match('#^https://#i', $clientid)) {
        return;
    }

    $client = $DB->get_record('minilesson_oauth_clients', ['clientid' => $clientid]);
    if (!$client) {
        oauth_token_error('invalid_client', 'Unknown client_id');
    }
    if ($client->tokenendpointauthmethod === 'client_secret_post') {
        if ($suppliedsecret === null || !password_verify($suppliedsecret, (string) $client->clientsecrethash)) {
            oauth_token_error('invalid_client', 'Invalid client_secret');
        }
    }
}

/**
 * Mint the OAuth token pair for a user and send the success response.
 *
 * @param int $userid
 * @param string $clientid
 * @param string|null $resource
 * @param string|null $scope
 * @param string $familyid reuse the existing refresh-token family on rotation, or a fresh
 *        random value on first issuance
 * @return never
 */
function oauth_token_issue(int $userid, string $clientid, ?string $resource, ?string $scope, string $familyid) {
    global $DB;

    try {
        $accesstoken = facade::mint_or_reuse_token($userid);
    } catch (Throwable $e) {
        oauth_token_error('invalid_grant', 'The user is no longer permitted to use this service');
    }

    $tokenrecord = $DB->get_record('external_tokens', ['token' => $accesstoken]);
    $expiresin = (!empty($tokenrecord->validuntil)) ? max(0, $tokenrecord->validuntil - time()) : null;

    $rawrefresh = random_string(64);
    $DB->insert_record('minilesson_oauth_refresh', (object) [
        'tokenhash' => hash('sha256', $rawrefresh),
        'clientid' => $clientid,
        'userid' => $userid,
        'externalserviceid' => (int) $tokenrecord->externalserviceid,
        'resource' => $resource,
        'scope' => $scope,
        'familyid' => $familyid,
        'revoked' => 0,
        'expires' => time() + OAUTH_REFRESH_TTL,
        'timecreated' => time(),
        'lastused' => null,
    ]);

    $response = [
        'access_token' => $accesstoken,
        'token_type' => 'Bearer',
        'refresh_token' => $rawrefresh,
    ];
    if ($expiresin !== null) {
        $response['expires_in'] = $expiresin;
    }
    if ($scope !== null) {
        $response['scope'] = $scope;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    die;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    oauth_token_error('invalid_request', 'Use POST');
}

$granttype = required_param('grant_type', PARAM_ALPHAEXT);

if ($granttype === 'authorization_code') {
    $code = required_param('code', PARAM_RAW_TRIMMED);
    $redirecturi = required_param('redirect_uri', PARAM_URL);
    $clientid = required_param('client_id', PARAM_RAW_TRIMMED);
    $codeverifier = required_param('code_verifier', PARAM_RAW_TRIMMED);
    $clientsecret = optional_param('client_secret', null, PARAM_RAW_TRIMMED);
    $requestresource = optional_param('resource', null, PARAM_URL);

    $codehash = hash('sha256', $code);
    $row = $DB->get_record('minilesson_oauth_codes', ['codehash' => $codehash]);
    if ($row) {
        // Single-use: delete immediately on redemption, regardless of what happens next.
        $DB->delete_records('minilesson_oauth_codes', ['id' => $row->id]);
    }
    if (!$row || $row->expires < time()) {
        oauth_token_error('invalid_grant', 'The authorization code is invalid or has expired');
    }
    if ($row->clientid !== $clientid || $row->redirecturi !== $redirecturi) {
        oauth_token_error('invalid_grant', 'client_id or redirect_uri does not match the authorization request');
    }
    if ($row->resource !== null && $requestresource !== null && $requestresource !== $row->resource) {
        oauth_token_error('invalid_target', 'resource does not match the authorization request');
    }

    $expectedchallenge = rtrim(strtr(base64_encode(hash('sha256', $codeverifier, true)), '+/', '-_'), '=');
    if (!hash_equals($row->codechallenge, $expectedchallenge)) {
        oauth_token_error('invalid_grant', 'code_verifier does not match the code_challenge');
    }

    oauth_token_check_client($clientid, $clientsecret);
    oauth_token_issue((int) $row->userid, $clientid, $row->resource, $row->scope, random_string(32));
} else if ($granttype === 'refresh_token') {
    $refreshtoken = required_param('refresh_token', PARAM_RAW_TRIMMED);
    $clientid = required_param('client_id', PARAM_RAW_TRIMMED);
    $clientsecret = optional_param('client_secret', null, PARAM_RAW_TRIMMED);

    $tokenhash = hash('sha256', $refreshtoken);
    $row = $DB->get_record('minilesson_oauth_refresh', ['tokenhash' => $tokenhash]);
    if (!$row) {
        oauth_token_error('invalid_grant', 'Unknown refresh token');
    }
    if ($row->revoked) {
        // Reuse of an already-rotated-away token: treat as compromise, kill the whole chain.
        $DB->set_field('minilesson_oauth_refresh', 'revoked', 1, ['familyid' => $row->familyid]);
        oauth_token_error('invalid_grant', 'This refresh token has already been used');
    }
    if (!empty($row->expires) && $row->expires < time()) {
        oauth_token_error('invalid_grant', 'This refresh token has expired');
    }
    if ($row->clientid !== $clientid) {
        oauth_token_error('invalid_grant', 'client_id does not match this refresh token');
    }
    if (!has_capability('mod/minilesson:usemcp', context_system::instance(), (int) $row->userid)) {
        oauth_token_error('invalid_grant', 'The user is no longer permitted to use this service');
    }

    oauth_token_check_client($clientid, $clientsecret);

    $DB->set_field('minilesson_oauth_refresh', 'revoked', 1, ['id' => $row->id]);
    $DB->set_field('minilesson_oauth_refresh', 'lastused', time(), ['id' => $row->id]);
    oauth_token_issue((int) $row->userid, $clientid, $row->resource, $row->scope, $row->familyid);
} else {
    oauth_token_error('unsupported_grant_type', 'Only authorization_code and refresh_token are supported');
}
