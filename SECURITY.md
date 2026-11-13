# Security Policy

## Reporting a vulnerability

Email **security@webtigers.com**. Please don't open a public GitHub issue for a security problem.

Include what you can:

- the affected version (`Tiger_Version::VERSION`, or the `webtigers/tiger-core` version in `composer.lock`);
- the component — core, a bundled module, or a separately installed module (name and version);
- steps to reproduce, and what an attacker gains;
- whether the issue is already public.

We confirm receipt, keep you updated while we work on it, and credit you in the release notes unless you'd
rather not be named. Please give us a reasonable chance to ship a fix before you disclose publicly.

## Supported versions

Tiger ships continuously on a single 1.x line. Security fixes land in the **latest release**; we don't
backport to older minor versions.

| Version | Supported |
|---|---|
| Latest 1.x release | ✅ |
| Older 1.x releases | Update to the latest release |
| 0.x (pre-1.0) | ❌ |

## Staying up to date

- **From the admin:** Modules › Updates updates core and installed modules in place.
- **With Composer:** `composer update webtigers/tiger-core`, then `bin/tiger migrate`.
- Release notes, including security fixes, are in [CHANGELOG.md](CHANGELOG.md) and on the
  [GitHub releases page](https://github.com/WebTigers/TigerCore/releases).

## Scope

This policy covers TigerCore (`webtigers/tiger-core`) and the modules bundled with it. Separately
installed WebTigers modules are covered too — report them to the same address. Modules from other vendors
are their authors' responsibility; Tiger's module installer verifies a signed module's signature before
extracting it, but it can't vouch for a module's code.

## How we keep dependencies in check

Every pull request and every push to `main` runs `composer audit` against the
[PHP security advisories database](https://github.com/FriendsOfPHP/security-advisories), and fails on any
known-vulnerable dependency.
