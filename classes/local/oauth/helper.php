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

namespace mod_minilesson\local\oauth;

/**
 * Small pure-logic helpers shared by the OAuth authorization server front-ends
 * (oauth_register.php, oauth_authorize.php, manageoauthclients.php), so the redirect_uri
 * acceptance rule can't silently drift between where clients are created and where they
 * are used.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Whether a redirect_uri is acceptable: https, or the RFC 8252 section 7.3 loopback
     * exception (http, host 127.0.0.1/::1/localhost, any port) for native/CLI clients.
     *
     * @param string $uri
     * @return bool
     */
    public static function valid_redirect_uri(string $uri): bool {
        $parts = parse_url($uri);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if ($parts['scheme'] === 'https') {
            return true;
        }
        $loopbackhosts = ['127.0.0.1', '::1', 'localhost'];
        return $parts['scheme'] === 'http' && in_array($parts['host'], $loopbackhosts, true);
    }

    /**
     * RFC 9728 protected resource metadata for mcp.php/aigen_rest.php - shared by
     * oauth_resource_metadata.php and the PATH_INFO discovery branch in mcp.php/aigen_rest.php
     * so the two never drift apart.
     *
     * @return array
     */
    public static function resource_metadata(): array {
        global $CFG;
        return [
            'resource' => $CFG->wwwroot . '/mod/minilesson/mcp.php',
            'authorization_servers' => [$CFG->wwwroot . '/mod/minilesson/oauth_metadata.php'],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => ['aigen'],
        ];
    }

    /**
     * RFC 8414 authorization server metadata - shared by oauth_metadata.php and the
     * PATH_INFO discovery branch in mcp.php/aigen_rest.php so the two never drift apart.
     *
     * @return array
     */
    public static function authorization_server_metadata(): array {
        global $CFG;
        $issuer = $CFG->wwwroot . '/mod/minilesson/oauth_metadata.php';
        return [
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
        ];
    }
}
