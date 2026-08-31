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
 * Model Context Protocol (MCP) endpoint for the mod_minilesson aigen functions.
 *
 * A stateless MCP server over the Streamable HTTP transport: it accepts JSON-RPC 2.0
 * requests by POST and exposes each aigen function as an MCP tool, so MCP clients
 * (Claude Desktop / Claude Code custom connectors, and other MCP hosts) can drive the
 * aigen APIs directly. There is no server-initiated SSE stream and no session state -
 * every request carries its own token.
 *
 * All shared logic (the brokered function set, token auth, dispatch, JSON schemas) lives
 * in \mod_minilesson\local\aigen\facade, the same core used by aigen_rest.php and
 * openapi.php; this file is just the MCP/JSON-RPC front-end.
 *
 * Methods: initialize, ping and notifications/* need no auth; tools/list and tools/call
 * require the token (X-API-Key header, or Authorization: Bearer - the latter is what an
 * OAuth-issued access token arrives as, since it is just a real web service token minted
 * via the shared local_oauthmcp authorization server, if that plugin is installed). An
 * unauthenticated or invalid attempt gets a 401 with a resource_metadata challenge (RFC 9728)
 * pointing OAuth-capable clients at oauth_resource_metadata.php to discover the flow - only
 * when local_oauthmcp is present, since without it there is no OAuth flow to discover and the
 * static-token path (X-API-Key) is unaffected either way.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
// Behave as a stateless web service entry point (token auth, no session cookies).
define('WS_SERVER', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Token-authenticated web service endpoint; auth is via the facade.
require(__DIR__ . '/../../config.php');

use mod_minilesson\local\aigen\facade;

/** @var string The MCP protocol version this endpoint implements. */
const MCP_PROTOCOL_VERSION = '2025-06-18';

/**
 * Send a JSON body (or an empty body for notifications) and stop.
 *
 * @param mixed $payload response body, or null for an empty (202) body
 * @param int $status HTTP status code
 * @return never
 */
function mcp_send($payload, int $status = 200) {
    if ($payload === null) {
        http_response_code($status);
        die;
    }
    header('Content-Type: application/json; charset=utf-8', true, $status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    die;
}

/**
 * Build a JSON-RPC success envelope.
 *
 * @param mixed $id request id
 * @param mixed $result
 * @return array
 */
function mcp_result($id, $result): array {
    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
}

/**
 * Build a JSON-RPC error envelope.
 *
 * @param mixed $id request id
 * @param int $code JSON-RPC error code
 * @param string $message
 * @return array
 */
function mcp_error($id, int $code, string $message): array {
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

/**
 * The name this server identifies itself as in the MCP `initialize` response - includes the
 * site host so a client connected to several Moodle sites can tell them apart in its own UI.
 *
 * @return string
 */
function mcp_server_name(): string {
    global $CFG;
    $host = parse_url($CFG->wwwroot, PHP_URL_HOST) ?: $CFG->wwwroot;
    return "Poodll MiniLesson on {$host}";
}

/**
 * Send the 401 challenge header. When local_oauthmcp is installed, points OAuth-capable
 * clients at the RFC 9728 protected resource metadata document so they can discover the
 * authorization server and start the OAuth flow instead of giving up on a bare 401; when it
 * is not installed, sends a plain challenge with no OAuth discovery pointer (there is no
 * OAuth flow to discover) - the static-token path this header accompanies is unaffected
 * either way.
 *
 * @return void
 */
function mcp_send_auth_challenge(): void {
    global $CFG;
    if (class_exists('\local_oauthmcp\api')) {
        header(
            'WWW-Authenticate: Bearer resource_metadata="' . $CFG->wwwroot . '/mod/minilesson/oauth_resource_metadata.php"',
            true,
            401
        );
        return;
    }
    header('WWW-Authenticate: Bearer', true, 401);
}

/**
 * Serve RFC 9728/8414 discovery JSON (and exit) if this GET's PATH_INFO asks for it and
 * local_oauthmcp is installed - covers clients that append ".well-known/..." onto the
 * resource's own URL (mcp.php) rather than inserting it at the domain root, e.g. the real
 * request "GET /mod/minilesson/mcp.php/.well-known/openid-configuration" observed from a
 * real client. Returns normally (falls through to the existing 405) for any other GET, or
 * when local_oauthmcp is absent.
 *
 * @return void
 */
function mcp_maybe_send_wellknown_discovery(): void {
    global $CFG;
    if (!class_exists('\local_oauthmcp\api')) {
        return;
    }
    $pathinfo = $_SERVER['PATH_INFO'] ?? '';
    if (strpos($pathinfo, 'oauth-protected-resource') !== false) {
        $data = \local_oauthmcp\api::resource_metadata($CFG->wwwroot . '/mod/minilesson/mcp.php');
        if ($data !== null) {
            mcp_send($data);
        }
    }
    if (strpos($pathinfo, 'oauth-authorization-server') !== false || strpos($pathinfo, 'openid-configuration') !== false) {
        mcp_send(\local_oauthmcp\api::authorization_server_metadata());
    }
}

/**
 * Server-level instructions surfaced to the model on initialize (the three request kinds
 * and how to route them; the full workflow detail is in the tool descriptions / openapi.php).
 *
 * @return string
 */
function mcp_instructions(): string {
    return implode(' ', [
        'These tools create and manage Poodll MiniLessons.',
        'Requests come in three kinds - route each accordingly:',
        '(1) SOURCE MATERIAL (an uploaded PDF/doc/image or a pasted lesson plan): reproduce it',
        'faithfully as items - list item types, fetch each type\'s spec, compose items, create a',
        'lesson and import them.',
        '(2) EXPORTED LESSON AS A TEMPLATE (an uploaded export, or an existing lesson pulled with',
        'aigen_export_items_json): keep each item\'s type/layout/options, rewrite only the wording',
        'per topic, drop the old images, and let audio regenerate from text.',
        '(3) A DESCRIBED LESSON: check aigen_list_templates first and use a template if one fits',
        '(templates can generate media); only hand-compose if none fits.',
        'Only item types where aigen_list_itemtypes reports hasimportdocs=true can be hand-composed.',
        'PICK THE MOST SPECIFIC TEMPLATE: several templates produce the same item type, and each one',
        'lists its siblings in "variants" with a "control" level - "supplied" (your text is used',
        'verbatim), "derived" (the AI marks up text you supply) or "generated" (the AI invents the',
        'content). For every teaching point you have already decided - which words are gapped or',
        'shuffled, which answer is correct, the translation language, the grammar being practised -',
        'check the template has an input that carries it. If none does, the AI decides it for you and',
        'may contradict the lesson aim: move to a higher-control variant, or compose the item directly.',
        'Only take a "generated" template where the user has genuinely left that detail open.',
        'PLAN FIRST: before calling any tool that creates or imports (aigen_create_empty_lesson,',
        'aigen_create_add_items_to_lesson, aigen_import_items_json), show the user a plan and wait for',
        'their approval. For direct-compose, list each item with its actual content (question, answers,',
        'text); for templates, list EVERY input the template declares with the exact value you will send,',
        'each marked (from the user), (your choice) or (BLANK) - a choice you made on the user\'s behalf',
        'is still a choice, including defaults you accepted and inputs you are leaving empty. State the',
        'target course/title. Create only after they approve, and fold in any changes they request.',
        'NEVER send a required input empty. If the user has not told you what it needs (a native',
        'language, a source text, a level), ask before creating - creation is rejected when a required',
        'input is empty, and a template that does not check would produce silently broken content, like',
        'a vocabulary card whose translation is a copy of the word. Where you picked a value the user',
        'expressed no preference about (image style, level, voice), name your choice and offer the',
        'alternatives rather than presenting it as settled.',
        'Exception: if the user has said to just go ahead (or to skip the review), create without pausing.',
        'After importing, read the per-item errors array and resubmit only the rejected items.',
    ]);
}

// The Streamable HTTP transport is POST for client -> server messages. We do not offer a
// server -> client SSE stream, so GET (and anything else) is not supported.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    if ($method === 'GET') {
        mcp_maybe_send_wellknown_discovery();
    }
    mcp_send(['error' => 'This MCP endpoint accepts POST (JSON-RPC) only'], 405);
}

