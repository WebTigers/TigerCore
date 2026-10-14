# AGENTS.md — working on the `matomo` module

> **Step 0 — check core before you build.** Before writing any mechanism, grep
> [`../../CAPABILITIES.md`](../../CAPABILITIES.md) — a generated, CI-checked index of every `Tiger_*` class and module.
> Core probably already has it. **Assume a capability exists until you have grepped the index and confirmed it doesn't.**

Instructions for an AI assistant (or a new contributor) working on Tiger's **matomo** module. For
platform conventions read the root **AGENTS.md** (`read.guide` with no module). This file is the
module-specific layer; match the surrounding style.

> First-party **self-hosted Matomo** integration — the "paste your Matomo URL + site id" tracker done
> the Tiger way: config-backed (no deploy), consent-aware (`Tiger_Consent`), provider-extensible
> (`Tiger_Tracking`). It emits the Matomo `_paq` snippet on public pages AND reads the `/a/<name>`
> affiliate cookie into a Matomo **visit-scoped custom dimension** so affiliate traffic is segmentable.
> This module is the **sibling of `analytics`** (GA4) — same shape, same registry, and **both can be
> active at once**.

## The one thing to know: this module only TALKS to Matomo, it is not Matomo

Matomo is a **separate GPLv3 application** (its own install at e.g. `analytics.webtigers.com`, its own
PHP pool + DB). This BSD-3 module is **never** vendored into it and never embeds its code — it only
speaks Matomo's client-side tracking API (`_paq`) and (future) its HTTP Reporting API. Keep that wall.

## Where things live (no models of its own)

- `Bootstrap.php` — registers the tag plugin (stackIndex 92), declares `matomo` to `Tiger_Tracking`,
  and lists the screen under the admin **Settings** tree (`Tiger_Admin_Settings`).
- `plugins/Tag.php` — **`Matomo_Plugin_Tag`**, the `_paq` emitter (the whole runtime).
- `controllers/AdminController.php` — `/matomo/admin` (settings), admin-only.
- `services/Matomo.php` — `Matomo_Service_Matomo::save` (writes the config tier). `forms/Settings.php`.
- `views/scripts/admin/index.phtml` + `assets/js/matomo.settings.js` (the save wiring).
- **No `models/`** — settings live in `Tiger_Model_Config` (the live-override tier).

## Config keys (all `tiger.matomo.*`, GLOBAL scope today)

- `enabled` (bool) · `url` (the Matomo base URL, trailing-slashed) · `site_id` (idsite, int) ·
  `exclude_signed_in` (bool, default on) · `affiliate.dimension_id` (the visit-scoped custom-dimension
  **index**, default 1).

## The affiliate cookie contract (TIGER-306 ↔ TIGER-303)

- **`Matomo_Plugin_Tag::AFFILIATE_COOKIE` = `tiger_aff`** holds the affiliate **name**; the plugin reads
  it, validates it against `AFFILIATE_NAME_RE` (`[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}`), and pushes
  `setCustomDimension` **before** `trackPageView`. The value is JSON-encoded before it reaches the page.
- **This module READS the cookie; it never WRITES it and never owns the `/a/<name>` route.** Writing the
  cookie (and validating the name against real affiliate records, recording the click, stamping the
  account) is **TigerAffiliates**' job (TIGER-303). When that module lands it must write **this same
  cookie name**. Until then the dimension is simply never set (set the cookie by hand to test the read).

## Conventions + gotchas (this module)

- **The tag rides a front-controller plugin** (`Matomo_Plugin_Tag`, stackIndex 92, `preDispatch`,
  emit-once) appending the `_paq` snippet to the `tigerTracking` head placeholder — which **only the
  public layout renders**, so admin traffic is never tagged. It emits only when enabled + a valid
  `http(s)` URL + a positive site id + `Tiger_Consent::allows('analytics')` (TRUE until the cookie
  feature sets a mode, so Matomo works standalone) + not excluding signed-in staff. **Fail-open** — a
  broken lookup never breaks the page render.
- **`isConfigured()` is the registry's `active` signal** — enabled AND url AND site id. The URL + id are
  validated in the plugin before ever being emitted (don't trust the stored config shape).
- **The two switches (`enabled`, `exclude_signed_in`) are NOT form elements** — the form validates only
  the URL / site id / dimension index; the switches come straight off `$params`.
- **Config scope is GLOBAL today**, isolated in `_scope()` (multi-site flips to per-org later — same as
  the analytics module).
- **Everything is `class_exists`-guarded** against a Core predating `Tiger_Tracking`/`Tiger_Admin_Settings`
  — the module still loads (degraded) on older cores.

## ACL

admin: `Matomo_AdminController`, `Matomo_Service_Matomo` (both in `configs/acl.ini`, deny-by-default).

## Do / Don't

- **Do** keep tagging consent-gated + fail-open; **do** validate the URL/site id before emitting.
- **Don't** write the `/a/<name>` cookie or add an `/a/:name` route here — that's TigerAffiliates.
- **Don't** emit the tag into admin/auth layouts (they don't render `tigerTracking` — keep it that way).
- **Don't** embed or vendor any Matomo code — HTTP/`_paq` only.
