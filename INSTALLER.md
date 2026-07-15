# Tiger — the one-file web installer (`tiger-install.php`)

How an **everyday cPanel/WordPress user with no shell** installs Tiger: download one file, drop it in
the document root, open it in a browser. It downloads Tiger, places the app **above** the web root,
writes secrets **above** the web root, builds the schema, creates the admin, and deletes itself. Read
this before building the installer or the release build that ships it. For the *why* behind the
platform read [ARCHITECTURE.md](ARCHITECTURE.md) (esp. §1 distribution, §4 entry/bootstrap, §5 config
cascade); for the **host requirements** the preflight verifies read [INSTALL.md](INSTALL.md) (the
source of truth for §5 here); for the deploy surface read [FEATURES.md](FEATURES.md).

> **Status: design-of-record (proposed, not built).** This records the decisions and their rationale.
> Today install is the `bin/tiger` CLI (`install:secrets` / `migrate` / `install:admin`) plus the
> composer path (`composer create-project`, done). This spec covers the **no-shell / cPanel** channel
> ([[install-distribution-model]]): a single `tiger-install.php`, shipped from its **own one-file repo**
> (`WebTigers/tiger-install`), **evergreen** (§12). Where it says "the installer does X," that's the
> target behavior.

---

## 0. The one principle

**The secret file never lives where a browser can fetch it.** WordPress drops `wp-config.php` — your
DB password in plaintext — into the document root and relies on the web server *choosing* not to serve
`.php` as text. Tiger inverts it: the app and its `local.ini` live **above** the document root, and
only a 3-line shim + assets sit in the web-reachable dir. A misconfigured server, a `.php`-as-text
slip, a directory-listing bug — none of them can leak a secret that isn't under the web root at all.

The installer's whole job is to reach that layout with **zero shell, zero composer, and one thing the
user does by hand** (create an empty database — see §4).

---

## 1. Division of labor — who does what

The single hard limit on shared cPanel decides the entire UX (§4): a user-space PHP script can't
create a MySQL database. Everything else, the installer automates.

| Task | Who | Why |
|---|---|---|
| Create the empty **MySQL database + user + grant** | **the user** (cPanel *MySQL Databases* wizard, ~30s) | `CREATE DATABASE`/`CREATE USER` are cPanel-gated; a normal DB account is denied (§4) |
| Download + extract Tiger | installer | cURL + `ZipArchive`/`PharData` |
| Place app code **above** docroot | installer | PHP runs as the cPanel user → can write `/home/user/` |
| Write the `public/` shim + assets into docroot | installer | the only web-reachable pieces |
| Write DB creds + minted secrets to `local.ini` (0600, above docroot) | installer | the security payoff (§0) |
| Build the **schema** (migrations) + seed the founding org/admin | installer | the granted user has full rights *on its own DB* |
| Delete the installer | installer (self-unlink) | no lingering `install.php` |

**The takeaway to internalize:** *DB+user creation* is the only step no user-space script can do on
shared hosting; *schema creation* is fully automatable. Match WordPress's manual-installer UX — which
millions already know — and do everything after the paste.

---

## 2. The end-state layout

```
/home/user/
├── tiger-app/                          ← app root (APPLICATION_ROOT), NOT web-reachable
│   ├── vendor/                         ← bundled: tiger-core, tigerzf, polyfills (no composer needed)
│   ├── application/
│   │   ├── configs/local.ini           ← DB creds + secrets · 0600 · ABOVE docroot ✅
│   │   └── modules/  library/  Bootstrap.php  custom.php
│   ├── var/  (cache, logs, docs-generated)
│   └── migrations/
└── public_html/                        ← the ONLY web-reachable dir
    ├── index.php                       ← shim: defines APPLICATION_ROOT=/home/user/tiger-app, requires its autoload
    ├── .htaccess                       ← front-controller routing (no vhost needed — cpanel-hosting-constraint)
    └── _theme/  (asset symlink → tiger-app theme assets, or a copy if symlinks are off)
```

Two layouts, installer **defaults to B** (no cPanel action needed):

- **Layout A — docroot into the app** (`docroot = /home/user/tiger-app/public`). Matches the standard
  composer shim exactly (`APPLICATION_ROOT = dirname(__DIR__)`), keeps `public_html` pristine — but the
  user must **repoint the domain's docroot** in cPanel (a primary domain's docroot a script can't
  change). Offer it to users who can/will.
