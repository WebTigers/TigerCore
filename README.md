# tiger-core

The **framework** half of the [Tiger](https://github.com/WebTigers/Tiger) platform — the
kernel + multi-tenant substrate that a Tiger app consumes from `vendor/` and updates with
`composer update`. Built on [TigerZF](https://github.com/WebTigers/TigerZF).

This package is **Tiger-owned**: it lives in `vendor/webtigers/tiger-core/` in a real app,
and `composer update` replaces it in place. You never edit it — you extend it (modules,
config `.ini` overrides, subclasses in your app `library/`).

## Layout

| Path | What |
|---|---|
| `library/Tiger/` | The `Tiger_*` kernel — Composer PSR-0 autoload (like `Zend_*`) |
| `core/` | The default (module-less) namespace: `controllers/`, `views/`, `configs/` |
| `modules/` | First-party modules (auth, account, …) |
| `migrations/` | Additive-only core schema (org, user, org_user, acl_*) |
| `public/` | Core static assets — symlinked into the app docroot as `public/_tiger` |
| `bin/tiger` | The platform console |

## How an app wires it

The app's `application/Bootstrap.php` points ZF1 at this package:

```php
$front->setControllerDirectory(TIGER_CORE_PATH . '/core/controllers', 'default');
$front->addModuleDirectory(TIGER_CORE_PATH . '/modules');
$view->addScriptPath(TIGER_CORE_PATH . '/core/views/scripts');
// + symlink: public/_tiger -> vendor/webtigers/tiger-core/public
```

`Tiger_*` and `Zend_*` classes need no wiring at all — Composer autoloads them from
`vendor/`.

---

Built by WebTigers. Licensed under `(MIT AND BSD-3-Clause)`.
