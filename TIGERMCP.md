# Tiger — TigerMCP (Tiger as an MCP server)

How an external AI client — Claude Desktop, Claude Code, Cursor, ChatGPT — drives a Tiger install
through the **Model Context Protocol (MCP)**, over the **same token-authenticated, ACL-gated `/api`
surface** the browser and the in-app agent already use. Read this before building the `/mcp` endpoint,
the tool reflection, or the stdio bridge. For the `/api` model read [WEBSERVICES.md](WEBSERVICES.md); for
the in-app agent read [TIGERAGENT.md](TIGERAGENT.md); for the sibling extension axis read
[TIGERSKILLS.md](TIGERSKILLS.md) (§7 frames Skills vs MCP); for the admin-screen template read
[ADMIN.md](ADMIN.md).

> **Status: increments 1 + 3 BUILT; 2 + 4 scoped.** The `modules/mcp` core module ships the JSON-RPC
> endpoint (increment 1 — `initialize` / `tools/list` / `tools/call` / `ping`, Bearer auth, `tools/list` from
> `Tiger_Agent_Tools::catalog(role)`, `tools/call` proxied to `/api`) AND the connect experience (increment 3
> — the zero-Node PHP **stdio bridge** `bin/mcp-bridge.php` + the admin **Connect screen** `/mcp/admin`:
> enable toggle, mint/list/revoke tokens, copy-paste `mcpServers` config for npx-`mcp-remote` or the PHP
> bridge, bridge download). **OFF by default** (`tiger.mcp.enabled`). Still scoped, not built: tool
> `inputSchema` from Forms (increment 2), scoped/org-scoped tokens + per-token metering (increment 4). This
> doc is the design-of-record for all of it. Shape: **inbound**, a **stdio bridge** to **one** endpoint,
> **`/mcp`**, a **core module OFF by default**.

---

## 0. The one principle

**MCP is a *transport* in front of the existing `/api` — it adds reach, not capability.** An external
client authenticates with a Tiger **personal access token** (`tgr_…`) and calls the *same* services, gated
by the *same* deny-by-default ACL, as that token's identity. There is **no new privilege, no new execution
engine, and no new tool** — an MCP tool call *is* an `/api` call under a different envelope. Everything the
security model already guarantees (ACL, form validation, `validate→transaction`, the reserved-module guard)
applies unchanged. If you're ever tempted to give MCP a bypass (raw SQL, an elevated role, a tool that
isn't an `/api` op), stop — it breaks the one principle and every guarantee below.

The corollary: **capability that genuinely needs new code is a *module*** (which adds an `/api` service
that auto-reflects into an MCP tool), never something bolted onto the MCP layer.

---

## 1. The shape — a core module, off by default, one endpoint + a bridge

- **A first-party core module `modules/mcp`** (BSD-3, ships in tiger-core). **Deactivated / disabled by
  default** — `/mcp` 404s until an admin explicitly turns it on (`tiger.mcp.enabled`, default `0`), exactly
  like `/api/openapi` discovery is opt-in. A shared-host CMS install must not expose an AI-drivable surface
  by accident; a SaaS/automation user turns it on deliberately.
- **ONE HTTP endpoint: `POST /mcp`.** The whole MCP server is this single JSON-RPC endpoint (the current
  **Streamable HTTP** transport — one URL, not the retired HTTP+SSE dual-endpoint shape). No per-tool routes,
  no REST — the `/api` philosophy, one level up.
- **A stdio bridge** for the clients that speak stdio (Claude Desktop/Code, Cursor today). The bridge is a
  tiny local relay: **stdio JSON-RPC ⇄ `POST /mcp`**, carrying the token. Ships as a **zero-Node PHP**
  script (`bin/tiger mcp:bridge`) so a Tiger user needs nothing but PHP; `npx mcp-remote <url>/mcp` is the
  documented Node-world alternative (§6). This keeps the server to one standard endpoint while working with
  every MCP client that exists.

**Why a module, not folded into TigerAgent:** TigerAgent is the *in-app* front-end (user session, user's
creds); TigerMCP is the *external* front-end (scoped token, request-only identity). They're two faces of the
same core (`/api` + ACL). Keeping MCP its own module means it activates/deactivates independently and can't
drag the agent's surface with it.

---

## 2. What's already built (why this is thin)

The three hard parts exist today — MCP is the adapter that fronts them:

