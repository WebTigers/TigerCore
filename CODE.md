# Tiger — Code Modules & the Code Area

How the community shares **code snippets** — a single helper function or a whole class of them that
become available inside TIGER — through a `code` module, and how a user activates them in the **Code
Area**. Read this before building the snippet store, the loader, or the Code Area screen. For the
platform *why* read [ARCHITECTURE.md](ARCHITECTURE.md); for the sibling theme design read
[THEMES.md](THEMES.md); for the registry read the Vendor Registry docs; for the admin-screen template
read [ADMIN.md](ADMIN.md).

> **Status: design-of-record (proposed, not built).** This records the decisions and their rationale
> so we don't relitigate them. Current reality: the registry `type` enum includes **`code`** and the
> Add-Module directory filters by it (built). Everything below — the snippet store, the bootstrap
> loader, the Code Area screen, the security model — is the target.

---

## 0. The one principle

**A code module is a *pack of snippets*; a snippet is the *activatable unit*.** Installing a code
module never runs anything — it just surfaces its snippets in the Code Area. Activating a **snippet**
is what loads its PHP so its functions/classes/hooks become available app-wide. Two deliberate steps,
exactly like a theme (install ≠ activate).

This is the WordPress "Code Snippets" idea made first-class: a way to share small, useful (or
esoteric) helpers without standing up a whole routed module. The heavy machinery — routes, ACL,
views — is what `app`/`plugin` modules are for; `code` is the lightweight path for *just some PHP*.

---

## 1. The two levels

| Level | What | Unit of… |
|---|---|---|
| **Code module** (`type: code`) | a distribution package — one or more snippets + a manifest | **install** (registry / URL / upload) |
| **Snippet** | one self-describing PHP file: a function, a class, a set of hooks/shortcodes | **activation** (in the Code Area) |

Plus a second *source* of snippets that isn't a module at all:

| Snippet source | Where it lives | Reviewed? |
|---|---|---|
| **Module snippet** | a file in a `code` module's `snippets/` dir (from the registry/upload) | yes — the registry review + your read |
| **Local snippet** | authored in-app in the Code Area, stored in a DB row | no — it's *your* code |

Both surface in the Code Area and activate identically. Module snippets are the **community-sharing**
path; local snippets are the **write-your-own-helper** path (the core WP-Snippets UX). One screen,
two origins.

---

## 2. What a code module ships

```
modules/code-<name>/
  module.json           ; type=code, slug, version, requires
  TIGER.md              ; human description (the registry "View more")
  snippets/
    <id>.php            ; ONE snippet — a self-describing PHP file (header hint + body)
  README.md
```

A **snippet file** is self-describing via a leading comment hint — the same convention as TigerDocs'
`tiger:doc`, the theme's `tiger:page`/`tiger:block`:

```php
<?php
// tiger:snippet id="slugify" label="Slugify" category="String" scope="global"
//   description="A slug() helper that turns any string into a URL-safe slug."

if (!function_exists('slug')) {
    function slug(string $s): string { /* … */ }
}
```

- The **hint** (`id`, `label`, `category`, `scope`, `description`) drives the Code Area listing and
  search. `scope` = `global` (available everywhere) or `admin` (only in admin requests) — the loader
  decides when to include it.
- The **body** is ordinary PHP that *defines* things (functions, classes, `Tiger_*` registrations —
  a shortcode, a hook, a service helper). It should be **idempotent and side-effect-light on load**:
  define, don't *do*. Guard definitions (`function_exists`/`class_exists`) so a name collision degrades
  to a reported conflict, not a fatal redeclare.

No `snippets/` folder ⇒ it isn't a code module; `type:code` is a search label, the `snippets/` dir is
the capability (capability-detection, per THEMES.md).

---

## 3. The store & activation state

Snippets are files (module) or rows (local); **what's *active* is data.** A `code_snippet` table (the
standard-columns model) is the source of truth:

| Column | Meaning |
|---|---|
| `snippet_id` | UUID PK |
| `source` | `module` \| `local` |
| `module_slug` | the owning code module (null for local) |
| `ref` | snippet id from the hint (module) or a slug (local) |
| `label` / `category` / `scope` | denormalized from the hint / the editor |
| `body` | the PHP — **only for `local`** snippets (module snippets stay on disk) |
| `source_hash` | SHA-256 of the snippet body **at activation** (integrity — see §5) |
| `active` | `TINYINT(1)` |
| standard columns | `status`, `created_by`, `created_at`, … (who activated what, when) |

Activation is `active = 1` + a stored `source_hash`; it's the **live-override pattern** (a DB tier
effective next request), the same shape as config/translations. This is *not* a `wp_options` blob —
it's a declared table with one row per snippet (config-discipline).

---

## 4. The loader (bootstrap)

A `_initSnippets()` step (in the app Bootstrap, after autoload, before dispatch) loads the active set:

1. Query active snippets for the current **scope** (`global` always; `admin` only on admin requests).
2. For each: verify integrity (`source_hash`, §5), then make its code available —
   - **module** snippet → `require_once` its `snippets/<ref>.php`.
   - **local** snippet → write the `body` to a per-server cache file and `require_once` it (never
     `eval` — a real file is debuggable, opcache-cacheable, and shows in stack traces).
3. **Fail-soft:** a snippet that fails integrity or throws on load is **auto-deactivated + logged**
   (`Tiger_Log`), never allowed to take the request down. (Parse errors are fatal and *not* catchable,
   which is why activation parse-checks first — §5.)

Snippets load into the **global scope** on purpose — the whole point is to expose `slug()` app-wide.
That means no true isolation; collisions are handled by the `function_exists` guard + a conflict
report, not by namespacing (which would defeat the feature).

---

## 5. Security — the load-bearing section

