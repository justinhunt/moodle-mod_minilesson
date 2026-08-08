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
 * THROWAWAY SPIKE - shared helpers for the OAuth authorization-server spike.
 *
 * This is not production code and must be deleted once the spike has answered its
 * questions. It exists to find out, against real MCP clients (Claude, Gemini Spark):
 *
 *   1. Does a client reach our authorization-server metadata at the only URL a Moodle
 *      plugin can serve - the OIDC path-appended one, via PATH_INFO?
 *   2. Is a non-root "resource_metadata" pointer in the 401 honoured?
 *   3. What redirect_uri does each client use, and does it register dynamically?
 *   4. Does the whole authorization-code + PKCE round trip complete?
 *
 * Everything is logged to dataroot so the answers can be read off afterwards
 * (see oauth_spike_log.php).
 *
 * Enable by adding to config.php:
 *     $CFG->minilesson_oauth_spike = true;
 *     $CFG->minilesson_oauth_spike_token = '<an existing aigenservice web service token>';
 *
 * The spike never mints a token: it hands out that one, so there is no new credential
 * machinery to get wrong, and revoking the spike is just deleting the config lines.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** @var int How long an authorization code is valid, in seconds. */
const ML_SPIKE_CODE_TTL = 300;

/**
 * Whether the spike is switched on and has a token to hand out.
 *
 * @return bool
 */
function ml_spike_enabled(): bool {
    global $CFG;
    return !empty($CFG->minilesson_oauth_spike) && !empty($CFG->minilesson_oauth_spike_token);
}

/**
 * Stop with a plain 404 when the spike is off, so the endpoints are invisible in normal operation.
 *
 * @return void
 */
function ml_spike_require_enabled() {
    if (!ml_spike_enabled()) {
        header('Content-Type: text/plain; charset=utf-8', true, 404);
        echo "Not found.\n";
        die;
    }
}

/**
 * The directory the spike writes its log and state to.
 *
 * @return string
 */
function ml_spike_dir(): string {
    global $CFG;
    $dir = $CFG->dataroot . '/minilesson_oauth_spike';
    if (!is_dir($dir)) {
        mkdir($dir, $CFG->directorypermissions ?? 0777, true);
    }
    return $dir;
}

/**
 * Base64url encode (no padding), as used by PKCE and by our opaque identifiers.
 *
 * @param string $binary
 * @return string
 */
function ml_spike_b64url(string $binary): string {
    return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
}

/**
 * A random opaque identifier.
 *
 * @param int $bytes
 * @return string
 */
function ml_spike_random(int $bytes = 32): string {
    return ml_spike_b64url(random_bytes($bytes));
}

/**
 * Every inbound HTTP header, for the log. This is how we find out what each client
 * actually sends (accept types, user agent, auth scheme).
 *
 * @return array
 */
function ml_spike_request_headers(): array {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$name] = $value;
        }
    }
    // Never write a whole credential to disk; enough to correlate, not enough to replay.
    foreach (['Authorization', 'X-Api-Key'] as $sensitive) {
        if (isset($headers[$sensitive])) {
            $headers[$sensitive] = substr($headers[$sensitive], 0, 16) . '...[redacted]';
        }
    }
    return $headers;
}

/**
 * Append one JSON line to the spike log.
 *
 * @param string $event short event name, e.g. 'discovery', 'register', 'token'
 * @param array $data anything worth reading back later
 * @return void
 */
