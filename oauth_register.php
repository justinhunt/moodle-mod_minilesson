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
 * RFC 7591 OAuth 2.0 Dynamic Client Registration for the MCP OAuth authorization server.
 *
 * Open and unauthenticated by design - this endpoint only ever creates PUBLIC clients
 * (token_endpoint_auth_method "none", no secret), so the security boundary is entirely at
 * the /authorize consent step, not here. A registered-but-never-consented-to client can do
 * nothing. Confidential clients (with a secret) can only be created by an admin, via
 * manageoauthclients.php.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Open DCR endpoint per RFC 7591; the security boundary is /authorize.
require(__DIR__ . '/../../config.php');

/** @var int Maximum registrations accepted from one IP per hour. */
const OAUTH_REGISTER_RATE_LIMIT = 30;

/**
 * Send a JSON error response and stop.
 *
 * @param int $status
 * @param string $error an RFC 7591/6749 error code
 * @param string $description
 * @return never
 */
function oauth_register_error(int $status, string $error, string $description) {
    header('Content-Type: application/json; charset=utf-8', true, $status);
    echo json_encode(['error' => $error, 'error_description' => $description], JSON_UNESCAPED_SLASHES);
    die;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    oauth_register_error(405, 'invalid_request', 'Use POST with a JSON body');
}

// Lightweight, best-effort abuse guard - not a hard security boundary.
$ratelimitcache = cache::make(\mod_minilesson\constants::M_COMPONENT, 'dcrratelimit');
$ratelimitkey = 'ip_' . sha1(getremoteaddr());
$attempts = (int) ($ratelimitcache->get($ratelimitkey) ?: 0);
if ($attempts >= OAUTH_REGISTER_RATE_LIMIT) {
    oauth_register_error(429, 'invalid_request', 'Too many registration attempts, try again later');
}
$ratelimitcache->set($ratelimitkey, $attempts + 1);

$raw = file_get_contents('php://input');
$metadata = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($metadata)) {
    oauth_register_error(400, 'invalid_client_metadata', 'Request body must be a JSON object');
}

$redirecturis = $metadata['redirect_uris'] ?? null;
if (!is_array($redirecturis) || empty($redirecturis)) {
    oauth_register_error(400, 'invalid_client_metadata', 'redirect_uris is required and must be a non-empty array');
}
foreach ($redirecturis as $uri) {
    if (!is_string($uri) || !\mod_minilesson\local\oauth\helper::valid_redirect_uri($uri)) {
        oauth_register_error(400, 'invalid_redirect_uri', 'Each redirect_uri must be https, or a loopback http URI');
    }
}

$authmethod = $metadata['token_endpoint_auth_method'] ?? 'none';
if ($authmethod !== 'none') {
    oauth_register_error(
        400,
        'invalid_client_metadata',
        'This endpoint only registers public clients; token_endpoint_auth_method must be "none"'
    );
}

$granttypes = $metadata['grant_types'] ?? ['authorization_code', 'refresh_token'];
if (!is_array($granttypes) || array_diff($granttypes, ['authorization_code', 'refresh_token'])) {
    oauth_register_error(400, 'invalid_client_metadata', 'grant_types may only contain authorization_code and refresh_token');
}

$clientname = isset($metadata['client_name']) ? clean_param((string) $metadata['client_name'], PARAM_TEXT) : null;
$clienturi = isset($metadata['client_uri']) ? clean_param((string) $metadata['client_uri'], PARAM_URL) : null;
$logouri = isset($metadata['logo_uri']) ? clean_param((string) $metadata['logo_uri'], PARAM_URL) : null;
$scope = isset($metadata['scope']) ? clean_param((string) $metadata['scope'], PARAM_TEXT) : null;

$now = time();
do {
    $clientid = 'mlc_' . random_string(32);
} while ($DB->record_exists('minilesson_oauth_clients', ['clientid' => $clientid]));

$record = (object) [
    'clientid' => $clientid,
    'clientsecrethash' => null,
    'clientname' => $clientname,
    'clienturi' => $clienturi,
    'logouri' => $logouri,
    'redirecturis' => json_encode(array_values($redirecturis)),
    'granttypes' => implode(',', $granttypes),
    'responsetypes' => 'code',
    'tokenendpointauthmethod' => 'none',
    'scope' => $scope,
    'origin' => 'dcr',
    'createdby' => null,
    'timecreated' => $now,
    'timemodified' => $now,
    'lastusedtime' => null,
];
$DB->insert_record('minilesson_oauth_clients', $record);

header('Content-Type: application/json; charset=utf-8', true, 201);
echo json_encode([
    'client_id' => $clientid,
    'client_id_issued_at' => $now,
    'redirect_uris' => array_values($redirecturis),
    'token_endpoint_auth_method' => 'none',
    'grant_types' => $granttypes,
    'response_types' => ['code'],
    'client_name' => $clientname,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
