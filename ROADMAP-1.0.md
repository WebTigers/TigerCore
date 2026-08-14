# Tiger — Road to 1.0 (Launch)

**1.0 = drop the `-beta` and launch.** Not an API-freeze ceremony — Tiger is pre-launch and shipped
continuously via CI/CD. 1.0 is a **confidence marker**: the core is solid, a real target user can install
and use it end to end, and we're ready to say "use this" out loud. We keep shipping after 1.0 exactly like
before — minor = feature, patch = fix, don't gratuitously break. Semver applied with judgment, not a
waterfall gate; if something breaks post-1.0 we bump and fix it, same as today.

For the running feature/debt list see [BACKLOG.md](BACKLOG.md).

## Where we are

The engine is done and CI-green (tests, smoke 8.1–8.5, coverage ratchet ~74%, version-check, release-zip,
one-click self-update). The platform is already feature-rich: multi-tenant substrate, auth incl. TOTP,
ACL, CMS + visual builder, media, modules/marketplace, updates. So the question isn't "is it built" — it's
**"can our target user (the WP/cPanel crowd, indie authors, small SaaS builders) get it running and get
value without hitting a wall."**

## The one real launch gate: onboarding on a no-shell host

The whole GTM (WP migration, cPanel hosts, "1-click, no shell") rides on **first-run install without a
terminal**. The engine (`Tiger_Install`) + CLI (`install:admin`/`install:secrets`) exist; the missing
piece is the browser front-end:

- [ ] **First-run web installer** — requirements pre-flight → DB creds → `local.ini` → migrate → admin,
      with an `installed` sentinel ([INSTALL.md](INSTALL.md)). **This is the launch blocker.**
- [x] composer create-project, vendored release ZIP, one-click self-update, Packagist auto-publish — done.

## Launch polish (small; worth doing before we say "use this")

- [ ] Clean **create-project → running app** path — the asset-symlink hook so a fresh app renders with zero manual steps.
- [ ] Error pages i18n-keyed (`core.error.*`); align the stale `Zend_Version` constant.
- [ ] A quick **security once-over** of the exposed paths (auth, ACL deny-by-default, install/extract, `/api` guard) — mostly test-covered already.
- [ ] Skim FEATURES.md / the docs for "coming soon" that actually shipped (and the reverse).

## Everything else = continuous delivery

Real and valuable, but **post-launch increments, not launch gates** — build each when a customer or use
case pulls it, not to satisfy a checklist (YAGNI):

- App-level ACL Phase 2 (named maps) · token-management admin + scoping · the `Tiger_Event` extension
  model · the Vendor Library Registry infra · API-discovery Phase 3 (role-filtered) · a public Billing module.

*(These are the trimmed [BACKLOG.md](BACKLOG.md) "Priorities" — they stay on the backlog, they just don't
block the suffix flip.)*

## Cut it

- [ ] Bump `Tiger_Version::VERSION` → **`1.0.0`** (skeleton in lockstep), tag `v1.0.0`, roll all envs, launch.
- No freeze, no audit, no sign-off. Keep shipping.

**Bottom line:** the only thing between here and a legitimate 1.0 launch is **no-shell onboarding**. Land
the web installer, do a light polish pass, flip the suffix, launch — then keep delivering continuously.
