# Tiger — Agent Skills (installable know-how for TigerAgent)

How TigerAgent gains new **know-how** from GitHub-installable **Skills**, how an admin manages which are
added/activated/updated, and how Skills sit next to **MCP** as the two axes of *extending the agent*. Read
this before building the skill loader, the Skills admin, or the MCP surfaces. For the agent itself read
[TIGERAGENT.md](TIGERAGENT.md); for the `/api` tool contract read [WEBSERVICES.md](WEBSERVICES.md); for the
install/update/registry machinery read [MARKETPLACE.md](MARKETPLACE.md); for the file-discovery + active-set
pattern this mirrors read [CODE.md](CODE.md).

> **Status: design-of-record (proposed, not built).** TigerAgent Phase 1 ships (the aside, the `/api`-tool
> surface, the permission-gated Forge, the Scout). Skills + MCP are the next extension layer; this records
> the decisions so we don't relitigate them or drift when the code lands.

---

## 0. The one principle

**A skill is *know-how*, not *capability*.** In Tiger the agent's *capability* is already fixed and safe:
its tools **are** the role-filtered `/api` surface, executed **as the current identity** with deny-by-default
ACL (TIGERAGENT.md §2). A skill can never do anything the agent's role can't already do — it teaches the
agent **how** to orchestrate the tools it already holds. So Skills are additive, portable, and can't widen
the security envelope. Capability that genuinely needs new code is a **module** (which adds an `/api` service
that auto-reflects into a tool), never a script smuggled in via a skill.

Hold that line and Skills are cheap and safe: they compose two things Tiger already has — the **module
installer/registry** (distribution) and the **Code-module file-discovery + active-set** (activation) — plus
a thin loader into the agent turn.

---

## 1. What a skill is — adopt the `SKILL.md` format

A **skill** is a folder with a `SKILL.md` (the Anthropic Agent Skills shape) and optional bundled resources:

```
skills/author-storefront/
  SKILL.md            ; frontmatter (name, description, when-to-use) + the instructions body
  reference/          ; optional: extra markdown/data the skill pulls in on demand
```

`SKILL.md` frontmatter:
```markdown
---
name: author-storefront
description: Set up an indie-author storefront — products, a paywalled member tier, and a download.
when_to_use: The user wants to sell books / start a membership / stand up a store.
---
<the instructions: the sequence of /api operations, the decisions, the gotchas>
```

**Why the Anthropic format, not a Tiger-specific one:** a skill folder is then **portable** — the *same repo*
works in Claude Code, the Claude API, and TigerAgent — so we consume the growing skill ecosystem instead of
inventing a format. TigerAgent's provider-agnostic adapters (Anthropic/Google/xAI/DeepSeek/Groq/…) mean the
instructions steer any model.

---

## 2. A skill pack = a module (`type: skill`) — distribution is already built

A skill is distributed as a **module** flagged `type: "skill"` in its `module.json`. One repo ships **1..N**
skills (a `skills/` dir), exactly as a `code` module ships N snippets. This buys the *entire* distribution
chain for free — **no reinvention**:

- **Install from GitHub** — `Tiger_Module_Installer` (release tarball for a pinned tag → SHA-verify →
  zip-slip-guarded extract → `application/modules/skill-<name>/`). Same "paste a public repo URL" path as any
  module (MARKETPLACE.md §6a).
- **Discover in the catalog** — `Tiger_Module_Registry` (multi-source), badged by the `skill` type filter in
  the Module Manager.
- **Update** — `Tiger_Update_Checker` diffs installed-vs-latest → update badges → one-click update via the
  installer. `type:skill` is a *search label*; the **`skills/` dir is the capability** (capability-detection,
  per THEMES.md).

Nothing about a skill's *distribution* is new — it's a module whose payload happens to be `SKILL.md` files.

---

## 3. Discovery + activation — mirror the Code-module active-set

Skills are **discovered live from files, never copied to a DB** (the same source-of-truth discipline as code
snippets and theme components). A new static class **`Tiger_Agent_Skills`** mirrors `Tiger_Code_Modules`:

- Globs `{APPLICATION_PATH,TIGER_CORE_PATH}/modules/*/skills/*/SKILL.md`, **skipping any module in
  `Tiger_Model_Module::inactiveSlugs()`** (only active packs contribute), and parses each frontmatter.
