# Connect Claude Desktop to MiniLesson (MCP)

This folder ships a ready-to-install **Claude Desktop extension** (`.mcpb`) for using AI
agents to generate MiniLessons on this site — creating items, importing lessons, and
using lesson templates, all driven from a chat.

**Full walkthrough (setup, screenshots, other agents/clients):**
https://support.poodll.com/en/support/solutions/articles/19000175579-using-ai-agents-to-generate-minilessons

That article is the authoritative guide and also covers Claude Code, ChatGPT custom GPTs,
and other MCP-capable clients. This README only covers the one file in this folder.

## Requirements

- This site must have this MiniLesson plugin update (or later) which includes `mcp.php`,
  `aigen_rest.php`, `openapi.php` and `classes/local/aigen/facade.php`, and be reachable
  over public HTTPS.
- A Moodle web service user set up as described in the article above ("The Web Service
  User and Permissions"), with a token for the **AI Generation Service (aigenservice)**
  (Site administration → Server → Web services → Manage tokens).

## What's in this folder

- `poodllminilesson.mcpb` — the packaged Claude Desktop extension. This is the file most
  people need.
- `relay.js`, `manifest.json`, `package.json` — its source. Claude Desktop runs `relay.js`
  as a small local relay (Node built-ins only, no dependencies) that forwards requests to
  this site's `mod/minilesson/mcp.php` over HTTPS, adding your token as an `X-API-Key`
  header. It exists because Claude Desktop's remote-connector UI only supports OAuth login,
  with no field for a plain web service token — the relay works around that.

## Install

1. In Claude Desktop, go to **Settings → Extensions → Install Extension…** (may be under
   an **Advanced** section) and select `poodllminilesson.mcpb` from this folder.
2. When prompted, enter:
   - **Moodle site URL** — this site's base URL, e.g. `https://school.example.com`
     (no trailing slash)
   - **Web service token** — the aigenservice token from the step above
3. Enable the extension.

Your token is stored by Claude Desktop (marked sensitive) and only ever sent to this site
— never to the model.

For other agents (Claude Code, ChatGPT custom GPTs, or calling the API directly), see the
full article linked above.