// Parse the JSON-RPC request.
$raw = file_get_contents('php://input');
$request = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($request)) {
    mcp_send(mcp_error(null, -32700, 'Parse error'));
}
$id = $request['id'] ?? null;
$rpcmethod = (string) ($request['method'] ?? '');
$params = isset($request['params']) && is_array($request['params']) ? $request['params'] : [];
$isnotification = !array_key_exists('id', $request);

// Lifecycle / discovery methods that require no auth.
if (strpos($rpcmethod, 'notifications/') === 0) {
    // Notifications get no response body.
    mcp_send(null, 202);
}
if ($rpcmethod === 'initialize') {
    mcp_send(mcp_result($id, [
        'protocolVersion' => MCP_PROTOCOL_VERSION,
        'capabilities' => ['tools' => new stdClass()],
        'serverInfo' => ['name' => mcp_server_name(), 'version' => '1.0.0'],
        'instructions' => mcp_instructions(),
    ]));
}
if ($rpcmethod === 'ping') {
    mcp_send(mcp_result($id, new stdClass()));
}

// Everything below (tools/*) requires an authenticated token.
$token = facade::request_token();
if ($token === '') {
    mcp_send_auth_challenge();
    mcp_send(mcp_error($id, -32001, 'Authentication required'), 401);
}
try {
    $authinfo = facade::authenticate($token);
} catch (Throwable $e) {
    mcp_send_auth_challenge();
    mcp_send(mcp_error($id, -32001, 'Invalid or unauthorised token'), 401);
}

switch ($rpcmethod) {
    case 'tools/list':
        $tools = [];
        foreach (facade::functions_info() as $name => $functioninfo) {
            $tools[] = [
                'name' => facade::short_name($name),
                'description' => $functioninfo->description,
                'inputSchema' => facade::input_schema($functioninfo),
            ];
        }
        mcp_send(mcp_result($id, ['tools' => $tools]));
        break;

    case 'tools/call':
        $toolname = isset($params['name']) ? clean_param($params['name'], PARAM_ALPHANUMEXT) : '';
        $args = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];
        $functionname = facade::full_name($toolname);

        if ($toolname === '' || !in_array($functionname, facade::function_names())) {
            mcp_send(mcp_error($id, -32602, 'Unknown tool: ' . $toolname));
        }
        if (!facade::token_allows($authinfo, $functionname)) {
            mcp_send(mcp_error($id, -32001, 'This token is not allowed to call ' . $toolname));
        }

        $response = facade::call($functionname, $args);
        if (!empty($response['error'])) {
            // Tool-level failure: report as an MCP tool result with isError so the model can iterate.
            $message = $response['exception']->message ?? 'Web service error';
            mcp_send(mcp_result($id, [
                'content' => [['type' => 'text', 'text' => $message]],
                'isError' => true,
            ]));
        }
        $text = json_encode($response['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        mcp_send(mcp_result($id, [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => false,
        ]));
        break;

    default:
        if ($isnotification) {
            mcp_send(null, 202);
        }
        mcp_send(mcp_error($id, -32601, 'Method not found: ' . $rpcmethod));
}