**Activating a snippet runs community PHP inside your app. There is no true PHP sandbox.** This
feature makes trust **explicit and informed**; it does not make untrusted code safe. Every guardrail
below is about *accountability and consent*, and the doc is honest that the residual risk is real —
the same risk WordPress "Code Snippets" carries, with more rails.

1. **Superadmin-only.** Installing a code module *and* activating any snippet is `superadmin` (ACL,
   deny-by-default). Never a lower role.
2. **No auto-activation, ever.** Install surfaces snippets *inactive*. Activation is a separate,
   deliberate click — the install ≠ activate rule.
3. **Read-before-you-run.** The Code Area shows the snippet's **full source inline**, and activation
   requires an explicit *"I've read this code and trust it"* confirmation. No blind activation.
4. **Static red-flag scan at activation.** A token pass **blocks or hard-warns** on the obvious
   footguns before activation: `eval`, `exec`/`shell_exec`/`system`/`passthru`/`proc_open`, backticks,
   `create_function`, `include`/`require` of a *dynamic/remote* path, `base64_decode`→`eval`,
   variable-variables/variable-functions fed by request input. Not foolproof — a determined author
   evades it — but it stops the careless and the obvious.
5. **Parse-check.** Reject a snippet that doesn't `php -l` cleanly, so a broken file can't fatal on
   load.
6. **Integrity pinning.** The `source_hash` is captured at activation; on every load the file/body is
   re-hashed. A mismatch (a tampered update, a swapped file) **auto-deactivates + flags** it — code
   can't silently change under you after you approved it.
7. **Registry review applies, hardest.** Code modules go through the same public review; the AI pass
   already scans for obfuscation / eval-of-remote / exfiltration / backdoors, and a `code` listing
   gets a prominent **"runs in-process"** caution on its directory card + the strictest bar.
8. **Audit trail.** Every install/activate/deactivate is logged (who, when, hash) via `Tiger_Log`.

The honest summary: **this is trusted code-sharing behind superadmin + informed review + integrity —
not a sandbox.** Ship it with that framing in the UI, not a false sense of safety.

---

## 6. The Code Area (admin screen)

A first-party admin surface (`/system/code`, built per [ADMIN.md](ADMIN.md)):

- **List**, grouped by source module (+ a "Local snippets" group). Each row: label · category · scope,
  an **active toggle**, **View source** (the PHP, read before you trust), and the conflict/integrity
  state.
- **Filter/search** the snippet list; a category facet.
- **Local snippet editor** — a code editor (the CMS already vendors CodeMirror) to author/paste PHP,
  name it, set scope, and save; activation runs the same red-flag scan + parse-check.
- Toggling posts to `/api` (`Code_Service_*`, validate → transaction), effective next request. The
  save/activate uses the house UI primitives (`TigerButton`, `TigerDOM`) — see AGENTS.md.

Whether the Code Area is a screen in the **System** module or its own first-party **`code` module** is
an implementation call; it's a first-party surface either way.

---

## 7. Registry integration

- **`type: code`** in the listing (done — the schema enum + the Add-Module type filter).
- A code module installs through the **same path** as any module (`module.json` + `TIGER.md`, the
  `installFromUrl`/`installFromUpload`/registry flow). Post-install, `Tiger_Snippet_Discovery` scans
  its `snippets/` and the snippets appear in the Code Area, inactive.
- The directory card shows the **Code** badge and a **"runs in-process — review before activating"**
  caution; "View more" surfaces the snippet inventory.

---

## 8. Rejected alternatives (so we don't relitigate)

| Rejected | Why | Chosen instead |
|---|---|---|
| `eval()` snippet code | no integrity, no debuggability, no opcache, hidden in stack traces | write to a real file, `require_once`, hash-pinned |
| Auto-activate on install | surprising + dangerous — runs code you haven't read | install surfaces *inactive*; explicit activate (§5.2) |
| A full routed module per helper | too heavy for "just a `slug()` function" | that's what `plugin`/`app` are; `code` is the light path |
| Namespaced/isolated snippet scope | defeats the feature (the point is `slug()` *app-wide*) | global scope + `function_exists` guard + conflict report |
| A `wp_options`-style blob of active ids | not queryable, grab-bag | a declared `code_snippet` table (config-discipline) |
| "It's sandboxed, so it's safe" framing | false — PHP isn't sandboxable here | superadmin + read-before-run + integrity, stated honestly |

---

## 9. Build order (phasing)

1. **`code_snippet` store** (migration) + the active-set + `source_hash`.
2. **Snippet discovery** — scan a code module's `snippets/` + parse the `tiger:snippet` hint; the
   local-snippet model.
3. **The loader** — `_initSnippets()`: scope-aware, integrity-verified, fail-soft `require_once`.
4. **The Code Area** — list + activate/deactivate + View source + the local editor (CodeMirror).
5. **Security hardening** — red-flag token scan, parse-check, hash auto-deactivate, audit log, the
   directory "runs in-process" caution.
6. **Registry polish** — `type:code` inventory in "View more", the review-bot's stricter code bar.

---

## 10. Open questions (decide before Phase 2/3)

- **Scope beyond global/admin?** e.g. a `cli` scope for console helpers, or per-module scoping.
- **Dependencies/ordering** — may a snippet declare it needs another loaded first?
- **Provided-API discovery** — should a snippet's public functions feed the docblock reference
  generator, so a code module self-documents what it adds?
- **Export a local snippet → a code module** — an "author here, share to the registry" on-ramp?
- **Update semantics** — when a code module updates, re-review + re-hash each activated snippet (the
  integrity check forces a re-consent); confirm that's the desired friction.

---

*This document records decisions and their rationale. If you change a decision, update the relevant
section here in the same change — the "why" is the most valuable and most perishable part.*
