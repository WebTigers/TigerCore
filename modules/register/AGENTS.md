# AGENTS.md — the `register` module

The optional site-registration prompt. A first-party module bundled in tiger-core (BSD-3), active by default.
It's the install-side **client** of the TigerRegistry authority (registry.webtigers.com).

## The one principle: it disables NOTHING

Registration is 100% optional and gates no feature anywhere; the module touches no other module. The offer is
a **dashboard widget** (`Register_Widget_Registration`) the user places, collapses, or switches off like any
other. The **opt-out** is switching the widget off or **deactivating this module** — no widget, no prompt.
Nothing is sent anywhere until the admin submits.

## How it works

- **`Register_Widget_Registration`** — the dashboard widget body (registered in the Bootstrap via
  `Tiger_Dashboard::registerWidget`). Renders the current step (register email → verify domain → verify email →
  "registered"); each action posts to `/api`.
- **`Register_Service_Registration`** (`/api`, admin): `register(email)` → registry `site/register` → stores
  the TSID + a domain token this install **auto-serves** at `/.well-known/tiger-verify.txt`
  (`Register_VerifyController::domainAction`, routed in `configs/routes.ini`) → self-verifies the domain;
  `verifyDomain`; `resendEmail`; `status`. Registry transport injectable (`setTransport`), URL
  `register.registry_url` (default `registry.webtigers.com`).
- **`Register_Service_Status`** — read-only progress (`hasStarted` / `isDomainVerified` / `isEmailVerified` /
  `isVerified` / `tsid` / `state`). **Gates nothing.** `tsid()` is the verified id the marketplace Federation
  reads. Fail-safe: unreadable state → nothing-done.
- **Two proofs:** domain control (strong, near-automatic — the install serves its own token) and email
  (the human channel, via the magic link `/register/verify/email/token/<t>`).

## Conventions

BSD-3 headers (bundled in core). State in the config tier (`register.*`, SCOPE_GLOBAL). Admin form keeps CSRF;
the widget submits a token via `Register_Form_Register->getElement('_csrf')->getHash()`. Aimed at the CMS
operator — a composer/vibe-dev who doesn't care just deactivates it.