| Prereq | Status | Class |
|---|---|---|
| **Stateless token auth to `/api`** | **built** | `Tiger_Service_Token` (mint/list/revoke `tgr_…`, plaintext shown once) + `Tiger_Ajax_ServiceFactory` resolves `Authorization: Bearer <token>` → a stateless identity (never starts a session; invalid token = guest) via `Tiger_Service_Authentication::identityFromToken` |
| **The `/api` message dispatcher** | **built** | `Tiger_Ajax_ServiceFactory` — resolves module/service/method, runs the ACL + form-validate + transaction, returns the standard envelope |
| **Deny-by-default ACL** | **built** | `Tiger_Acl_Acl` — `isAllowed($role, $service, $method)` on every call |
| **Role-filtered tool reflection** | **built** | `Tiger_Agent_Tools::catalog($role)` → `{module: [{service, method, summary}]}`, each op checked with `isAllowed` — *this is `tools/list`* |
| **Argument schema from Forms** | **built** | `Tiger_OpenApi_Generator` maps a method's `Tiger_Form::elements()` → a JSON Schema request body — *this is a tool's `inputSchema`* |

So "an external AI creates content / runs actions in Tiger" is not a new capability — it's the same guarded
services a human (or the in-app agent) uses, reached over MCP.

---

## 3. The `/mcp` endpoint — standard MCP, the tool-calling subset

`POST /mcp` speaks **JSON-RPC 2.0** and implements the MCP lifecycle + tools methods a client expects:

- **`initialize`** — client sends `protocolVersion`, `capabilities`, `clientInfo`; server replies with its
  `protocolVersion`, `capabilities: { tools: { listChanged: false } }`, and `serverInfo { name: "Tiger",
  version }`. Client follows with the `notifications/initialized` notification.
- **`tools/list`** — returns `{ tools: [ { name, description, inputSchema } ] }`, reflected from the token
  identity's role-allowed `/api` surface (§4). Pagination via `cursor` when a big install exceeds one page.
- **`tools/call`** — `{ name, arguments }` → the named `/api` op is dispatched as the token identity (§5);
  the result is returned as MCP `content` blocks.
- **`ping`** — liveness.

**Transport shape (v1):** the simple request/response mode of Streamable HTTP — the client POSTs a JSON-RPC
request, the server returns a single JSON-RPC response. No server-initiated messages are needed for the
tool-calling subset, so **SSE and session resumption are deferred** (the endpoint MAY add an `Mcp-Session-Id`
+ SSE later for notifications/streaming, without changing the tool contract).

**Auth:** `Authorization: Bearer tgr_…` on every `/mcp` request — the exact header `ServiceFactory` already
understands. (OAuth 2.1, which the MCP HTTP-auth spec prefers for *direct* remote clients, is deferred — the
stdio bridge holds the token and presents the Bearer, so v1 needs no OAuth dance. See §7.)

---

## 4. `tools/list` — reflect the role-filtered `/api` surface

The tool list *is* `Tiger_Agent_Tools::catalog($role)` where `$role` is the token identity's role,
serialized into MCP tool objects:

- **`name`** = `"<module>__<service>__<method>"` (double-underscore delimited; module/service are
  `[a-zA-Z]`, so `explode('__', $name, 3)` recovers the target unambiguously even when a method name itself
  contains underscores). Within MCP's `[A-Za-z0-9_-]` name charset.
- **`description`** = the method's docblock summary (already in the catalog).
- **`inputSchema`** = the method's `Tiger_Form` → JSON Schema via the `Tiger_OpenApi_Generator` mapper
  (field names, types, `required` from validators). A form-less method gets a permissive object schema.

**Scoped to the token — a curated starter set by default (decided).** A fresh MCP token exposes a **curated
starter set** of tools, not the whole role surface: the mainstream business verbs — **content** (CMS
pages/menus), **media**, **blog**, and, where those modules are present, **commerce** (shop / membership) —
with the admin able to **widen** a token to more of its role's surface explicitly. MCP clients get confused
by 100+ tools, and least-privilege is the right default. The token carries the allow-list (modules/tool
names + a read-only flag). **Discovery == authorization**: `tools/list` can never advertise an op the token
isn't allowed to call, because it's reflected through the same ACL that would deny `tools/call` *and* clipped
to the token's set. This is the property most MCP servers lack.

---

## 5. `tools/call` — proxy to `/api` as the token identity

1. Split `name` → `{module, service, method}`.
2. Dispatch through `Tiger_Ajax_ServiceFactory` **as the token identity** (the same in-process dispatch the
   Forge uses for its `api` action) — the target service's own ACL, form-validate, and `_transaction()` all
   run unchanged. A denied/unknown op returns an MCP error, never a bypass.
3. Map the standard envelope → the MCP result:
   - `result=1` → `{ content: [ { type: "text", text: <JSON of data + messages> } ] }` (structured text the
     model reads; a future revision can emit MCP structured content).
   - `result=0` → `{ content: [...messages...], isError: true }`.
