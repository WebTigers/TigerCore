# Tiger — Rules and best practices for module vendors

You're building a module someone else will install next to modules you've never seen, on a site you'll never see.
These rules are what lets that work. Most are enforced by the platform, and the rest are what reviewers and buyers
check. For *how* to build (forms, services, views, ACL) read [AGENTS.md](AGENTS.md); for selling read
[SELLING.md](SELLING.md) and [MARKETPLACE.md](MARKETPLACE.md).

---

## 1. Brand your database tables (enforced)

Every table your module creates must start with **your own branded prefix**, declared in your module's
`configs/module.ini`:

```ini
[production]
tables.prefix = "paysoft_"
```

```php
// migrations/20261117120000_create_paysoft_payout.php
return ['up'   => ['CREATE TABLE `paysoft_payout` (...)'],
        'down' => ['DROP TABLE `paysoft_payout`']];
```

- **Brand it.** Use your company or product name: `paysoft_`, `easypay_`, `acme_crm_`. Never a generic word —
  `user_`, `mail_`, `order_`, `data_`, `app_` — because a generic prefix is exactly where two vendors collide.
- **Shape:** lowercase, starts with a letter, at least three letters or digits, ends in `_`. More words joined
  with `_` are fine (`acme_crm_`).
- **Every** table, including join tables and logs. A table without the prefix refuses the install.
- **One prefix per vendor product.** A prefix that overlaps another installed module's (`acme_` vs `acme_crm_`)
  is refused, so pick something distinctive.

**What the installer checks, before anything is installed** (`Tiger_Module_TableGuard`, TIGER-444):

| Check | Refused with |
|---|---|
| A prefix is declared, well-formed, branded (not a generic/reserved word) | `E_TABLE_PREFIX` |
| The prefix doesn't overlap another installed module's prefix | `E_TABLE_PREFIX` |
| Every table your pending migrations create starts with it | `E_TABLE_PREFIX` |
| None of those tables already exists in the database (core, the app, or any module) | `E_TABLE_COLLISION` |

The checks read your migration files as text; nothing runs until they pass, and a refusal leaves nothing behind.
An update that re-ships migrations already applied is fine — only migrations that will actually run are checked.

> The platform's own first-party modules use plain table names, the same as core. That's the one exception, and
> it's why your prefix must be branded: the plain names belong to the platform.

---

## 2. Be multi-tenant aware — even if your buyer runs one site

Every Tiger install has at least one organization, and many host dozens on one install. Write your module as if it
will land on a multi-tenant host, because one day it will. A module written this way also works perfectly on a
single site; one that isn't **leaks data between customers** the day it's installed on a multi-tenant host.

- **Every row you store carries `org_id`.** Every read is limited to the caller's org; every update or delete checks
  the row's org first — never a bare `findById()` on a write.
- **Settings are per org.** Write them at `Tiger_Model_Config::settingScope()`, show them with `currentValue()`, and
  read them on public pages with `siteValue()` — never `SCOPE_GLOBAL` for something a tenant configures.
- **Which org a request belongs to is core's job,** not yours. Use the caller's org from the identity and the
  platform helpers; don't infer it from the hostname or a parameter.
- **Never assume one admin.** An app-wide administrator may manage every org; a tenant's admin only theirs. Ask the
  ACL (`_isAllowed()`), never compare role names.
- **Secrets and AI keys are the org's own.** Never fall back to another org's key or the install's.

AGENTS.md "Authorization" and ACL.md §1a are the full rules.

---

## 3. Everything else reviewers check

- **Your namespace is your slug.** Classes `Slug_*`, translation keys `slug.*`, config keys under your slug,
  stored files under `storage/<slug>/` (never inside your module directory — an update replaces it).
- **Every write goes through a `Tiger_Form`** (that's where CSRF comes from) and runs in a transaction. Every
  public guest form follows the checklist in AGENTS.md (CSRF, reCAPTCHA, honeypot, rate limit).
- **`/api` is deny-by-default.** Ship a `configs/acl.ini` with one rule per method that a non-admin can reach, and
  declare each method's input (`@apiRequest` / `@apiParam`) or mark in-process methods `@apiExclude`.
- **No `<script>` or `<style>` in views.** Register assets with `pageScript()` / `pageStyle()`; pass values through
  `data-*` attributes and strings through `$this->i18n()`.
- **No browser dialogs** (`alert`/`confirm`/`prompt`) — use `TigerModal`.
- **Migrations are additive** and versioned with a time-of-day stamp (`YYYYMMDDHHMMSS_name.php`) so they never
  share a version with another module's. Ship a working `down`.
- **Strings are translated** — no hardcoded user-facing text; ship at least English.
- **Your version is real.** `module.json` `version` matches the release tag, every time.
- **Test it.** A module that takes money or stores customer data without tests won't pass review.

---

*If a rule here conflicts with AGENTS.md, AGENTS.md wins — and tell us, so this file gets fixed.*
