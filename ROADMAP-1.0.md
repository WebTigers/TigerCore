# Tiger 1.0 — shipped

**Tiger 1.0.0 shipped on 2026-08-24.** Tiger is a stable release line on Packagist
(`webtigers/tiger` + `webtigers/tiger-core`) and ships continuously: minor = feature, patch = fix,
semver applied with judgment. Install and update from stable tags; no `--stability` flag is needed.

## What 1.0 meant

1.0 was a confidence marker, not an API freeze: the core is solid, and a real target user — the
WordPress/cPanel crowd, indie authors, small SaaS builders — can install it and use it end to end.

- **No-shell onboarding** — the one-file web installer ([WebTigers/TigerInstall](https://github.com/WebTigers/TigerInstall))
  installs the vendored release bundle on shared hosting with no terminal and no Composer.
- **One command with a shell** — `composer create-project webtigers/tiger my-app`.
- **Self-updating** — core and modules update from the admin (Modules › Updates) or `composer update`.
- **CI on every change** — tests on PHP 8.1–8.5, a coverage ratchet, version-check, release-zip.

## After 1.0

Everything since is continuous delivery — see [CHANGELOG.md](CHANGELOG.md) for what each release
added and [BACKLOG.md](BACKLOG.md) for what's next.
