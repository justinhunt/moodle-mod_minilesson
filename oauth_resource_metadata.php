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
 * RFC 9728 OAuth 2.0 Protected Resource Metadata for the mcp.php/aigen_rest.php endpoints.
 *
 * A thin delegator to the shared local_oauthmcp authorization server plugin - see
 * mod_minilesson_mcp_oauth_resources() in lib.php for the resource declaration this reads
 * back. Kept as a file inside this plugin (rather than moving to local_oauthmcp) because
 * real MCP clients were found to request this document at a URL of the *resource's* own
 * choosing (via the resource_metadata parameter on mcp.php's WWW-Authenticate challenge),
 * not a fixed central path.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public, unauthenticated discovery metadata.
require(__DIR__ . '/../../config.php');

if (!class_exists('\local_oauthmcp\api')) {
    // No OAuth authorization server is available on this site - nothing to discover.
    http_response_code(404);
    die;
}

$data = \local_oauthmcp\api::resource_metadata($CFG->wwwroot . '/mod/minilesson/mcp.php');
if ($data === null) {
    // The local_oauthmcp plugin is installed but this resource is not (yet) visible to it -
    // e.g. caches not yet purged after enabling. Fail clearly rather than serving nothing.
    http_response_code(503);
    die;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
