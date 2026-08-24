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
 * .well-known forms RFC 8414 normally expects, so this script is reachable several ways:
 *  - {this script}/.well-known/openid-configuration via PATH_INFO (the "path-appended"
 *    discovery form, the same routing mechanism aigen_rest.php already uses in
 *    production) - reachable with no server config beyond what the plugin already needs.
 *  - Some real-world MCP clients (observed with Claude.ai) instead request paths like
 *    /.well-known/oauth-authorization-server (optionally with this script's own path
 *    appended again) at the site root, per RFC 8414's "insert" construction and the MCP
 *    spec's sequence diagram, rather than following the resource_metadata pointer in the
 *    WWW-Authenticate header as the spec text describes. Since Moodle owns the site root,
 *    catching any of these requires a server-config RewriteRule (documented in
 *    managemcp.php) pointing the site-root path at this script.
 * Since this script serves exactly one public, non-sensitive document, it does not gate on
 * how it was reached (e.g. via PATH_INFO) - observed variations in what PATH_INFO/REQUEST_URI
 * end up as under different rewrite targets are not worth chasing when there is nothing to
 * protect by rejecting an unexpected one. This script's own URL (without PATH_INFO) is the
 * "issuer" referenced everywhere else (oauth_resource_metadata.php, and every client's
 * discovery request).
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public, unauthenticated discovery metadata.
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
echo json_encode(
    \mod_minilesson\local\oauth\helper::authorization_server_metadata(),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
