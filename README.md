# TigerCore

The **framework** at the heart of the [Tiger](https://github.com/WebTigers/Tiger) platform —
the kernel + multi-tenant substrate (auth, ACL, orgs/users/memberships, theming, the
`bin/tiger` console) that a Tiger app consumes from `vendor/` and updates with
`composer update`. Built on [TigerZF](https://github.com/WebTigers/TigerZF).

> **Install:** `composer require webtigers/tiger-core`
>
> The repo and brand are **TigerCore**; the Composer *package name* is lowercase
> (`webtigers/tiger-core`) because Composer requires it — the two don't have to match.

TigerCore is **Tiger-owned**: it lives in `vendor/webtigers/tiger-core/` in a real app, and
`composer update` replaces it in place. You never edit it — you extend it (modules, config
`.ini` overrides, subclasses in your app `library/`).

## Layout

| Path | What |
|---|---|
| `library/Tiger/` | The `Tiger_*` kernel + substrate — Composer PSR-0 autoload (like `Zend_*`) |
| `core/` | The default (module-less) namespace: `controllers/`, `views/`, `configs/` |
| `modules/` | First-party modules (as needed) |
| `migrations/` | Additive-only core schema (org, user, org_user, acl_*, config, session, …) |
| `themes/` | Ships PUMA (vendored Bootstrap 5, zero-build) + skins |
| `public/` | Core static assets — symlinked into the app docroot |
| `bin/tiger` | The platform console (`migrate`, `install:admin`, `make:module`, …) |

## How an app wires it

The app's `application/Bootstrap.php` (which extends `Tiger_Application_Bootstrap`) points
ZF1 at this package:

```php
// ADD, not SET — setControllerDirectory() wipes the whole module map first.
$front->addControllerDirectory(TIGER_CORE_PATH . '/core/controllers', 'default');
$front->addModuleDirectory(TIGER_CORE_PATH . '/modules');
$view->addScriptPath(TIGER_CORE_PATH . '/core/views/scripts');
// + symlink: public/_theme -> vendor/webtigers/tiger-core/themes/<active>/assets
```

`Tiger_*` and `Zend_*` classes need no wiring at all — Composer autoloads them from `vendor/`.

See [ARCHITECTURE.md](ARCHITECTURE.md) for the *why* and [WEBSERVICES.md](WEBSERVICES.md) for
the `/api` message pattern.

---

Built by WebTigers. Licensed under `(MIT AND BSD-3-Clause)`.
