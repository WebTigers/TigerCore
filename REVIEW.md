# tiger-core — AI review calibration

Instructions for the automated PR reviewer (`.github/workflows/claude-code-review.yml`). This tunes what
counts as **Important** vs a **Nit** for *this* codebase — a multi-tenant SaaS platform on TigerZF
(Zend Framework 1, modernized for PHP 8.1–8.5). Read `AGENTS.md`, `ARCHITECTURE.md`, and `WEBSERVICES.md`
for the conventions these rules enforce.

## 🔴 Important — flag these even if the author says "low impact"

**Security (the platform's job is to be safe by default):**
- **SSRF.** Any user/admin-supplied host or URL that reaches a server-side fetch — `Tiger_Module_Github::get()`
  (marketplace/source/connect URLs), `Tiger_Location` adapters, registry/authority calls, media fetch. Require
  validation against private/link-local ranges, or a documented reason it's safe.
- **Injection.** SQL built from string concatenation instead of the query builder (`activeSelect()` /
  `$db->select()` + bound `where('col = ?', $v)`); command injection (`exec`/`proc_open`/backticks); template
  injection; unescaped output in a `.phtml` (XSS) — especially anything echoed into HTML without `escape`.
- **Cryptography.** `md5`/`sha1` for passwords or tokens; hand-rolled crypto instead of `Tiger_Crypto`
  (libsodium); a **missing or incorrect signature verification** (`Tiger_Crypto_Signature` Ed25519 for feeds,
  module artifacts, and license-authority replies; webhook HMAC for Stripe/GitHub); predictable/`mt_rand`
  values used as secrets (`mt_rand` is fine only for non-security things like a tiebreak); secrets or keys that
  get logged, returned in an API `data` payload, or committed (they belong in `local.ini`, gitignored).
- **Authorization.** `/api` is deny-by-default: every service needs an `acl.ini` rule (resource = service
  class, privilege = method). Flag a new service/method with **no ACL rule**, an admin-only action reachable by
  a lower role, a **role-string compare in code** instead of `Zend_Acl::isAllowed`, or a read that trusts a
  client-supplied `org_id` instead of scoping in the service (tenancy: writes are auto-stamped, **reads must be
  scoped server-side** — never trust the payload's org).
- **Licensing/entitlement (nag-never-disable).** A lapse/verdict path that *disables* a module (only an
  *update* may be withheld); treating `unknown` (unreachable authority) as `lapsed`; trusting an authority
  reply without verifying its signature.

**Correctness:**
- A mutation not wrapped in `_transaction()` (validate form → transaction → `_success`/`_error`), or business
  errors emitted as bare strings/raw exceptions instead of `_error`/`_formErrors` with a translation key.
- **Soft-delete traps.** `activeSelect()` excludes `deleted=1`; an insert/upsert that looks up via
  `activeSelect()` but the DB unique index still holds the soft-deleted row → duplicate-key crash on
  re-set-after-forget. (A real bug class here — the `set()` must revive, not blind-insert.)
- **Config-tier staleness.** The `config` tier is eager (folded into `Zend_Config` at boot); a mid-request
  `config` write is NOT reflected until the next boot. Flag code that writes config then reads it back from
  `Zend_Config` in the same request expecting the new value.
- Editing anything under `vendor/` to change app behavior (lost on `composer update`).

## 🟡 Worth a comment (not blocking)
- New `/api` surface with no test (unit or integration) for the security-relevant path.
- A new migration that isn't additive-only, or a domain table missing the standard columns
  (`status`/`deleted`/`created_by`/`updated_by`/timestamps).
- Config vs option misuse: per-user/per-entity state written to the eager `config` tier instead of the lazy
  `option` tier (the wp_options mistake).
- Page-POSTing a form to a controller or server-rendering list/table data instead of the `/api` message
  pattern (the UI is a client).

## Nits (report at most 8, then "plus N similar")
- `array()` instead of `[]` in **TigerCore/app** code (NOT TigerZF — `Zend_*` keeps `array()` to match upstream).
- Hardcoded user-facing strings instead of semantic owner-prefixed i18n keys (`core.*`/`app.*`/`<module>.*`).
- Missing/!thin docblocks on a new `@api` class or public method (the reference is generated from them).
- Naming/formatting preferences.

## Do NOT report
- Anything under `vendor/` (incl. `vendor/webtigers/tigerzf` — upstream ZF1, its `array()` and style are intentional).
- Generated artifacts: `var/docs-generated/`, `library/Tiger/OpenApi` generated output.
- Pre-existing issues outside the PR diff.
- Failures PHPUnit already covers (unit/integration/coverage run in CI).
- `array()` in TigerZF, or ZF1 idioms in code that must match the framework.

## Always sanity-check on a security-touching PR
- New Service method: validates input **before** any DB query, ACL rule present, response doesn't leak keys/paths/internal ids.
- New server-side fetch: is the target host/URL attacker-influenced? If so, is it SSRF-guarded?
- New signature/crypto: is verification actually enforced (fail-closed on a bad signature), and are keys kept out of logs/VCS/responses?
