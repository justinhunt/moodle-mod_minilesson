#!/usr/bin/env node
'use strict';

/*
 * Poodll MiniLesson - stdio <-> HTTP MCP relay.
 *
 * Claude Desktop's remote-connector UI only supports OAuth, with no field for a
 * plain web service token. This tiny local MCP server works around that: Claude
 * Desktop spawns it over stdio, and it forwards each JSON-RPC message verbatim to
 * the site's remote MCP endpoint (mod/minilesson/mcp.php) over HTTP, injecting the
 * token as an X-API-Key header. mcp.php does all the actual MCP work (tool listing,
 * dispatch, instructions); this only bridges the two transports and adds auth.
 *
 * Config comes from the extension (see manifest.json) as two environment variables:
 *   MOODLE_MCP_URL   - the Moodle site base URL, e.g. https://school.example.com
 *   MOODLE_MCP_TOKEN - an aigenservice web service token
 *
 * No third-party dependencies: only Node built-ins, so nothing to install/bundle.
 */

const readline = require('node:readline');
const http = require('node:http');
const https = require('node:https');
const { URL } = require('node:url');

const base = (process.env.MOODLE_MCP_URL || '').replace(/\/+$/, '');
const token = process.env.MOODLE_MCP_TOKEN || '';

if (!base) {
    process.stderr.write('Poodll MiniLesson relay: MOODLE_MCP_URL is not set.\n');
    process.exit(1);
}

const endpoint = new URL(base + '/mod/minilesson/mcp.php');
const transport = endpoint.protocol === 'http:' ? http : https;

/**
 * POST a JSON-RPC message body to the remote MCP endpoint.
 *
 * @param {string} body
 * @returns {Promise<{status: number, body: string}>}
 */
function postToEndpoint(body) {
    return new Promise((resolve, reject) => {
        const req = transport.request(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-API-Key': token,
                'Content-Length': Buffer.byteLength(body),
            },
        }, (res) => {
            let data = '';
            res.on('data', (chunk) => { data += chunk; });
            res.on('end', () => resolve({ status: res.statusCode, body: data }));
        });
        req.on('error', reject);
        req.write(body);
        req.end();
    });
}

const rl = readline.createInterface({ input: process.stdin });

rl.on('line', async (line) => {
    line = line.trim();
    if (!line) {
        return;
    }
    let message;
    try {
        message = JSON.parse(line);
    } catch (e) {
        return; // Ignore anything that is not a JSON-RPC message.
    }
    const isnotification = !Object.prototype.hasOwnProperty.call(message, 'id');
    try {
        const response = await postToEndpoint(JSON.stringify(message));
        // Notifications (and the 202 they produce) have no response to relay back.
        if (response.status === 202 || isnotification) {
            return;
        }
        const body = (response.body || '').replace(/^﻿/, '').trim();
        // The endpoint must return a single JSON-RPC message. Parse and re-serialise so a
        // valid response is always emitted as one compact line, and anything else (an HTML
        // redirect/404/error page, a PHP notice, etc.) is turned into a clean JSON-RPC error
        // instead of corrupting the stdout stream (which Claude rejects as "Invalid JSON-RPC").
        let parsed = null;
        try {
            parsed = JSON.parse(body);
        } catch (e) {
            parsed = null;
        }
        if (parsed && typeof parsed === 'object' && parsed.jsonrpc) {
            process.stdout.write(JSON.stringify(parsed) + '\n');
            return;
        }
        // Log the raw response to stderr (visible in Claude's MCP logs) and return a helpful error.
        process.stderr.write('[poodll-minilesson] non-JSON-RPC response (HTTP ' + response.status
            + ') from ' + endpoint.href + ':\n' + body.slice(0, 800) + '\n');
        process.stdout.write(JSON.stringify({
            jsonrpc: '2.0',
            id: message.id,
            error: {
                code: -32603,
                message: 'The MiniLesson endpoint returned a non-JSON response (HTTP ' + response.status
                    + '). Check that MOODLE_MCP_URL exactly matches the site\'s configured wwwroot and that '
                    + 'mod/minilesson/mcp.php is deployed and reachable. See the MCP log for the response body.',
            },
        }) + '\n');
    } catch (e) {
        process.stderr.write('[poodll-minilesson] request error to ' + endpoint.href + ': ' + e.message + '\n');
        if (!isnotification) {
            process.stdout.write(JSON.stringify({
                jsonrpc: '2.0',
                id: message.id,
                error: { code: -32603, message: 'Relay could not reach the MiniLesson endpoint: ' + e.message },
            }) + '\n');
        }
    }
});
