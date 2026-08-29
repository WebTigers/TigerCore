# Tiger — Backlog

A manual, lightweight tracker for planned features and known issues (in lieu of GitHub issues
for now).

**Workflow:** when an item ships, **delete it from this file**. If it's a user-facing
capability, add it to [FEATURES.md](FEATURES.md). Keep this list short and current — it's a
working to-do, not a changelog (git history is the changelog). The road to a stable release is
tracked separately in [ROADMAP-1.0.md](ROADMAP-1.0.md).

## Priorities (next up)

The next-up features. **None block the 1.0 launch** — per [ROADMAP-1.0.md](ROADMAP-1.0.md) the only
launch gate is the no-shell web installer; these ship continuously, whenever a use case pulls them.

1. **Advanced ACL — one model: grants cascade UP, context narrows DOWN** *(design of record:
   [ACL.md](ACL.md) = the floor/maps/token half; `~/Desktop/Update Zend ACL.txt` + Claude memory = the
   groups/individual half — **fold into one doc when this is built**).* Today's ACL is **role-only**
   (deny-by-default, role on `org_user`, resolved live). Two extensions were designed separately; they're
   the **two axes of ONE model**, sharing one rule shape (resource = class · privilege = action ·
   permission = allow|deny · org-scoped · owned by exactly one of role | group | user | map):
   - **Grants build UP — the subject cascade** (most-specific-wins): `role → + groups → + individual
     overrides`. *"What is this user granted?"* Tables `acl_group`/`_member`/`_rule` + `acl_user_rule`; a
     generic `Tiger_Acl_Resolver` (mechanism) + `Tiger_Acl_Acl` (loader); the entry point widens to
     `isAllowed($role, $resource, $privilege, $user = null)` (back-compat: `$user = null` = today's path).
     The real work is **threading `$user` through the two enforcement seams**
     (`Tiger_Controller_Plugin_Authorization` + `Tiger_Ajax_ServiceFactory`), not the tables.
   - **Context narrows DOWN — floor + maps + token** (deny-wins, never widens): the immovable **floor**
     (the platform ACL) composed with a selected **map** (a named policy set — `acl_map`, scope
     platform|app|tenant; rules carry `map_id`, null = floor). A **token** or the org default *selects* a
     map and can only **narrow**. *"What survives in this context?"* Feeds the token-auth item (#2).
   - **One resolver, one request:** the subject cascade → intersect the active map, bounded by the floor →
     decision; **`explain()` traces every layer** (role → group → individual → map → floor → final).

   **Shipped (Phase 1):** `Tiger_Acl_Acl::explain()` + the admin **ACL Simulator** (`/system/acl`) on
   today's single ACL — the "why am I locked out?" trace. **Decide before building:** (a) which axis first —
   a real need pulls it (*groups* = "give this user extra / a read-only group"; *maps* = API-token
   least-privilege + per-tenant policy); (b) **deny-precedence** — proposed model: the *cascade* is
   most-specific-wins (an individual override can beat a group), but the *floor/map* layer is
   **deny-wins + immovable** (a map/token can never un-gate a floor deny); the two don't conflict because
   they act at different stages — confirm this. *Biggest remaining item.*

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
- **Comments, ratings & reviews — the last WP-parity gap** *(design of record:
  [COMMENTS.md](COMMENTS.md)).* One core module, **off by default**, where **a review IS a comment
  with a rating** — one `comment` table with a nullable `rating`, never a separate review store.
  Attaches to **anything** through a subject-provider registry (`Tiger_Comment::registerSubject`,
  modeled on `Tiger_Search`/`Tiger_Audience`): a CMS page, a blog article, a marketplace listing, a
  shop product. 5 stars with **half-star display** of averages (whole-star input). Denormalized
  `comment_aggregate` because a 60-card grid cannot average N rows per card. The differentiator is
  the **verified reviewer** — Tiger has an entitlement oracle, so "every review is from someone who
  bought it" is a claim WordPress structurally cannot make. Fills the rating/download overlay a
  marketplace already publishes (`TigerMarketplace/docs/design/reputation.md` §8).

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

<!-- NOTE: billing/payments live in separate app-level modules (their own repos), NOT in tiger-core.
     They are not a free-platform gap — don't add a "billing module" item to this backlog. -->


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
