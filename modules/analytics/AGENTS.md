# AGENTS.md — working on the `analytics` module

> **Step 0 — check core before you build.** Before writing any mechanism, grep
> [`../../CAPABILITIES.md`](../../CAPABILITIES.md) — a generated, CI-checked index of every `Tiger_*` class and module.
> Core probably already has it. TigerImage once reimplemented `Tiger_View_Helper_I18n` by hand because this
> hop was skipped, and lost a property core had designed in. **Assume a capability exists until you have
> grepped the index and confirmed it doesn't.**

Instructions for an AI assistant (or a new contributor) working on Tiger's **analytics** module. For
platform conventions read the root **AGENTS.md** (`read.guide` with no module). This file is the
module-specific layer; match the surrounding style.

> First-party **Google Analytics (GA4)** — the WordPress-parity "paste your tracking ID" feature done
> the Tiger way: config-backed (no deploy), consent-aware (`Tiger_Consent`), provider-extensible
> (`Tiger_Tracking`). It emits gtag.js on public pages AND offers in-app GA4 reporting (OAuth connect,
> a reports dashboard, a "Traffic" dashboard widget). This module owns only GA; the cookie banner is a
> separate feature.

## The analytics hub: provider choice (1.61)

This module is also where an org **chooses its reporting provider** — `Tiger_Analytics` (core library),
a name → adapter registry like `Tiger_Location`. Built-in adapters: `ga` (wraps `Tiger_Google_Analytics`)
and `matomo` (Matomo Reporting API). Add one with `Tiger_Analytics::register('name', 'Class')`.

- **Setting:** `tiger.analytics.provider` (org-scoped) = `''` | `ga` | `matomo`. **Unset resolves
  automatically**: GA connected → `ga`; else Matomo with a token → `matomo`; else none. Never compare a
  stored value directly — call `Tiger_Analytics::provider($orgId)`.
- **Normalized report** (`Tiger_Analytics::report($orgId, ['days' => 28])`): `totals {visits, visitors,
  pageviews, bounce_rate, avg_time}` (null = not reported), `series [{date, visitors, pageviews}]`,
  `top_pages [{label, hits}]`, `top_referrers [{label, visits}]`, or `{error, code}`. The widget and the
  dashboard render ONLY this shape — never a provider's raw response.
- **Tags are independent.** The choice is setup + reporting. GA's tag (`tiger.analytics.enabled`) and
  Matomo's tag (`tiger.matomo.enabled`) keep their own switches; a site may tag with both.
- **Matomo is set up here, once.** The Matomo panel renders the matomo module's OWN form via the shared
  partial `matomo/views/scripts/admin/_matomo-fields.phtml` and posts to `Matomo_Service_Matomo::save`;
  `/matomo/admin` redirects to `/analytics/admin?provider=matomo`. Don't add a second Matomo form.
- **Credentials are org-level only** — the Matomo token (`tiger.matomo.token_auth_enc`) and GA's refresh
  token / BYO secret: the org's own row, plus the install tier for the install's own org. Adapters read
  them through `Tiger_Analytics_Adapter_Abstract::_secret()`; the screen gets `hasToken`, never a value.
- **One CSRF token on the screen for analytics forms**: `Analytics_Form_Settings` and
  `Analytics_Form_Provider` share the salt `Analytics`; the provider choice posts the GA form's `_csrf`.

## The one thing to know: two distinct GA surfaces

1. **Tagging** — the client-side gtag.js snippet; needs only a `measurement_id`, **no OAuth**.
2. **Reporting** — server-side GA4 Data API pulls (dashboard + widget); needs an OAuth *connection* via
   `Tiger_Google_Analytics`.

A site can tag without reporting. Don't conflate them.

## Where things live (no models of its own)

- `controllers/AdminController.php` — `/analytics/admin` (settings), `/connect`, `/callback`,
  `/disconnect`, `/dashboard` (all admin). OAuth state in a `Zend_Session_Namespace`.
- `services/` — `Analytics` (settings + `saveProvider`), `Reports` (read-only). `forms/Settings.php`,
  `forms/Provider.php`.
- `plugins/Tag.php` (the gtag emitter), `widgets/Traffic.php` (the provider-agnostic dashboard card;
  `widgets/Ga.php` is its deprecated alias) + `assets/js/analytics.widget.js`.
