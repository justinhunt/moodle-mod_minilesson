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
 * Unlike authorization server metadata (RFC 8414), RFC 9728 does not require a fixed
 * .well-known path - a resource server may point at this document from any URL via the
 * resource_metadata parameter on its WWW-Authenticate challenge, which is exactly what
 * mcp.php and aigen_rest.php do on a 401. So this file is a plain, arbitrarily-named
 * script rather than something routed via PATH_INFO like oauth_metadata.php.
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
    \mod_minilesson\local\oauth\helper::resource_metadata(),
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
