# Tiger — Backlog

A manual, lightweight tracker for planned features and known issues (in lieu of GitHub issues
for now).

**Workflow:** when an item ships, **delete it from this file**. If it's a user-facing
capability, add it to [FEATURES.md](FEATURES.md). Keep this list short and current — it's a
working to-do, not a changelog (git history is the changelog). The road to a stable release is
tracked separately in [ROADMAP-1.0.md](ROADMAP-1.0.md).

## Priorities (next up)

These are the pre-1.0 cluster (they touch the auth / ACL / module `@api`, so they belong in the
frozen surface — see [ROADMAP-1.0.md](ROADMAP-1.0.md)).

1. **App-level ACL — Phase 2 (maps + floor + token context)** *(design of record: [ACL.md](ACL.md)).*
   Phase 1 shipped: `Tiger_Acl_Acl::explain()` + the admin **ACL Simulator** (`/system/acl`, superadmin)
   so "why am I locked out?" is answerable. Phase 2 is the access-changing half, built carefully: named
   policy maps (`acl_map` + `map_id` storage), floor+map **composition** (floor immovable, deny-wins),
   **token→map** selection (the token from #2 carries a map/org context), **narrows-never-widens**
   enforcement, and per-tenant map authoring. *Biggest remaining item.*

2. **Stateless token auth — finish the surface** *(core built).* `Authorization: Bearer tgr_…`
   (a hashed `personal_access_token` credential) resolves identity and runs the **same ACL + services**,
   stateless (no session). Remaining: a **token-management admin screen**; the token carrying an explicit
   **org/map context** (feeds #1); **scoping** (read-only / per-service).

3. **Module dependency provisioning — the WebTigers infra** *(design of record: [DEPENDENCIES.md](DEPENDENCIES.md)).*
   Foundation built: `Tiger_Vendor` (Tier 1 Composer · Tier 2 pre-built bundle · Tier 3 raw tarball),
   the `vendor-libs/` store + bootstrap autoloading, and installer provisioning of `dependencies.php`.
   Remaining: the **Vendor Library Registry** repo + CI **bundle-builder** + published AWS/Stripe/Guzzle
   bundles (the provisioner *consumes* these); a first real Tier-2 consumer (Billing's Stripe SDK);
   conflict reporting for the one-version rule; the skeleton `.gitignore` `vendor-libs/`.

4. **cPanel/no-shell FIRST-RUN web installer + WHM plugin** *(design of record: [INSTALL.md](INSTALL.md)).*
   The install *engine* (`Tiger_Install`) + CLI (`install:admin` / `install:secrets`) + the one-click
   self-*update* all ship. The gap is **first-time** setup on a no-shell host: a browser **first-run
   wizard** — a requirements pre-flight (PHP version, extensions, INI, DB test, `var/`/`local.ini` write
   test, generated FROM INSTALL.md) → DB creds → `local.ini` → migrate → `install:admin` — gated by an
   `installed` sentinel that permanently disables it; plus a WHM/cPanel plugin (Softaculous-style) that
   lets a host offer Tiger as a near-one-button install. The "WordPress-parity, no shell" distribution play.

5. **API discovery — finish the spec** *(largely shipped).* `Tiger_OpenApi_Generator` + `GET /api/openapi`
   (opt-in `tiger.api.discovery`) + the **TigerAPIDocs** Swagger UI all ship. Remaining: **role-filtered**
   discovery (Phase 3 — you only discover what you can call), richer `data` typing, and services adopting
   `@apiRequest <Form>` for form-derived request schemas.

## Features (planned)

- **Extension model — the anti-WP-hooks design (`Tiger_Event`).** NOT WP's ~2,000 stringly-typed hooks.
  Four typed mechanisms: **ADD** (just be a module — auto-discovery), **REGISTER** (typed registries per
  surface — shortcodes today; nav items / dashboard widgets / settings panels next), **REACT** (one small
  `Tiger_Event` facade over ZF1's `Zend_EventManager` — `on`/`emit`/`filter`, ~30–50 semantic namespaced
  *declared* events, seeded with the first dozen: `user.created`, `auth.login`, `auth.login_failed`,
  `org.created`, `org.member_added`, `page.saved`, `page.published`, `module.activated/deactivated`, …),
  and **MODIFY** (service polymorphism, not filters). **Key win:** subscriptions are DECLARATIVE (module
  config, like `acl.ini`) → the Module Manager shows exactly what a module hooks/routes BEFORE install.
- **Access admin — remaining core screens.** Users + Orgs ship (the `access` module). Remaining, all core
  substrate: **org soft-delete cascade** (reparent children / handle memberships); admin **user-credential**
  actions (password set/reset, lock/unlock); a general **options registry** (declared keys → `config` UI,
  per config-discipline) that **masks secrets** (`mail.smtp.password` et al. never rendered — consider a
  `secret` flag on the `config` table and/or at-rest encryption). *(Membership/invite UX stays app-side.)*
- **SMS OTP channel** — email OTP ships; add a `Tiger_Sms` transport (a `Tiger_Mail` sibling; SNS/Twilio,
  creds in DB config) + `requestLoginCodeSms`/`verifyLoginCodeSms` reusing the channel-agnostic
  `_completeCodeLogin` (`sms_otp`). Substrate built (`auth_challenge` + the `sms` credential factor).
- **User prefs service** (`core/user/setprefs`) — `tiger.prefs.js` posts theme/skin/lang choices
  best-effort; build the endpoint to persist per-user prefs server-side.
- **Per-org theming UI** — the resolver works (an org `config` row for `tiger.skin`/`tiger.theme`); needs
  an admin screen to set it.
- **Per-org translation overrides UI** — the `translation` table supports `scope=org`; needs the
  request-time per-org layer + an admin screen.
- **Sign-in history UI** — surface the append-only `login` audit log to users/admins.
- **create-project post-install hook** — auto-symlink core assets (`_tiger`/`_theme`) on
  `composer create-project` so a fresh app renders with zero manual steps.
- **Public Billing module (Stripe)** — *deferred.* The private TigerStripe/TigerMembership line covers
  commerce today; a public, reusable **app-level** billing module (Stripe-only, org-scoped
  customer/plans/subscriptions + Checkout/Portal + webhook) remains a gap for open-source consumers.

## Issues / tech debt

- **Error pages not i18n-keyed** — `core/views/scripts/error/error.phtml` uses literal English; key it to
  `core.error.*` (the keys are already seeded in `core/languages/`).
- **`Zend_Version` secondary constant** — the "latest stable available" constant is still on an old value;
  align it in a TigerZF patch.

## Later / maybe

- **TigerDocs: resizable asides (docs "Phase 2").** Phase 1 shipped (Normal | Full-width toggle). Phase 2 =
  drag-resizable left/right asides (CSS-variable grid tracks + splitter handles + persisted widths). Polish.
- **Redis session handler** — a swap-in alternative to the DB session handler for scale.
- **Bootswatch full-look skins** — current skins are CSS-variable overlays; a per-skin full base-swap if a
  pixel-perfect Bootswatch theme is ever wanted.
- **Validator message translation** — `Zend_Validate::setDefaultTranslator` so validator messages localize.