- **Layout B — split, docroot stays `public_html`** (default). App above, a **custom shim** in
  `public_html` with the resolved absolute `APPLICATION_ROOT` baked in. **No docroot change** → the
  everyday path.

---

## 3. The install flow (wizard screens)

`tiger-install.php` is a small **self-contained, dependency-free** state machine (pure PHP 8.1, no
Tiger autoload yet — Tiger isn't installed). It runs in two phases: **pre-bootstrap** steps use only
core PHP; **post-bootstrap** steps (after extraction) `require` the freshly-extracted
`vendor/autoload.php` and call Tiger's own `Tiger_Install` so migrations/secrets/admin are **never
reimplemented** in the installer.

1. **Welcome + preflight** (§5). Green/red checklist. Blocks Continue on a hard failure; warns on soft.
2. **Choose target paths.** Show the detected docroot + the computed app dir (`/home/user/tiger-app`);
   let the user confirm/edit (addon domains/subdomains differ — never assume, §7). Pick Layout A or B.
3. **Download + extract** (§6). Fetch the versioned release ZIP → extract app above docroot → write the
   shim + assets into docroot. Progress + a manual-upload fallback if egress is blocked.
4. **Database** (§4). A form: host / name / user / password, with an inline "create this in cPanel
   first" mini-guide and a **Test connection** button that must pass before Continue.
5. **Write config + secrets.** `provisionSecrets()` mints the app key + pepper; write `local.ini`
   (0600) with the DB block + secrets, **in the app dir above docroot**.
6. **Build + seed** (post-bootstrap). Require the extracted autoload → run migrations (schema) →
   `install:admin` collects the founding org name + admin email/password and creates them.
7. **Done + self-destruct.** Show the site URL + the admin login; `unlink()` the installer (and, if
   that fails on perms, a loud "DELETE THIS FILE NOW" banner). Link to `/` and `/admin`.

Wizard state rides the request (hidden fields) + a PHP session; **the DB password is never persisted**
— it's held only across the write step and then dropped.

---

## 4. Why the user creates the database (the hard constraint)

On shared cPanel, a normal MySQL account **cannot** run `CREATE DATABASE` or `CREATE USER` — cPanel
owns database provisioning (it prefixes and tracks them as `cpuser_name` in its own registry), so a
raw `CREATE DATABASE` from PHP returns *access denied*. The only ways around it both cost more than
they're worth for a dropped file:

- **cPanel UAPI** (`Mysql::create_database`, `Mysql::create_user`, `Mysql::set_privileges_on_database`)
  — correct, but needs a cPanel **API token** or an active cPanel session the installer doesn't have.
  Asking the user to paste a token is friction + a security ask.
- **Root-level installers** (Softaculous/Installatron) *can* auto-create DBs — only because they're
  installed **server-wide with root**. A user PHP file can't assume that.

So: **the user creates the empty DB + user + grant in cPanel** (the same 30 seconds WordPress asks
for), and the installer does everything after. The granted user has **full privileges on that
database**, so the installer creating **tables** (migrations) works fine — it's only DB/user
*creation* that's walled off.

**Optional nicety (never a dependency):** on step 4, the installer probes whether the supplied account
can `CREATE DATABASE` (true on VPS/dedicated, some hosts). If yes, offer a one-click "create it for me"
and skip the manual step. If denied, fall back to the manual guide silently.

> **GTM note (separate track):** true auto-DB, one-click-from-a-catalog install is the
> **Softaculous/Installatron** listing — a root-level host integration, not something `tiger-install.php`
> can be. Pursue it as a distribution play; it doesn't change this spec.

---

## 5. Preflight checks (reassuring *and* diagnostic)

A WordPress/Joomla-style checklist, each PASS/WARN/FAIL with a one-line fix. **The requirement values
and the full list are owned by [INSTALL.md](INSTALL.md)** — the installer's preflight is generated from
it, so keep the two in sync; the below is the installer-relevant subset:

| Check | Hard? | Why |
|---|---|---|
| PHP ≥ 8.1 | FAIL | the platform floor |
| `pdo_mysql` | FAIL | DB driver |
| `zip` **or** `phar` | FAIL | to extract the release (`ZipArchive`, else `PharData` for tar.gz) |
| `curl` **or** `allow_url_fopen` | FAIL* | to download; *soft if the manual-upload fallback is offered |
| `mbstring`, `openssl`/`sodium` | FAIL | i18n + `Tiger_Crypto`/secrets |
| Outbound HTTPS to GitHub reachable | WARN | else use manual upload |
| App dir (`/home/user`) writable by PHP | FAIL | to place code above docroot |
| Docroot writable | FAIL | to write the shim + assets |
| `symlink()` available | WARN | else copy assets instead of linking |

Reflected from `phpversion()`, `extension_loaded()`, `ini_get()`, `is_writable()`, a `HEAD` to the
release host — the same reads INSTALL.md §Pre-flight describes.

---

## 6. Fetching + extracting

- **Source:** the **full-app vendored release ZIP** attached to the `webtigers/tiger` release
  ([[install-distribution-model]] gap #1) — the skeleton (`application/`, `public/`, `bin/`) **with
  `vendor/` bundled**, pruned, ~15–30MB, unzips with **no composer**. (Distinct from
  `tiger-core-vendored-*.zip`, which is `vendor/`-only and feeds the in-place *core updater*
  `Tiger_Update_Core` — the installer needs the whole app tree.) The installer is **evergreen** (one
  static file, never regenerated): it resolves the **latest** `webtigers/tiger` release via the GitHub
  API, downloads the ZIP asset, and **verifies it against the asset's `.sha256` sidecar** (the
  convention `bin/build-release-zip.sh` already emits — fetched over TLS from the same release) before
  extracting. A pinned `?version=` override is accepted for reproducible installs.
- **Transport:** cURL (follow redirects, TLS verify on) → temp file in the app-dir parent (not docroot).
  Fallback if egress is blocked: a **manual-upload** form (the user downloads the ZIP on their laptop
  and uploads it), or an SFTP-drop path the installer then reads.
- **Extract:** `ZipArchive`; `PharData` for a `.tar.gz` variant. Extract app tree above docroot, then
  place `public/`'s contents (shim, `.htaccess`, assets) into docroot per the chosen layout (§2).

---

## 7. Placing code above docroot (path detection)

- Detect: `$_SERVER['DOCUMENT_ROOT']`, `__DIR__` (installer lives in docroot), and the **home** =
  parent of docroot for a primary domain. Default app dir = `<home>/tiger-app`.
- **Never assume** docroot is directly under home — **addon domains/subdomains** put docroot at
  `public_html/<sub>`. Show the detected values and let the user **confirm/override** the app-dir path
  (step 2). This one confirmation prevents the most common wrong-path install.
- **Layout B shim** is written with the **resolved absolute** `APPLICATION_ROOT` baked in (not a
  fragile `dirname()` that assumes adjacency), then requires `APPLICATION_ROOT/vendor/autoload.php` and
  runs `Tiger_Application`, exactly as the standard shim (ARCHITECTURE §4) but with an explicit root.
- **Assets:** the installer wires the docroot with **`Tiger_Install::linkPublicAssets($webroot,
  $appRoot, 'puma')`** — which already handles the split layout (`_tiger`/`_theme` symlinks computed
  from the absolute app root) — **plus** `_media`/`_code`/`_modules` symlinks from the docroot to the
  app's `public/*` (created by `provisionStorage`). If `symlink()` is disabled, fall back to Layout A
  (point the domain's docroot at `<appRoot>/public`, where all of these already live natively).

## 7a. Multi-domain / domain-namespaced installs (cPanel addon domains)

**Yes — Tiger runs domain-namespaced, cleanly, because every install is self-contained.** A Tiger
install is defined entirely by its `APPLICATION_ROOT` (explicit in the shim), its own `local.ini`, and
its own database — it shares *nothing* global. So N domains on one cPanel account = **N independent
installs** living side by side:

```
/home/user/
├── domain.com/tiger-app/            ← install A: code + application/configs/local.ini + var/
├── domain.net/tiger-app/            ← install B: fully separate — own local.ini, own DB
└── public_html/
    ├── domain.com/                  ← install A docroot (shim → /home/user/domain.com/tiger-app)
    └── domain.net/                  ← install B docroot (shim → /home/user/domain.net/tiger-app)
```

The installer supports this **for free** because it's uploaded *into a specific domain's docroot*, so
it already knows which domain it's serving:

- **Home** is resolved reliably via `posix_getpwuid(posix_getuid())['dir']` (fallback: walk up the
  docroot), so it's correct even when an addon docroot sits at `public_html/<domain>` (where
  `dirname(docroot)` is *not* the home).
- **Domain** comes from `$_SERVER['HTTP_HOST']`; the **default app dir** is derived as
  `<home>/<domain>/tiger-app` — domain-namespaced so a second install never collides with the first.
  Shown and **editable** at step 2 (the user's exact layout, or `<home>/tiger-app` for a lone site).
- Each docroot gets its **own shim** with its **own absolute `APPLICATION_ROOT`**, so installs are
  mutually invisible. Nothing is shared, so there's nothing to conflict.

**Two axes, don't conflate them:** the above is *N separate installs* (unrelated sites, separate DBs,
separate admins). Tiger *also* has in-app **multi-tenancy** (ARCHITECTURE §7) — **one** install serving
many sites as org-scoped tenants (one DB, per-org theme/config). Pick per-domain installs for unrelated
sites; pick one multi-tenant install for a fleet you manage centrally. The installer builds the former;
the platform provides the latter.

---

## 8. Writing `local.ini` + secrets

- The installer writes **`application/configs/local.ini`** (above docroot) with the `[production]` DB
  block (`resources.db.params.*`) + the minted secrets (`tiger.crypto.key`, `tiger.security.pepper`),
  then `chmod 0600`. This is the same file the CLI/setup already targets ([[crypto-key-provisioned]]).
- Secrets come from the **existing** `Tiger_Install::provisionSecrets()` (the CLI's `install:secrets`
  and a web setup call the same code) — the installer does **not** invent its own crypto.
- `local.ini` is gitignored and per-deploy; a `.dist` template ships in the bundle. Never in docroot,
  never committed.

---

## 9. Schema + admin (reuse `Tiger_Install`, don't reinvent)

Once code is extracted and `local.ini` is written, the installer **bootstraps Tiger in-process**
(`require APPLICATION_ROOT/vendor/autoload.php`) and calls the platform's own install path:

- **Migrations** — the shell-free runner builds the schema (the granted DB user can create tables).
- **`install:admin`** — collect founding **org name** + **admin email/username/password** (policy-
  checked via the real validators) → create the org + owner + membership. Same code as the CLI.

Because these are Tiger's own methods, the browser installer and `bin/tiger` stay in lockstep — one
install path, two front doors (a web form and a CLI), exactly like `provisionSecrets`.

---

## 10. Security hardening

- **Self-delete on success** (`unlink(__FILE__)`); if perms block it, a loud persistent banner + a
  refusal to proceed until it's gone.
- **Re-run guard:** if a populated `local.ini` (DB block present) already exists, the installer
  **refuses** and shows "already installed" — no takeover of a live site.
- **Perms:** files 0644, dirs 0755, `local.ini` 0600.
- **No secret in the URL or logs;** the DB password is POST-only and never written to the wizard's
  session beyond the write step.
- **TLS-verified download + SHA-256 check** (§6) so a MITM can't swap the payload.
- **Optional install lock:** on first load, write a random token to `<home>/.tiger-install-token` (not
  web-reachable) and require it be echoed — defeats a drive-by hitting the installer before the owner
  does. (Weigh against friction; the re-run guard + self-delete may suffice for v1.)

---

## 11. Failure handling — degrade, never dead-end

Every external step has a fallback, surfaced as a clear next action (never a white screen):

- Download blocked → **manual upload**.
- No `zip` → `phar`/tar.gz.
- No `symlink` → **copy** assets.
- DB connect fails → back to the form with the driver's message + the cPanel guide.
- Can't write above docroot (PHP not running as the user) → explain, offer Layout A or an SFTP-place
  fallback.
- Migration error → show it, keep `local.ini`, let the user fix + resume (idempotent re-run).

---

## 12. Its own repo — one file, nothing more

`tiger-install.php` lives in a **dedicated repository** (`WebTigers/tiger-install`) whose payload is
that single file — **not** inside `tiger-core`. Two reasons make this the right home, not just a tidier
one:

- **Chicken-and-egg.** The installer exists *because Tiger isn't on the box yet*; shipping it inside the
  very ZIP it fetches is backwards. It's a bootstrapper, so it stands alone.
- **A trivially-auditable trust surface.** It downloads code and writes secrets — the most
  security-sensitive file in the whole system. A reviewer reads **one small file in one repo**, not a
  needle in the framework tree. That's the "read before you run" ethos applied to the installer itself.

Consequences:

- **Evergreen** (§6) — one stable raw URL the install page + docs point at forever
  (`raw.githubusercontent.com/WebTigers/tiger-install/main/tiger-install.php`, plus a release-asset
  mirror), **never regenerated** per Tiger release. It carries no Tiger version of its own; it resolves
  the latest release at run time.
- **Integrity** now rests on **TLS + the asset's `.sha256` sidecar** rather than a SHA baked into the
  distributed file — the same model as `get-composer`, `rustup`, and `get.docker.com`, and
  industry-normal for a bootstrapper. The `?version=` pin (§6) covers reproducible installs.
- **The whole repo** is: the one file + a short **README** (states exactly what it does — transparency
  for a security-sensitive download) + a **LICENSE** (BSD-3). Nothing else. The *payload* stays one file.

---

## 13. Rejected alternatives (so we don't relitigate)

| Rejected | Why | Chosen instead |
|---|---|---|
| `wp-config.php`-style config **in** docroot | secrets one server slip from being served as text | app + `local.ini` **above** docroot; only a shim is web-reachable (§0) |
| Require the user to run `composer install` | no shell on shared cPanel | a **vendored ZIP** the installer downloads + extracts (no composer) |
| Auto-create the MySQL DB from the script | `CREATE DATABASE` is cPanel-gated → access denied | the user creates the DB (cPanel wizard); the installer builds the **schema** (§4) |
| Ask for a cPanel API token to auto-provision the DB | friction + a security ask; host-specific | manual DB step (WP-parity); optional auto-create only when the account already can |
| Reimplement migrations/secrets in the installer | drift from `bin/tiger`; a second crypto path | **bootstrap Tiger** and call `Tiger_Install` (§9) |
| Leave the installer in place (WP does) | a live attack/re-run surface | **self-delete** + a re-run guard (§10) |
| Assume docroot is directly under `$HOME` | breaks on addon domains/subdomains | **detect + confirm** the paths (§7) |
| Ship the installer **inside** `tiger-core` | chicken-and-egg (it fetches the ZIP it'd be in); buries a security-critical file in the framework tree | its **own one-file repo** (`WebTigers/tiger-install`), evergreen (§12) |
| Regenerate a **version-pinned** installer every release | breaks "one file, never touched"; a moving artifact to publish each time | **evergreen** file that resolves latest + verifies the published checksum (§6) |

---

## 14. Build order (phasing)

1. **Release build emits the vendored ZIP + a version/SHA manifest** (gap #1) — the installer's
   payload. *Blocks everything.*
2. **`Tiger_Install` web surface** — ensure `provisionSecrets` + migrate + `install:admin` are callable
   from an in-process web context (they already back the CLI); add a thin `Tiger_Install::run(array)`
   if useful.
3. **`tiger-install.php` — pre-bootstrap half:** preflight, path detect/confirm, download+verify,
   extract, write shim/assets, the DB form + Test connection.
4. **`tiger-install.php` — post-bootstrap half:** write `local.ini`, bootstrap Tiger, migrate, create
   admin, self-delete.
5. **Fallbacks + hardening** — manual upload, symlink→copy, re-run guard, perms, optional install lock.
6. **Polish** — the checklist UI, the cPanel DB mini-guide, Layout A/B chooser, the "already installed"
   and "delete me" states.

---

## 15. Open questions (decide before build)

- **Install lock (§10):** token-gate first load, or is re-run-guard + self-delete enough for v1?
  (Friction vs. drive-by protection.)
- **Layout default:** ship **B** (no docroot change) as default and offer **A**, or detect an
  addon-domain docroot and recommend the fit? (Lean: default B, offer A.)
- **tar.gz vs zip:** ship both, or pick zip and rely on `ZipArchive` being near-universal with `phar`
  as the only fallback?
- **Manual-upload UX:** in-browser file upload (PHP `upload_max_filesize` may be < bundle size) vs.
  "SFTP this file to `<path>`, then click Continue." (Upload caps make SFTP the safer default for a
  ~30MB bundle.)
- **Multi-tenant first org:** collect just the founding org, or also offer to name the first tenant?
  (Lean: founding org only; tenants are a post-install admin task.)
- **Integrity model (decided):** evergreen + the asset's `.sha256` sidecar over TLS, with a
  `?version=` pin for reproducibility — chosen over baking a per-release SHA into the file (which would
  force regenerating the "one file" every release). Revisit only if a supply-chain review demands an
  offline-verifiable pin.

---

*This document records decisions and their rationale. If you change a decision, update the relevant
section here in the same change — the "why" is the most valuable and most perishable part.*