- **No `models/`** — `Tiger_Model_Config` + `Tiger_Google_Analytics` (OAuth/reporting engine) +
  `Tiger_Consent`/`Tiger_Tracking`/`Tiger_Dashboard`.

## The `/api` surface (admin)

- **`Analytics_Service_Analytics`** — `save`: write `tiger.analytics.enabled` /
  `.ga4.measurement_id` / `.exclude_signed_in` (config, GLOBAL) + (guarded) the reporting mode + OAuth
  creds + property id via `Tiger_Google_Analytics::saveMode()`/`saveOauthConfig()`.
- **`Analytics_Service_Analytics::saveProvider`** — writes `tiger.analytics.provider` at the caller's
  org (`Analytics_Form_Provider`; an unregistered provider is a form error).
- **`Analytics_Service_Reports`** (read-only) — `report` (the NORMALIZED report from the org's provider;
  on failure `data.code` = `no_provider` | `not_configured` | `reconnect_required` | `unreachable` |
  `auth` | `error`), `summary` (the raw GA4 summary, kept for compatibility), `test` (GA's connection
  self-test, or `provider=matomo` for that provider — **note: a failed connection is still `result=1`;
  the client reads `data.test.ok`**).

## Conventions + gotchas (this module)

- **Broker vs BYO OAuth.** *Broker* mode uses the WebTigers-hosted broker with **PKCE** (verifier stays
  server-side; only the challenge + `?handoff` travel) and needs just a GA4 property id. *BYO* mode
  runs Google's flow against the operator's own client id/secret (secret encrypted; blank on save keeps
  the existing one) with a `state` nonce checked via `hash_equals`. Redirect URI is always
  `/analytics/admin/callback`.
- **The tag rides a front-controller plugin.** `Analytics_Plugin_Tag` (stackIndex **92**,
  `preDispatch`, emit-once) appends gtag.js to the `tigerTracking` head placeholder — which **only the
  public layout renders**, so admin traffic is never tagged. It emits only when enabled + a valid
  `G-XXXX` id + `Tiger_Consent::allows('analytics')` (TRUE until the cookie feature sets a mode, so GA
  works standalone) + not excluding signed-in staff (`exclude_signed_in` default on). **Fail-open.**
- **Reporting is read-only** — GA's summary is file-cached (Matomo is not: self-hosted, no quota); the
  `Analytics_Widget_Traffic` card (id `analytics.traffic`) fetches `reports.report` **async over `/api`**
  (days=28) so a cold provider call never blocks the dashboard render; with no provider it shows
  "Choose an analytics provider", and a chosen-but-unset provider shows "Finish setting up …".
- **The two switches (`enabled`, `exclude_signed_in`) are NOT form elements** — the form validates only
  `ga4_measurement_id` (`^G-[A-Z0-9]{4,}$`); the switches + OAuth fields come straight off `$params`.
- **Config is per org (TIGER-511)**: `_scope()` and `Tiger_Google_Analytics` write at the caller's org
  (global = install-wide fallback); the screen + reporting read `Tiger_Model_Config::currentValue()`, the
  tag reads `siteValue()`.
- **Everything is `class_exists`/`method_exists`-guarded** against a Core predating
  `Tiger_Tracking`/`Tiger_Dashboard`/`Tiger_Google_Analytics` — the module still loads (degraded) on
  older cores.
- **Registration:** `Bootstrap` registers the tag plugin, the tracker (`Tiger_Tracking::register`), the
  Traffic widget (`Tiger_Dashboard::registerWidget`), a Settings-tree entry, AND a top-level nav item
  via `configs/navigation.ini` (`/analytics/admin/dashboard`).

## ACL

admin: `Analytics_AdminController` (covers connect/callback/disconnect/dashboard),
`Analytics_Service_Analytics`, `Analytics_Service_Reports`.

## Do / Don't

- **Do** keep tagging consent-gated + fail-open; **do** fetch reports async so the dashboard never
  blocks.
- **Don't** treat `test()`'s `result=1` as "connected" — read `data.test.ok`.
- **Don't** render a provider's raw response in the widget/dashboard — add the mapping to its adapter.
- **Don't** read a credential with `currentValue()`/`orgValue()` — that falls back to the install tier
  for every org; use the adapter's `_secret()`.
- **Don't** emit the tag into admin/auth layouts (they don't render `tigerTracking` — keep it that way).