4. **Read vs write is the ACL's job, not a prompt gate.** Unlike the in-app agent (which pauses writes for
   human approval — TIGERAGENT §3), an MCP client is *itself* the human's agent; there's no Tiger UI to
   approve in. So the wall is the **token's scope + the ACL + metering + audit** (§7–8), and a write token is
   a deliberate grant — **no per-write approval webhook (decided):** token scope + audit is the boundary, and
   adding an out-of-band approval step would fight the point of a headless automation surface.

---

## 6. The stdio bridge — conform to what the community expects

Most MCP clients launch a **local process** and speak stdio to it. Tiger ships that process so a user
configures Tiger exactly like any other MCP server — the standard `mcpServers` block:

```jsonc
// Claude Desktop / Cursor / Claude Code — claude_desktop_config.json (or equivalent)
{
  "mcpServers": {
    "tiger": {
      "command": "php",
      "args": ["/path/to/tiger/vendor/webtigers/tiger-core/bin/mcp-bridge.php"],
      "env": {
        "TIGER_MCP_URL":   "https://my-site.com/mcp",
        "TIGER_MCP_TOKEN": "tgr_xxxxxxxx"
      }
    }
  }
}
```

`mcp-bridge.php` is a **zero-dependency PHP** stdio↔HTTP relay: read newline-delimited JSON-RPC from stdin →
`POST $TIGER_MCP_URL` with `Authorization: Bearer $TIGER_MCP_TOKEN` → write the JSON-RPC response to stdout.
No Node, no Composer — fits the cPanel/zero-build ethos. **Node-world alternative (documented, not shipped):**
`npx -y mcp-remote https://my-site.com/mcp --header "Authorization: Bearer tgr_…"` — the de-facto community
stdio↔HTTP bridge, pointed at the same one endpoint.

The admin **Connect** screen (§ nav below) shows the ready-to-paste config with the site URL filled in and a
one-click **mint token** — the WordPress-plugin-grade "it just works" setup.

---

## 7. Auth + scoped tokens (the one small addition)

- **The token primitive is built** (`Tiger_Service_Token`): mint from a normal session, plaintext shown
  once, listed by prefix, revocable. It authenticates `/api` (and now `/mcp`) with no session.
