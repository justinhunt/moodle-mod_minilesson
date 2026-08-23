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
}
