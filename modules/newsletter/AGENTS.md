# AGENTS.md — working on the `newsletter` module

> **Step 0 — check core before you build.** Before writing any mechanism, grep
> [`../../CAPABILITIES.md`](../../CAPABILITIES.md) — a generated, CI-checked index of every `Tiger_*`
> class and module. Core probably already has it. **Assume a capability exists until you have grepped
> the index and confirmed it doesn't.**

Module-specific layer; for platform conventions read the root **AGENTS.md**. Match the surrounding style.

> Free, first-party **subscriber collection + a simple embeddable form**. It COLLECTS consent-first
> newsletter opt-ins and exposes the confirmed ones as an **audience segment** (`Tiger_Audience`) for an
> email tool such as **TigerList** to send to. It does NOT send campaigns and does NOT own consent policy.

## The boundary (do not blur it)

`registry = entitlement, TigerList = consent, a send = the intersection.`

- This module owns **collection**: an opt-in row with a status (`pending` → `confirmed` → `unsubscribed`),
  a `consent_source` + `consent_at`, and an opaque `token` for the confirm / unsubscribe links.
- The **audience provider** (`Newsletter_Service_Audience`) offers only **confirmed** subscribers as the
  `newsletter:confirmed` segment. It conveys opt-in membership, never a licence to email — the consumer
  (TigerList) still applies its own consent / suppression before a send.
- **Double opt-in here is collection confirmation, not campaign machinery.** A free standalone install has
  no reachable generic consent path (TigerList is a separate, paid module and its double opt-in is
  list-scoped), so the module sends its own confirmation email and redeems the token itself. It does not
  duplicate TigerList's sending or suppression.

## Where things live

- `models/Subscriber.php` — `Newsletter_Model_Subscriber` (table `newsletter_subscriber`, migration 0051).
- `services/Subscribe.php` — the `/api` surface: `subscribe` / `confirm` / `unsubscribe` (guest) + `datatable` (admin).
- `services/Audience.php` — the `Tiger_Audience` provider (`listSegments` / `resolveMembers`) + admin `/api` (`segments` / `members`).
- `services/Render.php` — the `[newsletter_form]` shortcode's server-rendered form (in-process, not a `/api` service).
- `forms/Subscribe.php` — email (required) + name (optional). **CSRF disabled on purpose** (public, cache-served, double-opt-in-protected).
- `controllers/IndexController.php` — public confirm / unsubscribe landing pages (routed under `/newsletter`).
- `controllers/AdminController.php` — the subscriber list (grid loads from `/api`).
- `Bootstrap.php` — registers the shortcode, the admin nav item, and the audience provider (each guarded).

## Conventions + gotchas (this module)

- **Never auto-confirm.** `subscribe` stores `pending` and emails a confirm link; `confirm` (token) records
  consent. The subscribe response is IDENTICAL for a new / pending / already-confirmed address — no list
  enumeration.
- **Anti-bot** mirrors the comment module: honeypot `_hp`, a too-fast `_t` gate, and a per-IP rate limit
  (guests have no user id, so the address is the only handle).
- **Org scoping.** Rows are stamped with the request org via the base-model seam; a guest on a single-site
  install resolves to the global org (`''`). The admin grid and the audience provider scope to the caller's
  org **plus** the global scope, so a single-site operator sees guest opt-ins while real multi-site (where
  the host→org plugin stamps every request) stays isolated.
- Mail failure in `_sendConfirmation` is logged, **not fatal** — the row still exists.

## ACL

guest: `Newsletter_Service_Subscribe` (`subscribe`/`confirm`/`unsubscribe` privileges only) +
`Newsletter_IndexController`. admin: `Newsletter_Service_Subscribe::datatable`, `Newsletter_Service_Audience`,
`Newsletter_AdminController`. Privilege-scoped so the admin ops are never advertised to guests.

## Do / Don't

- **Do** feed confirmed subscribers to consumers through `Tiger_Audience`; let the consumer own the send.
- **Don't** add campaign sending, segmentation beyond the one confirmed-subscribers segment, or a consent
  suppression engine — that is TigerList's job.