- **MCP tokens are ORG-scoped by default (decided).** An MCP token acts **as the org, not a person** — an
  admin of the org mints it, and it resolves to an org-acting identity (an `org_id` + a role + the tool
  scope, no bound `user_id`; actor stamps + audit record the *token*, not an individual). This is the right
  unit for headless automation (it survives the minting admin leaving, and it's honest about "a machine did
  this"). The existing user-owned token stays for a person's own CLI use; MCP defaults to the org token.
- **The addition MCP wants: a *scope* on the token** — the allow-list of modules/tools + a read-only flag
  (§4), so an MCP token is least-privilege (e.g. "content, read+write" or "analytics, read-only")
  independent of the role's full surface. Org-scope + tool-scope are the only genuinely new persistence.
- **OAuth 2.1** (the MCP HTTP-auth direction for direct remote clients) is **deferred**: the stdio bridge
  presents a static Bearer, which covers the v1 clients. When direct-HTTP MCP clients (no bridge) matter, add
  the OAuth authorization-server metadata + flow in front of the same tokens.

---

## 8. Off by default + the guardrails (the load-bearing section)

MCP hands an external, autonomous AI a key to your business. State the posture honestly:

1. **Disabled by default.** `/mcp` 404s unless `tiger.mcp.enabled`. Turning it on is a deliberate admin act.
2. **The ACL is the whole security model (§0).** An MCP token can do exactly what its role+scope allow — no
   more. Deny-by-default, enforced at dispatch, not in a prompt.
3. **Least-privilege tokens (§7)** — scope a token to the smallest surface the job needs; prefer read-only.
4. **Meter the token — the real new risk.** An external agent in a loop can hammer `/api`. Enforce a
   **per-token rate limit + a per-token request/period cap**, 429 past it. (If a tool call reaches the in-app
   *agent*, it burns the org's BYO AI budget — cap that path especially.)
5. **Audit every `tools/call`** — reuse the agent's audit trail (who/token, tool, args, result). "What did
   this token do?" must always be answerable.
6. **Prompt injection is inherited + named.** The external model reads site data that can carry
   instructions; the bound is the same as §0 (worst case = what the token's scope allows), mitigated by
   least-privilege + metering + audit, **not eliminated**. Ship it with that framing, like TIGERAGENT §8 /
   CODE.md §5.

---

## 9. The sibling axis — outbound MCP (deferred)

The inbound server (this doc) is v1. The mirror — **outbound Connections**, where the *in-app* agent
*consumes* an external MCP server's tools — is a later, smaller add: register a remote MCP server, list its
tools, and expose them to `Tiger_Agent_Loop` as additional tools (namespaced, permission-gated like any
other). It reuses the agent's tool-registry seam; it's out of scope here beyond fixing the shared **Agent →
MCP** IA (TIGERSKILLS §6): `MCP ▸ Server/Access` (inbound, this doc) and `MCP ▸ Connections` (outbound, later).

---

## 10. Rejected alternatives (so we don't relitigate)

| Rejected | Why | Chosen |
|---|---|---|
| A new tool-execution engine for MCP | duplicates `/api` + the ACL; a second thing to secure | MCP = a JSON-RPC front-end that **proxies `/api`** as the token identity |
| Many endpoints / REST-per-tool | endpoint zoo; not the Tiger model | **one** `POST /mcp` (Streamable HTTP), tools reflected |
| On by default | exposes an AI-drivable surface on every install | **off by default** (`tiger.mcp.enabled`) |
| Hand-written tool schemas | drift from the real services | reflect `Tiger_Agent_Tools` + the Form→schema mapper |
| Fold MCP into TigerAgent | couples external reach to the in-app aside; can't toggle independently | its **own core module** `modules/mcp` |
| A bespoke protocol | strands us from the ecosystem | **standard MCP** (JSON-RPC 2.0, stdio + Streamable HTTP) |
| Require Node for the bridge | breaks the zero-build/cPanel ethos | ship a **zero-Node PHP** bridge; `mcp-remote` as an option |
| Human-approval gate on writes (like the aside) | there's no Tiger UI in an external client to approve in | least-privilege token scope + ACL + metering + audit (approval webhook = §12) |

---

## 11. Build order (increments)

1. **The `/mcp` server — ✅ BUILT.** `modules/mcp` (off by default): `Tiger_Mcp` (enable gate + version
   negotiation) + `Tiger_Mcp_Server` (the JSON-RPC engine — `initialize` / `tools/list` / `tools/call` /
   `ping`) + `Mcp_ServerController` (the `/mcp` HTTP surface: Bearer-or-session identity, request/response).
   `tools/list` from `Tiger_Agent_Tools::catalog(role)`; `tools/call` proxied to `/api` via `ServiceFactory`.
   Route ingested from `modules/mcp/configs/routes.ini`; controller public in `acl.ini` (token + per-service
   ACL gate). Verified live: 404 disabled → `initialize` handshake → `tools/list` reflects the role surface.
2. **Tool `inputSchema`** — wire the `Tiger_OpenApi_Generator` Form→JSON-Schema mapper into `tools/list` so
   arguments are typed (not just a permissive object).
3. **The stdio bridge + Connect screen — ✅ BUILT.** `bin/mcp-bridge.php` (zero-Node PHP stdio↔HTTP relay:
   env `TIGER_MCP_URL`/`TIGER_MCP_TOKEN`, guards the stdout channel, JSON-RPC errors on transport failure) +
   `Mcp_AdminController` `/mcp/admin` (the Connect screen: enable toggle via `Mcp_Service_Settings`, mint/
   list/revoke tokens via the core `Tiger_Service_Token`, ready-to-paste `mcpServers` config for both
   `npx mcp-remote` and the PHP bridge, a `download` action that serves the bridge). Nav-registered under
   Settings; `mcp-remote` documented as the Node alternative.
4. **Scoped tokens + metering** — the token allow-list/read-only flag (§7) and per-token rate-limit + cap +
   audit (§8).
5. **(later) Streamable HTTP niceties** — `Mcp-Session-Id` + SSE for notifications; **OAuth 2.1** for
   direct-HTTP clients; then the **outbound Connections** axis (§9).

---

## 12. Settled + open

**Settled (Beau, 2026-08-17):**
- **Curated starter set** — a fresh MCP token exposes the mainstream verbs, widenable, not the whole role (§4).
- **Org-scoped tokens** — an MCP token acts as the org, not a person (§7).
- **Tools-only v1** — MCP `resources` (read-only context — CMS pages/docs) and `prompts` are **deferred**;
  v1 is tools-only. (`resources` is a natural early follow-on since the content is already there.)
- **No approval webhook** — token scope + ACL + metering + audit is the write boundary (§5).

**Still open:**
- **Structured results.** Return `/api` `data` as JSON-in-text (simple, works everywhere) vs MCP structured
  content / an output schema (richer, newer). Start with text?
- **Discovery signal.** Advertise the server via `/.well-known` / `llms.txt` so an AI *finds* the Tiger MCP
  endpoint (ties to the AI-discoverability plan) — in v1 or later?

---

*This document records decisions and their rationale. If you change a decision, update the "why" here in the
same change.*