- The **active set is one config value** — `tiger.agent.skills = "author-storefront,seo-audit,…"` (the
  live-override tier, effective next request, config-discipline — *not* a `wp_options`-style table, *not* a
  new schema). `setActive($key,$on)` flips a key; `body($key)`/`resources($key)` read the file on demand.
- `entries()` returns `{key, name, description, when_to_use, module, path}` for the admin list + the loader.

So a skill has **no DB row**; "what's active" is the only state, and it's a config key. Install ≠ activate:
installing a pack surfaces its skills **inactive**; activation is a deliberate toggle.

---

## 4. Runtime — progressive disclosure into the agent turn

The model remembers nothing between turns (TIGERAGENT.md §5b); context is assembled per turn. Skills plug in
with **progressive disclosure** so you can have 50 installed and pay for only the one that's relevant:

1. **Always in the system prompt (cheap):** the active skills' **name + description + when_to_use** — a short
   menu, one line each. The model sees *what skills exist and when to reach for them*, nothing more.
2. **On demand (a read-tool):** a `load_skill(name)` tool — a **Scout-side read**, no permission gate (it only
   reads a shipped file) — pulls the **full `SKILL.md` body + referenced resources** into context when the
   model decides the skill applies. Same move Claude Code makes with its skill tool.

That's the whole loader: a menu in the prompt + a read-tool for the body. It rides `Tiger_Agent_Loop` and the
existing tool-registry seam; no new execution machinery.

---

## 5. The safety model (the load-bearing section, like CODE.md §5 / TIGERAGENT.md §8)

**A skill can't exceed the agent's role.** Because capability is the ACL-gated `/api` tool surface executed as
the current identity, an instructions-only skill is **behavioral** — it changes *which permitted tools the
agent chooses and in what order*, never *what it's allowed to touch*. The deny-by-default ACL is still the
wall. So the native, safe skill is **instructions-only**, and it's the ~90% case.

**Two tiers, stated honestly:**

| Skill tier | What it ships | Risk | Gate |
|---|---|---|---|
| **Instructions-only** (native, default) | `SKILL.md` + reference docs | *behavioral* — bounded by the ACL; prompt-injection is the residual risk | activation is admin+; read-the-`SKILL.md`-before-activate; public-repo registry review |
| **Script-bundling** (portability with the ecosystem) | `SKILL.md` + executable scripts | **RCE-by-design** — runs code outside the ACL/identity model | the **full Tiger Code trust model** (CODE.md §5): superadmin-only, read-before-run, `php -l` compile-gate, public-repo review; flagged as the higher-trust tier |

The rule of thumb, and the reason this stays safe: **new *capability* belongs in a module** (an `/api` service
→ an ACL-gated tool), **new *know-how* belongs in a skill** (instructions over tools the identity already
holds). Support script-bundling for portability, but gate it exactly like Tiger Code and surface the tier in
the UI — never pretend a code-bearing skill is as safe as an instructions-only one.

---

## 6. The admin surface — an "Agent" section (Skills is the first tab)

Skills get an admin home via the zero-code nav path (a module's `navigation.ini` → `Tiger_Admin_Nav`,
ACL-filtered). Rather than a bare top-level "Skills", they live under an **"Agent"** section alongside the
existing agent admin, because Skills and MCP are two facets of one thing — *extending the agent*:

```
Agent
├─ Conversations        run history (exists)
├─ Skills               installed SKILL.md packs — activate/deactivate, view source, update badges
├─ MCP  ▸ Connections   OUTBOUND: connect to external MCP servers → their tools join the agent's toolset
│       ▸ Server/Access INBOUND: expose THIS install's /api to external agents + scoped tokens
└─ Settings             providers, keys, auto-mode
```

The **Skills** screen is modeled on the Code Area (CODE.md §6): a list of installed skills (name, description,
source pack, an **active toggle**, a **View source** of the `SKILL.md` before activating), update badges from
`Tiger_Update_Checker`, and the install≠activate≠update discipline. Built per [ADMIN.md](ADMIN.md).

---

## 7. MCP — the sibling axis (and why it's thin)

Skills and MCP are the two ways to *extend the agent*:

- **Skills add know-how** (instructions that steer the tools it has).
- **MCP-Connections add tools** (an external MCP server's tools become agent tools — outbound/client).
- **MCP-Server exposes Tiger** (inbound: external agents — Claude Desktop, Cursor, ChatGPT — drive *this*
  install through the same permission-gated surface the in-app agent uses).

**Why MCP is a thin build, not a new engine — the key realization:** *almost everything in Tiger is the one
`/api` message endpoint.* An external client already has everything it needs to drive Tiger — it just
**authenticates and relays the same `/api` calls the browser makes**. Two auth paths, both already present:

- a **stateful session** (cURL `/auth/login` → keep the session, POST `/api`), or
- the **stateless `Authorization: Bearer tgr_…` token** (the personal-access-token credential — no session,
  request-only identity).

Either way the gateway resolves the identity and runs the **same ACL + the same services**. So an MCP server
is a **thin adapter**: it reflects the ACL-allowed `/api` surface into an MCP tool list (which
**`/api/openapi` already emits, role-filtered** — WEBSERVICES.md §9) and proxies each MCP tool call to `/api`.
**MCP adds reach, not capability; the ACL does the gating.** No new execution layer, no duplicated tools — the
same in-process `/api` dispatch the aside agent uses, fronted by a different transport.

MCP is **scoped, not built**, and sequenced later than Skills. This doc's concern is Skills; MCP is captured
here only to fix the shared "Agent" IA and this thin-adapter framing so we don't over-engineer it when we get
there.

---

## 8. Build order (phasing)

1. **`Tiger_Agent_Skills`** — the discovery class (glob active packs' `SKILL.md`, parse frontmatter, the
   `tiger.agent.skills` active-set config, `entries()`/`body()`/`resources()`). *(Mirrors `Tiger_Code_Modules`
   — the cheapest, unblocks everything.)*
2. **Loader** — inject the active skills' menu into the system prompt; add the `load_skill` read-tool.
3. **Skills admin** — the "Agent" nav section + the Skills screen (list, activate/deactivate, view source,
   update badges), per ADMIN.md.
4. **`type:skill` registry listing** + the review gate (public-repo, `skills/`-dir capability-detection);
   the script-bundling tier behind the Code trust model.
5. **(later) MCP** — the outbound Connections surface + the inbound Server/Access adapter (the thin `/api`
   relay of §7), sequenced after the CMS/commerce line.

---

## 9. Rejected alternatives (so we don't relitigate)

| Rejected | Why | Chosen instead |
|---|---|---|
| A Tiger-specific skill format | strands us from the ecosystem; every skill would need a Tiger port | the portable **`SKILL.md`** format |
| Skills as DB rows | forks the file → update-reconciliation hell (the WP "owns a row" trap) | **files discovered live** + a config active-set (§3) |
| Skills ship arbitrary executable tools by default | RCE outside the ACL/identity model | **know-how by default**; new capability = a **module** (`/api` tool, ACL-gated); script-bundling gated like Tiger Code |
| Load every active skill's full body every turn | context bloat; caps how many you can install | **progressive disclosure** — menu in the prompt, body via `load_skill` on demand |
| A bespoke skill installer/registry | duplicates the module machinery | a skill **is** a module (`type:skill`) on the existing installer/registry/updates |
| MCP as a new tool-execution engine | duplicates the `/api` surface + the ACL | MCP = **authenticated `/api` relay** (session or Bearer token); tools fall out of `/api/openapi` (§7) |

---

## 10. Open questions

- **Skill scoping** — per-org active-sets (a tenant enables its own skills) vs global? The config tier already
  supports `scope=org`, so per-org is nearly free; decide the default.
- **Skill → tool affinity** — should a skill be able to *declare* which `/api` tools it expects (a hint the
  loader uses to pre-warm the tool list), or stay purely instructional?
- **Script-bundling: support at all, or instructions-only for v1?** (Recommended: instructions-only first; add
  the gated script tier only when a real skill needs it.)
- **First-party skill pack** — ship a small starter pack (e.g. `seo-audit`, `author-storefront`) as the
  reference, the way TigerDocs was the first module?

---

*This document records decisions and their rationale. If you change a decision, update the "why" here in the
same change.*
