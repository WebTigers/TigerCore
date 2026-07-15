# Tiger — Installation & Requirements

What a host needs to run Tiger, and the config we recommend. Tiger targets **shared cPanel
hosting** (no shell, no root, everything inside the account) as its floor — if it runs there, it
runs anywhere. **Check these *before* you install:** most are one-time cPanel/`php.ini` changes,
and the [web installer's pre-flight](#pre-flight) will verify them for you and tell you exactly
what to change if something's short.

Two install paths, same requirements:

- **Composer** (shell hosts / dev): `composer create-project webtigers/tiger my-app --stability=beta`
- **No-shell / cPanel** (roadmap): a pre-built vendored ZIP + a browser web installer.

---

## PHP

### Version

| | Version |
|---|---|
| **Minimum** | **PHP 8.1** |
| **Recommended** | **PHP 8.2+** (tested through 8.5) |

On cPanel, set it in **MultiPHP Manager** (per-domain) or **Select PHP Version**.

### `php.ini` directives

| Directive | Minimum | Recommended | Why Tiger needs it |
|---|---:|---:|---|
| `memory_limit` | `128M` | `256M` | ZF1 app + CMS rendering + image/media handling + a module install pass |
| `max_execution_time` | `30` | `120` | migrations, module install/extract, and the docs **reference build** (scans the codebase) |
| `max_input_time` | `60` | `120` | large media/content uploads |
| `max_input_vars` | `1000` | `3000` | big admin forms — the menu builder, ACL grids, settings panels post many fields |
| `post_max_size` | `16M` | `64M` | media + content uploads (keep ≥ `upload_max_filesize`) |
| `upload_max_filesize` | `8M` | `64M` | media library uploads |
| `file_uploads` | `On` | `On` | media library |

On cPanel: **MultiPHP INI Editor** → *Basic* mode covers all of the above; *Editor* mode for
anything else. No cPanel? A `php.ini` (or `.user.ini` under PHP-FPM) in the docroot works.

### Extensions

**Required** — Tiger won't fully run without these:

| Extension | Used for |
|---|---|
| `pdo_mysql` (or `mysqli`) | database (ZF1 `Zend_Db`) |
| `mbstring` | UTF-8 text handling throughout |
| `openssl` | HTTPS/TLS, secure tokens |
| `gd` (or `imagick`) | image/media processing |
| `curl` | outbound HTTP — module install from GitHub, Location/mail/reCAPTCHA providers |
| `zip` | module-install archive extraction |
| `tokenizer` | the docs **reference generator** (token-parses source docblocks) |
| `fileinfo` | upload MIME detection |
| `json`, `ctype`, `filter`, `dom` | bundled with modern PHP — enabled by default |

**Recommended:**

| Extension | Benefit |
|---|---|
| `opcache` | performance; the docs build cache is opcache-friendly |
| `intl` | best localized country/locale names (falls back to CLDR without it) |
| `sodium` (libsodium) | **native-speed** secret encryption + TOTP 2FA (`Tiger_Crypto`). A pure-PHP polyfill (`paragonie/sodium_compat`) is **bundled**, so Tiger runs without the extension — but the native one is faster. |

On cPanel, toggle these in **Select PHP Version → Extensions**.

---

## Database

| | Requirement |
|---|---|
| Engine | **MySQL 5.7+** or **MariaDB 10.3+** |
| Storage | **InnoDB** (transactions — Tiger services wrap writes in transactions) |
| Charset | **utf8mb4** / `utf8mb4_unicode_ci` |

Create the database + a user with full privileges on it in cPanel's **MySQL® Databases**; the
installer writes those credentials to `local.ini`. **No table prefix** — use a separate database
per separate install (Tiger's multi-tenancy handles many sites in one DB via `org_id`).

---

## Web server

| Server | Requirement |
|---|---|
| **Apache 2.4+** (the cPanel default) | `mod_rewrite` **on**, `AllowOverride All` so the shipped `public/.htaccess` front-controller routing works |
| nginx / Caddy / FrankenPHP | reference configs ship; point the docroot at `public/` |

**Docroot must be `public/`** (the app root is its parent). A module never needs web-server
changes — pretty URLs are PHP-layer route overrides — so on shared hosting you only set the
docroot once and never touch vhosts again.

---

## Filesystem

PHP (running as your cPanel user) must be able to **write** inside the account:

| Path | Why |
|---|---|
| `var/` | caches, logs, the docs build cache + `var/docs-generated`, file-session fallback |
| `application/configs/local.ini` | the web installer writes DB creds + minted secrets here (a **manual-paste fallback** is offered if it isn't writable) |

Everything Tiger reads or writes stays **inside the account** — no system `/tmp`, nothing outside
your home dir — so it's `open_basedir`-safe by design.

---

## Pre-flight <a id="pre-flight"></a>

The web installer opens with a **requirements check** that reads the live environment
(`phpversion()`, `ini_get()` for each directive above, `extension_loaded()`, a DB connection test,
a write test) and shows **pass / warning / fail** per item — with the exact value to change and
where. You fix any shortfalls in cPanel / `php.ini` **before** the install proceeds, so you never
get a half-finished install that breaks on the first upload or the first module. This document is
the source of truth those checks are generated from.

---

*Requirements are also declared in `composer.json` (`require`) for the shell path; this document is
the human + no-shell reference. Keep the two in sync.*