function ml_spike_log(string $event, array $data = []) {
    $entry = [
        'time' => date('c'),
        'event' => $event,
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'pathinfo' => $_SERVER['PATH_INFO'] ?? '(none)',
        'ip' => getremoteaddr(),
        'headers' => ml_spike_request_headers(),
        'data' => $data,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    file_put_contents(ml_spike_dir() . '/spike.log', $line, FILE_APPEND | LOCK_EX);
}

/**
 * Read the spike's state (registered clients, live codes, refresh tokens).
 *
 * @return array
 */
function ml_spike_state_read(): array {
    $file = ml_spike_dir() . '/state.json';
    if (!file_exists($file)) {
        return ['clients' => [], 'codes' => [], 'refresh' => []];
    }
    $state = json_decode(file_get_contents($file), true);
    if (!is_array($state)) {
        return ['clients' => [], 'codes' => [], 'refresh' => []];
    }
    $state += ['clients' => [], 'codes' => [], 'refresh' => []];
    return $state;
}

/**
 * Mutate the spike's state under an exclusive lock.
 *
 * @param callable $mutator receives the state array by value, returns the new state
 * @return array the new state
 */
function ml_spike_state_update(callable $mutator): array {
    $file = ml_spike_dir() . '/state.json';
    $handle = fopen($file, 'c+');
    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = json_decode($raw, true);
    if (!is_array($state)) {
        $state = [];
    }
    $state += ['clients' => [], 'codes' => [], 'refresh' => []];

    // Drop expired codes on every write so the file cannot grow without bound.
    foreach ($state['codes'] as $code => $info) {
        if (($info['expires'] ?? 0) < time()) {
            unset($state['codes'][$code]);
        }
    }

    $state = $mutator($state);

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state;
}

/**
 * The issuer identifier. Deliberately the script URL, because the only authorization-server
 * metadata URL a plugin in a subdirectory can serve itself is the OIDC path-appended form
 * ({issuer}/.well-known/openid-configuration), which lands here as PATH_INFO. Whether real
 * clients fall back to that URL is the main question the spike exists to answer.
 *
 * @return string
 */
function ml_spike_issuer(): string {
    global $CFG;
    return $CFG->wwwroot . '/mod/minilesson/oauth_spike.php';
}

/**
 * The canonical URI of the protected resource (the MCP endpoint). Must match what the user
 * types into the client exactly, or Claude rejects the protected resource metadata.
 *
 * @return string
 */
function ml_spike_resource(): string {
    global $CFG;
    return $CFG->wwwroot . '/mod/minilesson/mcp.php';
}

/**
 * The URL of the protected resource metadata document, which the 401 challenge points at.
 *
 * @return string
 */
function ml_spike_prm_url(): string {
    return ml_spike_issuer() . '/.well-known/oauth-protected-resource';
}

/**
 * RFC 9728 protected resource metadata.
 *
 * @return array
 */
function ml_spike_prm_document(): array {
    return [
        'resource' => ml_spike_resource(),
        'authorization_servers' => [ml_spike_issuer()],
        'bearer_methods_supported' => ['header'],
        'scopes_supported' => ['aigen.read', 'aigen.write'],
    ];
}

/**
 * RFC 8414 / OIDC authorization server metadata.
 *
 * The advertised capabilities are the ones the real implementation intends to support, so
 * that what the spike observes is what production will see: public clients (CIMD and DCR)
 * plus confidential ones (Gemini Enterprise and ChatGPT need a pre-registered secret).
 *
 * @return array
 */
function ml_spike_as_document(): array {
    global $CFG;
    return [
        'issuer' => ml_spike_issuer(),
        'authorization_endpoint' => $CFG->wwwroot . '/mod/minilesson/oauth_spike_authorize.php',
        'token_endpoint' => ml_spike_issuer() . '/token',
        'registration_endpoint' => ml_spike_issuer() . '/register',
        'scopes_supported' => ['aigen.read', 'aigen.write', 'offline_access'],
        'response_types_supported' => ['code'],
        'response_modes_supported' => ['query'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        // 'none' is required for Claude to select CIMD; the secret methods are for
        // pre-registered confidential clients.
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
        'code_challenge_methods_supported' => ['S256'],
        'client_id_metadata_document_supported' => true,
        'authorization_response_iss_parameter_supported' => true,
    ];
}

/**
 * Send a JSON document and stop. Discovery documents are fetched cross-origin by some
 * clients, so they are readable by anyone - they contain no secrets.
 *
 * @param array $payload
 * @param int $status
 * @return never
 */
function ml_spike_json(array $payload, int $status = 200) {
    header('Content-Type: application/json; charset=utf-8', true, $status);
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    die;
}

/**
 * An OAuth error response (RFC 6749 shape - clients key off these exact codes).
 *
 * @param string $error
 * @param string $description
 * @param int $status
 * @return never
 */
function ml_spike_oauth_error(string $error, string $description = '', int $status = 400) {
    ml_spike_log('error_response', ['error' => $error, 'description' => $description, 'status' => $status]);
    ml_spike_json(['error' => $error, 'error_description' => $description], $status);
}

/**
 * Whether a requested redirect_uri is acceptable for a client's registered set.
 *
 * Exact string match, except for loopback addresses, where the port is ignored per
 * RFC 8252 s7.3 - native clients (Claude Code) bind an ephemeral port at runtime. The same
 * port-agnostic treatment is applied to "localhost" for compatibility, as Claude Code
 * declares it in its client metadata document.
 *
 * @param array $registered the client's registered redirect_uris
 * @param string $requested
 * @return bool
 */
function ml_spike_redirect_uri_allowed(array $registered, string $requested): bool {
    foreach ($registered as $candidate) {
        if ($candidate === $requested) {
            return true;
        }
        if (ml_spike_is_loopback($candidate) && ml_spike_is_loopback($requested)) {
            $a = parse_url($candidate);
            $b = parse_url($requested);
            if (($a['host'] ?? '') === ($b['host'] ?? '') && ($a['path'] ?? '') === ($b['path'] ?? '')) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Whether a redirect URI is a loopback address.
 *
 * @param string $uri
 * @return bool
 */
function ml_spike_is_loopback(string $uri): bool {
    $host = parse_url($uri, PHP_URL_HOST);
    return in_array($host, ['127.0.0.1', '::1', '[::1]', 'localhost'], true);
}

/**
 * Resolve a client_id to its metadata: either a Client ID Metadata Document (the client_id
 * is an HTTPS URL we dereference), or a client registered dynamically at /register, or a
 * client pre-registered by hand in the state file.
 *
 * @param string $clientid
 * @return array|null ['redirect_uris' => [...], 'client_name' => ..., 'source' => ...] or null
 */
function ml_spike_resolve_client(string $clientid) {
    // Client ID Metadata Document: the client_id is a URL that serves its own registration.
    if (preg_match('#^https://#i', $clientid)) {
        $curl = new \curl();
        $body = $curl->get($clientid, [], ['CURLOPT_TIMEOUT' => 10, 'CURLOPT_FOLLOWLOCATION' => 0]);
        $document = json_decode((string) $body, true);
        if (!is_array($document)) {
            ml_spike_log('cimd_fetch_failed', ['client_id' => $clientid, 'body' => substr((string) $body, 0, 500)]);
            return null;
        }
        // The document must be self-referential, or anyone could claim anyone's client_id.
        if (($document['client_id'] ?? '') !== $clientid) {
            ml_spike_log('cimd_not_self_referential', ['client_id' => $clientid, 'document' => $document]);
            return null;
        }
        ml_spike_log('cimd_resolved', ['client_id' => $clientid, 'document' => $document]);
        $document['source'] = 'cimd';
        return $document;
    }

    $state = ml_spike_state_read();
    return $state['clients'][$clientid] ?? null;
}
