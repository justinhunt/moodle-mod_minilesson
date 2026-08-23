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
 * RFC 8414 OAuth 2.0 Authorization Server Metadata for the MCP OAuth authorization server.
 *
 * A plugin living in a URL subdirectory (not a domain root) cannot serve the site-root
 * .well-known forms RFC 8414 normally expects. The one form it CAN serve is the
 * path-appended form, {issuer}/.well-known/openid-configuration, via PATH_INFO on this
 * script - the same routing mechanism aigen_rest.php already uses in production. This
 * script's own URL (without PATH_INFO) is therefore the "issuer" referenced everywhere
 * else (oauth_resource_metadata.php, and every client's discovery request).
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public, unauthenticated discovery metadata.
require(__DIR__ . '/../../config.php');

$pathinfo = $_SERVER['PATH_INFO'] ?? '';
if (rtrim($pathinfo, '/') !== '/.well-known/openid-configuration') {
    header('Content-Type: text/plain; charset=utf-8', true, 404);
    echo "Not found.\n";
    die;
}

$issuer = $CFG->wwwroot . '/mod/minilesson/oauth_metadata.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'issuer' => $issuer,
    'authorization_endpoint' => $CFG->wwwroot . '/mod/minilesson/oauth_authorize.php',
    'token_endpoint' => $CFG->wwwroot . '/mod/minilesson/oauth_token.php',
    'registration_endpoint' => $CFG->wwwroot . '/mod/minilesson/oauth_register.php',
    'response_types_supported' => ['code'],
    'grant_types_supported' => ['authorization_code', 'refresh_token'],
    'code_challenge_methods_supported' => ['S256'],
    'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
    'client_id_metadata_document_supported' => true,
    'scopes_supported' => ['aigen'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
