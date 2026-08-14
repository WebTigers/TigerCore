# Tiger — Road to 1.0

What **1.0** means for Tiger, and the checklist to get there. 1.0 is **not** a feature count — per
[ARCHITECTURE.md](ARCHITECTURE.md) §0/§1/§13 it is the release that **freezes the `@api`** and commits
to semver on that surface (`@api` = stable/build-on-it; `@internal` = may change any release). Everything
below serves one goal: make the public surface **complete + trustworthy**, then **lock it**.

For the running feature/debt list see [BACKLOG.md](BACKLOG.md); this doc is only the 1.0 gate.

---

## Status: the one named gate is MET ✅

ARCHITECTURE §13 set a single hard prerequisite — *"a green suite is the gate for the stable 1.0 that
freezes the `@api`."* That's done. On every PR, CI runs green:

- **Tests** — PHPUnit unit + integration (~1,650 tests).
- **Smoke** — boots on **PHP 8.1 / 8.3 / 8.4 / 8.5**.
- **Coverage** — a **ratcheting floor** (currently ~72, sitting at **~74%**); the security-critical paths
  are covered (auth/login + lockout, ACL deny-by-default, crypto/pepper rotation, module-install
  extraction + zip-slip guard, the `/api` reserved-module guard).
- **version-check** — asserts `Tiger_Version::VERSION` == the release tag on every tag push.
- **review** — an AI review bot on every PR. **release-zip** — the pre-resolved vendored ZIP per release.

So 1.0 is no longer blocked on tooling. It is now a **scope decision + an `@api` audit + a sign-off**.

---

## 1 — The defining act: freeze the `@api`

Do this **last** (after §2 lands), so we don't freeze a surface we're about to change.

- [ ] **`@api` inventory** — enumerate every `@api` class + public method (the semver-guaranteed surface).
      Mechanical; can be generated from the reference generator's input.
- [ ] **Consistency audit** — signatures match docblocks (the [AGENTS.md](AGENTS.md) contract); no
      `@internal` type leaking through an `@api` return; naming coherent across sibling classes.
- [ ] **Deliberate sign-off** — a maintainer accepts the surface as stable. After 1.0, changing it is a
      **major-version** event.

## 2 — Pre-freeze scope (land BEFORE the freeze)

These touch the **auth / ACL / module** `@api` and interlock (the token carries the ACL-map context; deps
feed modules), so they belong in the frozen surface rather than bolted on after. All are BACKLOG "Priorities".

- [ ] **App-level ACL — Phase 2** — named policy maps, floor+map composition, `token→map`,
      narrows-never-widens ([ACL.md](ACL.md)). *Biggest item.*
- [ ] **Token auth — finish the surface** — token-management admin screen + org/map context + scoping.
- [ ] **Module dependency provisioning — infra** — the Vendor Library Registry + CI bundle-builder + a
      first real consumer ([DEPENDENCIES.md](DEPENDENCIES.md)). *Can slip to just-after-1.0 if cutting sooner.*

## 3 — Distribution completeness (the "1-click, no shell" promise)

- [ ] **First-run web installer** — a browser setup wizard (requirements pre-flight → DB creds →
      `local.ini` → migrate → `install:admin`) with an `installed` sentinel ([INSTALL.md](INSTALL.md)).
      The engine (`Tiger_Install`) exists; this is the web front-end.
- [ ] **WHM/cPanel plugin** — the host-side one-click channel (Softaculous-style). *Post-1.0 acceptable.*
- [x] Vendored release ZIP + one-click core self-update — shipped.
- [x] Packagist auto-publish on tag — shipped.

## 4 — Trust & polish for a "stable" label

- [ ] **Coverage decision** — ratify ~74% as the 1.0 bar (security-critical paths covered), or push toward
      ~80%; the remaining tail is genuinely hard I/O (see `tests/COVERAGE-PLAN.md` §9).
- [ ] **Security pass** over the frozen surface (auth, ACL, install/extract, `/api` guard) — largely
      test-covered; a focused review before the lock.
- [ ] **Tech-debt cleanups** — error-page `core.error.*` i18n; the stale `Zend_Version` constant.
- [ ] **Docs sweep** — refresh ARCHITECTURE §13 "Pending" (CI is done); regenerate the `@api` reference;
      confirm FEATURES.md parity with what actually shipped.

## 5 — Cut 1.0

- [ ] Bump `Tiger_Version::VERSION` → **`1.0.0`** (drop `-beta`); bump the `webtigers/tiger` skeleton in lockstep.
- [ ] Tag `v1.0.0`, publish the release (version-check + release-zip fire; Packagist picks it up).
- [ ] Roll all envs; **announce the `@api` freeze**.

---

## Recommended sequencing

Tooling/tests are done, so the critical path is:

**ACL Phase 2 → token surface → first-run web installer → `@api` audit → cut 1.0.**

The **vendor-registry infra** (§2.3) and the **WHM/cPanel plugin** (§3) are the two pieces that can
reasonably ship *just after* 1.0 if we'd rather cut sooner — neither changes the `@api` in a way the
freeze would regret. Coverage is a dial, not a blocker. Nothing else gates the release.
