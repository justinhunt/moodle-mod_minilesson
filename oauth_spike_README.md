# OAuth spike — runbook

**Throwaway.** Delete all five `oauth_spike*` files and revert the `mcp.php` edit once the
questions below are answered. Nothing here is production code: the spike issues no
credentials of its own, it hands out one web service token you supply.

## Why

A Moodle plugin lives in a subdirectory, so it cannot serve `/.well-known/*` at the site
root. Of the three authorization-server metadata URLs an MCP client tries, only the third
is servable from a plugin:

| # | URL a client tries | Servable by a plugin? |
|---|---|---|
| 1 | `{host}/.well-known/oauth-authorization-server/{path}` | ✗ site root |
| 2 | `{host}/.well-known/openid-configuration/{path}` | ✗ site root |
| 3 | `{issuer}/.well-known/openid-configuration` | ✓ via `PATH_INFO` |

Whether real clients fall back to #3 decides whether the authorization server can be
self-hosted in the plugin, or whether every site needs a web server alias.

## What is already confirmed (local, `localhost:8051`)

- `PATH_INFO` routing reaches the script — Apache does not 404 the path itself.
- All three discovery documents serve, and `issuer` matches the URL it was fetched from
  (clients reject the document otherwise).
- `mcp.php` returns `401` with
  `WWW-Authenticate: Bearer error="invalid_token", resource_metadata="…", scope="…"`.
- `initialize` still succeeds unauthenticated, so clients show a Connect card rather than
  an error (this is the supported "lazy auth" pattern).
- DCR `/register` issues a `client_id`.
- Full token endpoint: PKCE S256 verified, wrong verifier rejected, authorization codes
  single-use, refresh tokens rotated, replayed refresh token rejected — all with
  RFC 6749 `invalid_grant`, which is what clients key off to re-run the flow.
- `/authorize` redirects to Moodle login, so the agent never sees a credential.

**Not confirmed, and the reason the spike exists:** whether Claude and Gemini Spark reach
#3 over the public internet, and what redirect URI Spark uses.

## Enable

Needs a **public HTTPS** site — Anthropic and Google reach your server from their own
infrastructure, so `localhost` will not do. Use a tunnel (`cloudflared tunnel --url …`)
if testing from a dev box, and set `$CFG->wwwroot` to the tunnel URL.

In `config.php`:

```php
$CFG->minilesson_oauth_spike = true;
$CFG->minilesson_oauth_spike_token = '<an existing aigenservice web service token>';
```

Both are required; with either missing every spike endpoint returns a plain 404 and
`mcp.php` behaves exactly as before. To switch the spike off, delete the two lines.

The token is one you create the normal way (Site administration → Server → Web services →
Manage tokens) for a user who is on the aigenservice allowed-users list. It is handed to
whoever completes the flow, so use a test account.

## Run

1. **Claude** — Settings → Connectors → Add custom connector, URL
   `https://<site>/mod/minilesson/mcp.php`. Expect a browser popup to Moodle login, then
   the consent screen, then a connected server whose tools you can call.
2. **Gemini Spark** — Gemini web app → Settings & help → Connected Apps, same URL. If it
   asks for credentials under "Advanced features", that is the DCR fallback — note it and
   stop, that answers one of the questions on its own.
3. **Claude Code** — `claude mcp add --transport http minilesson https://<site>/mod/minilesson/mcp.php`.
   This is the CIMD + loopback path, so it exercises different code from #1.

Read the results at `/mod/minilesson/oauth_spike_log.php` (site admins only), which shows
every request in order with full parameters, plus the registered clients.

## Readings to take

Record the answer to each; these are the inputs to the real design.

1. **Did the client fetch `…/oauth_spike.php/.well-known/openid-configuration`?**
   A `discovery_as` entry means yes → the authorization server can be plugin-hosted, and
   no web server config is needed. This is the single most important reading.
2. **Did it try the site-root URLs first?** The spike cannot see these — they 404 at the
   web server. Check the access log:
   ```
   grep '\.well-known' /var/log/apache2/access.log
   ```
   Root probes followed by a #3 that succeeds is the good outcome. Root probes and then
   nothing means that client needs an alias, and the alias becomes a documented
   prerequisite:
   ```apache
   Alias /.well-known/oauth-authorization-server /path/to/moodle/public/mod/minilesson/oauth.php
   ```
3. **Redirect URI per client** — from the `register_request` entry, or the registered
   clients panel. Expected: `https://claude.ai/api/mcp/auth_callback` for Claude,
   loopback with an ephemeral port for Claude Code, **unknown for Spark** — this is the
   value that could not be found in Google's public docs.
4. **DCR or CIMD or neither**, per client. `register_request` = DCR;
   `cimd_resolved` = CIMD; neither, plus a request for credentials in the client's UI =
   pre-registered only (which is what Gemini Enterprise requires).
5. **Was `resource` sent?** (RFC 8707, on `authorize_request` and `token_request`.)
   Determines whether audience binding can be enforced or only recorded.
6. **`token_request` content type** — confirm form-urlencoded, and that `raw_was_parsed`
   is false. A client sending JSON here would need handling.
7. **Did any redirect_uri get rejected?** An `authorize_rejected` entry means the
   port-agnostic loopback rule in `ml_spike_redirect_uri_allowed()` needs work.
8. **`on_aigenservice_allowed_users`** on the `authorize_user` entry — whether the
   consenting user would have passed the real gate. If this is false for testers who
   ought to have access, the allowed-users list is a bigger onboarding burden than
   assumed, which argues for revisiting the auto-authorise setting.

## Clean up

```
rm public/mod/minilesson/oauth_spike*.php public/mod/minilesson/oauth_spike_README.md
rm -rf <dataroot>/minilesson_oauth_spike
```
Revert the `mcp_auth_challenge()` edit in `mcp.php`, and remove the two `config.php` lines.
