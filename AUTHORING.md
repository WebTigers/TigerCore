# Tiger — Authoring: how a page gets built (human & agent)

How someone — a person clicking around, or an AI agent calling `/api` — builds a page in Tiger, from
nothing or from a starting point, and where every piece is stored. Read this before touching the CMS
authoring surface, the theme-fork flow, or the starter-content library. For the theme-material
architecture read [THEMES.md](THEMES.md); for the composition primitives read the CMS section of
[FEATURES.md](FEATURES.md); for the `/api` contract read [WEBSERVICES.md](WEBSERVICES.md).

> **Status: design-of-record (partly built).** Built today: the `page` store (page/layout/partial/block),
> the visual builder, the [partial]/[content] composition primitives, the in-context partial editor,
> Blocks (copy-in fragments), and `forkTheme` for theme **pages**. Proposed here: the same fork for theme
> **layouts/partials/blocks** (§4/§6 #1), a core Bootstrap-5 starter library (§3.2, #2), a blank base
> (§1, #3), and the agent authoring skill (§5, #4). Where a section says "proposed," it isn't built yet.

---

## 0. The one principle

**Whatever you start from, the artifact is always a row in the page store** — a `page`, `layout`,
`partial`, or `block`. The three ways to begin a page differ *only in how that first row is seeded*.

The load-bearing consequence: **a human clicking "New" and an AI agent calling `/api` produce the
identical artifact through the identical door** (`Cms_Service_Page::save`). The agent is not a second
code path — it writes the same rows the UI writes, validated by the same form, gated by the same ACL.
That is what makes authoring both **UI-agnostic** (any front-end renders the rows) and **agent-native**
(the agent has nothing bespoke to learn beyond the row shape). You never build the workflow twice.

So the real question is never "how does this scenario work?" — it's **"where does the starter row come
from?"** There are exactly three sources: **blank**, a **core Bootstrap-5 starter**, or a **theme**.

---

## 1. The two layers (the UI-agnostic seam)

Getting this boundary right is what keeps a theme swap from breaking pages, and lets a layout "contain
nothing in the head, or whatever the author desires":

| Layer | Owns | Lives as | Injection code? |
|---|---|---|---|
| **The shell** | `<!doctype><html><head>…</head><body>` + assets + **all injection points** (SEO/analytics/consent/code-inject), the header/footer *placement*, scripts | a **theme file** (`layouts/scripts/*.phtml`) | all of it |
| **The content-region layout** | what goes **inside `<main>`** — full-width, sidebars, columns: `[content]` + `[partial]` (aside) slots | a **CMS `layout` row** (`type=layout`) | **none — ever** |

- **A `layout` is a *content-region* template, not a whole page.** It renders **inside** the shell's
  `<main>` — `Tiger_Cms_Renderer` wraps the page body in it, and `PageController::viewAction` no longer
  treats a `layout_key` as a self-contained document (that behavior is retired). So a CMS user composing
  a layout (full-width, sidebar-left/right, two-sidebar) never sees the shell plumbing, and a layout is
  structurally incapable of carrying injection code.
- **Header/footer are chrome the *shell* renders**, in the theme's view scope — they need `themeAssets`,
  the nav helper, and the auth placeholders, which a CMS render can't supply. Their *content* is editable
  via the partial editor; they simply aren't re-placed per layout. That keeps the dynamic chrome working
  while the layout owns the content region.
- PUMA ships the starter set as forkable `tiger:layout` files: **Full Width · Sidebar Left · Sidebar
  Right · Two Sidebars** (+ a `Sidebar` partial).
- **Core emits no shell.** The shell is a theme concern; Core emits *data* (rows) + semantic default
  views (ARCHITECTURE §9). A `type=layout` row **never** owns `<html>`/`<head>` — it's the content
  region, rendered inside the active theme's shell.
- **Per-page head control already exists.** The page's `head_html` / `body_scripts` fields fill the
  shell's `pageHead` / `pageScripts` slots (THEMES §8a). "Nothing in the head" = leave them empty;
  "whatever you desire" = fill them. No new mechanism.
- **From-nothing wants the leanest shell.** For a true blank start we ship a **blank / Bootstrap-only
  base** — a shell that loads only Bootstrap and exposes the head/content slots, imposing no opinion
  (proposed, #3).

---

## 2. The store — four primitives + the composition seam

One table (`page`), discriminated by `type`. Everything an author starts from or drops in is one of
these rows:

| `type` | Is | Placed / reached by | Editable how |
|---|---|---|---|
| **page** | routed content (a URL) | its `slug`; wraps in its `layout_key` layout | visual builder or text |
| **layout** | a body skeleton (chrome composition) | a page's `layout_key`; body = `[partial]…[content]…[partial]` | text/shortcode (visual later) |
| **partial** | a synced **reference** fragment (header, footer, CTA) | `[partial name="x"]` — an *immutable placeholder* on the page; edited only in its own editor; every placement updates | in-context partial editor |
| **block** | a **copy-in** fragment (hero, pricing section) | dropped from the builder's *My Blocks* palette → its HTML is *inlined into the page*, detached | visual builder (its master) |

The **two composition primitives** are shortcodes the renderer expands (`Tiger_Cms_Renderer`):

- **`[content]`** — the page-body slot inside a layout (a layout is "a partial with a content hole").
- **`[partial name="x"]`** — transclude a partial by reference (recursive, cycle/depth-guarded).

Partial vs Block is the **synced-reference vs detached-copy** distinction — see the CMS-partials note +
THEMES.md. A block is a *builder library source only*; it is never resolved at render time (there is no
`[block name]` shortcode).

---

## 3. Three ways to seed the first row

### 3.1 From nothing

New → **Layout** (blank): body = `[content]` plus any `[partial]` placeholders you want; New → **Page**
bound to it (`layout_key`). Both are rows in the store; the page's head lives in its `meta`
(`head_html`/`body_scripts`). Reach the leanest possible shell with the blank base (#3). The agent does
the identical thing over `/api` — write a `layout` row, then a `page` row that references it.

### 3.2 From core Bootstrap-5 starters (proposed, #2)

PUMA ships a small **library of stock Bootstrap-5 layouts/sections** as files — holy-grail, sidebar,
landing, hero+features — **pure Bootstrap, no custom CSS/JS**. **"New from starter"** forks one into an
editable `type=layout` (or `page`) row *with `[content]`/`[partial]` placeholders already placed*. This
is *literally the same mechanism as §3.3* — it is "fork the **default** theme's stock layouts." §3.2 and
§3.3 are one feature pointed at two sources (core vs installed theme).

### 3.3 From a theme (fork templates)

The **active** theme (e.g. Porto once activated) ships layouts / partials / components as files; the
content admin lists them as forkable **templates**; **Customize** forks one into the matching editable
row (`layout`/`partial`/`block`/`page`). Theme menus → forkable menu rows. **Only the active theme
surfaces** — an installed-but-inactive theme has no asset symlink (created on Activate, removed on
Deactivate), so its content can't render and never appears; activate a theme to fork its templates. A
forked **page** bakes the theme's stylesheet links into its head, so it self-loads its origin theme's
CSS (reachable via that theme's symlink) and renders correctly even if a *different* theme is later
activated — going dark only if the origin theme is deactivated. This is THEMES.md's Tier-1-files →
Tier-3-rows path (§4a), with **fork-on-edit provenance** (`source`/`source_key`/`source_slug`/`forked`,
§4b) so a theme *update* refreshes only the copies you have not touched — non-destructive, the thing
WordPress cannot do.

---

## 4. The unifying primitive

**One store · one "fork a file into an editable row" operation · three starter sources (blank /
core-BS5 / theme).** Everything downstream — the visual editor, the placeholders, the render pipeline —
already exists and is shared. We are not building three workflows; we are building **one fork-to-row
primitive** and pointing it at three sources.

`forkTheme` (built, pages only) is that primitive. Generalizing it needs three parallel reads on
`Tiger_Theme` (it has `pages()`/`page()` today) and a `kind`/`type` on the fork service that maps to the
right `TYPE_*` and derives `slug` (pages) or `page_key` (layouts/partials/blocks). The provenance columns
(THEMES §4b) let a fork remember its origin so "revert to theme default" and non-destructive theme
updates work — no schema gymnastics.

---

## 5. The agent path (same door, one skill)

Because §3.1–§3.3 all reduce to "seed a row, then edit it," the agent's whole job is **"write that
row"** — through the same `Cms_Service_Page::save` the UI uses. What the agent needs is a focused
**authoring skill**: the four `type`s, the `[content]`/`[partial]` placeholder conventions, the format
choices (html/markdown/phtml/builder), and the save contract. That skill feeds straight off the existing
docblock **reference generator** + [AGENTS.md](AGENTS.md), and is the same surface the **TigerMCP** scope
exposes to external agents. "The agent builds my pages" is the *easy* half precisely because the human
workflow already normalized everything to rows.

---

## 6. Build order (phasing)

1. **Surface theme layouts (+ partials/blocks) as forkable templates** — generalize `forkTheme` + the
   "Theme Templates" tab beyond pages. **Fixes the concrete gap (a theme layout you can't edit), and is
   the foundation §3.2/§3.3 reuse.** ← *start here.*
2. **The PUMA Bootstrap-5 starter library + "New from starter"** — same fork primitive, core-provided
   sources; the from-a-skeleton path.
3. **The blank / Bootstrap-only base** — the leanest shell for from-nothing.
4. **The agent authoring skill** — the row model + save contract as a skill/doc (ties to the reference
   generator + TigerMCP).

---

## 7. Rejected alternatives (so we don't relitigate)

| Rejected | Why | Chosen instead |
|---|---|---|
| A `type=layout` row owns `<html>`/`<head>` | couples content to a shell → the theme-swap break | shell is a theme file; the layout row is the *body* skeleton (§1) |
| A separate agent authoring API | two code paths to keep in sync; drift | agent writes the *same rows* via the *same* `/api` (§0/§5) |
| Theme layouts stay locked in files | the author can't start from them (the gap) | fork a theme file into an editable row (§3.3/§4) |
| A distinct "starter" store/table | schema sprawl; a second thing to render | starters are just files forked into ordinary `page` rows (§3.2) |
| Theme update overwrites edited rows | destroys the author's work | fork-on-edit provenance — updates skip `forked=1` (THEMES §4c) |

---

*This document records the authoring workflow and its rationale. If you change a decision, update the
"why" here in the same change — it's the most valuable and most perishable part.*
