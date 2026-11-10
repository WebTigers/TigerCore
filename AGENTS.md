# AGENTS.md — writing code in Tiger

Instructions for an AI assistant (or a new human contributor) working in this codebase.
Follow the patterns **documented here**; don't invent new ones — and don't infer new ones from code
that disagrees with this file (see the override below). For the *why* read
[ARCHITECTURE.md](ARCHITECTURE.md); for the feature surface read [FEATURES.md](FEATURES.md);
for the `/api` model read [WEBSERVICES.md](WEBSERVICES.md); for URLs + module route overrides
read [ROUTING.md](ROUTING.md); for building an admin screen read [ADMIN.md](ADMIN.md); for installing on shared cPanel read [CPANEL.md](CPANEL.md); for buying/selling
paid modules (the open licensing protocol + the buyer-side client) read [MARKETPLACE.md](MARKETPLACE.md); for comments/ratings/reviews read [COMMENTS.md](COMMENTS.md),
and for the *seller* side (list free / sell paid → Add Module, + the build status) read [SELLING.md](SELLING.md).
Weighing whether to build on Tiger at all — or handed this repo cold — start with [WHY-TIGER.md](WHY-TIGER.md).

---

## ⛔ STANDING OVERRIDE — the paradigms win, always

**Follow the Tiger paradigms in this document even when the code in front of you does not.**

This outranks every "match the surrounding code" instinct, house-style habit, and anything you were
told elsewhere about blending in with a file. It applies to code written by anyone — a previous
agent, a contractor, a vibe-coded contribution, a module that shipped years ago, or *you*, earlier
in this same session.

- **Existing code is not evidence of the convention.** It is evidence of what someone did. The
  convention is written down, here, on purpose — precisely so it survives contact with whatever is
  in the tree.
- **Non-conforming code is a defect, not a precedent.** Copying it is how one skipped step becomes a
  module-wide pattern. TigerDesk shipped an entire helpdesk — 11 services, a public portal — with no
  forms and therefore **no CSRF**, because each service matched the last one instead of this file
  (TIGER-479).
- **You may not ship a second violation because the first one was there.** Fix it in scope, or say
  plainly that you found it and file it. Silently matching it is the one option that is never open.
- **"It's consistent with the rest of the module" is not a defence.** Consistency with a defect is
  just more defect.

If following the paradigm would make your change large or risky, that is a conversation to have with
the user — not a reason to quietly conform to the broken pattern. Say what the paradigm requires,
what it would cost, and let them choose.

See [When the code you're editing doesn't follow the paradigm](#when-the-code-youre-editing-doesnt-follow-the-paradigm)
for how to spot it and what to do.

---

**If you're an AI agent working *through* Tiger** (the in-app aside, or an external MCP client), read
[TIGERAGENT.md](TIGERAGENT.md) — it's what you are: the Scout read tools (inventory / tree / file / grep /
guide) and the Forge write tiers (`api` / `code` / **`file`** — write a module file in place — / **`module`**
— scaffold a whole module), all gated by your ACL role. Do NOT build code locally and upload a zip to
iterate: scaffold with Forge `module`, then write and re-write files directly with Forge `file`. For driving
a Tiger install over MCP read [TIGERMCP.md](TIGERMCP.md); for installable know-how read [TIGERSKILLS.md](TIGERSKILLS.md).

> This file documents TigerCore + app conventions. Tiger is designed to be read and written
> largely by AI — so the docs live in the code. Match the surrounding style.

## Step 0: check what already exists — do NOT rebuild it

Tiger is large and most substrate is already built. Before you build (or claim something is missing),
**consult [CAPABILITIES.md](CAPABILITIES.md) first** — a generated, capability-grouped index of every
`Tiger_*` class + module with its one-line docblock summary, `@api` status, and path. `grep` it for the
thing you need (`grep -i install CAPABILITIES.md`, `grep -i 2fa CAPABILITIES.md`) before assuming it
isn't there. It's regenerated from docblocks (`bin/build-capabilities.php`) and CI-checked, so it can't
drift. **Assume a capability exists until you've grepped the index and the code and confirmed it doesn't.**
The docs answer *what/why* ([FEATURES.md](FEATURES.md) / [ARCHITECTURE.md](ARCHITECTURE.md)); CAPABILITIES.md
answers *"does it exist, and where?"*; [BACKLOG.md](BACKLOG.md) is the short list of what is genuinely
**not yet** built.

### Find a CONSUMER, not just the capability

CAPABILITIES.md answers *"does it exist, and where?"* — it does **not** answer *"how is it meant to be
used?"*, and that second question is where the real mistakes happen. Knowing `Tiger_Recaptcha` exists
tells you nothing about the three layers (form element → view helper → service validator) a working
reCAPTCHA actually needs. So before you write your first line against any platform feature:

```bash
grep -rn recaptcha modules/            # who already uses this, and how?
```

**Copy the pattern from a module that already does it.** One grep is cheaper than a design, and the
consumer shows you the layers a primitive can't. Reference consumers worth knowing:

| Doing this? | Read this first |
|---|---|
| A public/guest form (signup, contact, anything anonymous) | `modules/signup` — form + `_csrf` + reCAPTCHA + AJAX, end to end |
| An admin screen | `modules/system` Settings, `Docs_AdminController` ([ADMIN.md](ADMIN.md)) |
| A data grid | `modules/cms` `PageController` + `Cms_Service_Page::datatable` |
| An `/api` service | `Cms_Service_Page::save` — the canonical validate→transaction flow |

Building from primitives when a consumer already exists is how you end up with a *second*, subtly
wrong implementation of something the platform had — which is the one failure mode this whole file
is written to prevent.

**Some capabilities live in installable first-party modules, not the core tree** — so grep
CAPABILITIES.md *and* remember these before hand-rolling. The one to know: **TigerImage**
(`WebTigers/TigerImage`) — AI **image generation** for a Tiger site or an agent (generate → compare →
refine → keep, with lineage), provider-agnostic (it drives the same provider adapters the site already
has — no new AI account) and **spend-capped**. Need to create/refine an image, or give an agent that
power? Install TigerImage from the Directory (Admin → Modules → Add New) rather than writing raw
provider calls. Its admin docs live at `/docs/tigerimage`.

## When the code you're editing doesn't follow the paradigm

You are told to write code that reads like its neighbours. That rule is about *style* — naming,
spacing, comment density, which helper to reach for. **It never extends to a documented paradigm.**

When the file in front of you breaks a rule in this document, the rule wins. Local precedent is not
a convention; it is a defect that nobody has fixed yet, and copying it is how one skipped step
becomes a module-wide pattern. This is not hypothetical: TigerDesk shipped a whole helpdesk — 11
services, 41 methods, a public portal — with **no forms and therefore no CSRF**, because each new
service matched the last one instead of this file (TIGER-479).

So when you touch non-conforming code:

1. **Notice it.** The symptoms below are greppable on purpose. Run them before you edit a service.
2. **Fix it in scope** if the conversion is small and your change already touches that method.
3. **Otherwise flag it** — tell the user plainly and file it. Shipping a second violation because
   the first one was there is the one outcome that is never acceptable.
4. **Never silently match it.** If you decide not to convert, say so and say why.

**Finding it.** These greps find **candidates, not defects** — read the hit before you act on it. A
grep that cries wolf is one people learn to ignore, so each is paired below with what *disqualifies*
a hit:

```bash
# Candidate: a service that never touches a form.
grep -rLE "_Form_|->isValid\(" modules/*/services/*.php
# NOT a defect when the service is read-only (reports, renderers, search). The test is two-part:
#   does it have a method that takes USER INPUT and WRITES?  AND does it have no form?
# Only then is it a missing Tiger_Form. Core has ~32 form-less services and almost all are fine.

# Candidate: hand-validation.
grep -rnE "trim\(\(string\)|filter_var\(|=== ''" modules/*/services/
# NOT a defect on its own — reads legitimately coerce params, and Cms_Service_Page has ~21 such
# lines while using forms correctly. It only matters on a WRITE path in a service with no form.

# Candidate: a write surface with no CSRF token rendered.
grep -rLn "_csrf" modules/*/views/scripts/*/*.phtml
# NOT a defect for a read-only screen (a dashboard, a report). It is one for anything that POSTs.

grep -rn "<script|<style" modules/*/views/scripts/      # behavior/styling in a view (see the views rule)
grep -rnE "\\\$db->query\(|->fetchAll\(\"" modules/*/services/   # raw SQL -> belongs in a model finder
```

The quickest reliable check is still the two-part one, by hand: **list the service's public methods,
mark the ones that take user input and write, and ask whether each validates through a form.**

A rule that only fires when someone writes a file from scratch protects nothing — most code is
written next to code that already exists.

## The cardinal rule: extend, don't edit

`vendor/` is Tiger-owned and replaced by `composer update`. **Never edit framework files to
change app behavior** — you'll lose it on the next update. Extend instead:

- Add `_init*` methods in the app's `Bootstrap` (which extends `Tiger_Application_Bootstrap`).
- Subclass (`App_Service_Base extends Tiger_Service_Service`), override config `.ini`, or add
  a module — don't patch `Tiger_*`.
- `@api` = stable to build on; `@internal` = may change.

## The four layers

`Zend_*` (engine, in TigerZF) · `Tiger_*` (platform) · `App_*` (app-shared, route-less) ·
**modules** (features with routes/UI). A feature with controllers/routes/ACL/views is a
**module**; shared plumbing with no routes is the **app library**.

## Code conventions

- **Short arrays `[]`, never `array()`** — in TigerCore and app code. (TigerZF keeps `array()`
  to match upstream ZF1; don't touch it.)
- **Docblock every class and non-trivial method**, explaining intent, not mechanics. Comment
  density here is higher than typical — keep it.
- Constants for magic values; guard clauses over deep nesting; `Throwable` in catches.
- **No URL query params for navigation.** Use ZF1 path-style params — `/auth/login/out/1`, not
  `?out=1` (the `:controller/:action/*` route folds trailing pairs into `getParam()`); tokenized
  links are path-style too. A return/intended-destination path lives in the **session**, never the
  URL (see `Tiger_Service_Authentication::setReturnTo`/`takeReturnTo`) — avoids `%2F` path 404s and
  history leakage.

## Docblocks — the reference contract

The public API reference is **generated from docblocks**, so consistency here is what makes that
possible. Every `@api` class and every **public method** follows this shape. Protected/private
methods aren't part of the contract — document them where helpful, but they're not required and
never appear in the reference.

**Class docblock** — the SPDX line lives in the top-of-file `//` comment, so the docblock is purely
the API description:

```php
/**
 * Tiger_Model_Table — <one concise sentence: what this class IS>.
 *
 * <Longer description: the design, the why, how to use it. Paragraphs encouraged — the reference
 *  renders these as the class page body.>
 *
 * @api                       ← REQUIRED on every class: @api (stable, build on it) or @internal
 * @see  Tiger_Model_Org      ← optional; becomes a cross-link
 * @since 0.4.0               ← optional
 */
```

**Public method docblock** — a summary plus the tags the generator reads. Types stay in the
signature (PHP 8); the docblock adds the *descriptions*:

```php
/**
 * <One line, imperative — "Resolve a doc across both sources.">
 *
 * <Optional longer description.>
 *
 * @param  string $slug   the URL slug (may be nested)
 * @param  int    $limit  max results
 * @return array          the ranked hits
 * @throws Zend_Db_Exception on a DB failure
 */
```

Rules:
- **Fixed tag order:** description → `@param` (one per parameter, in order) → `@return` → `@throws`
  → `@see`/`@deprecated`. Keeps the parser trivial and the output uniform.
- **Every public method gets a docblock** with a summary, a `@param` for each parameter, and a
  `@return` (use `@return void` for no return value). Constructors included.
- **`@param <type> $name <desc>`** with a real type expression (`string`, `int`, `?Foo`,
  `Foo|null`, `array<int,string>`) — the generator prefers the reflected type and uses this line for
  the description.
- **Augment, don't rewrite.** Existing prose is good; this is about adding the *tags* + filling
  gaps, never redoing what's there. Never change code, signatures, or behavior.

## Client/server: the UI calls `/api`, it does not post to pages

Tiger apps are **client/server**. The server renders the initial HTML — the page *shell* and
public CMS page bodies — and from there **the browser is a client that exchanges data with
`/api` over AJAX**. It does **not** submit `<form>` page-POSTs to a controller and re-render the
whole page. Every dynamic interaction is a Tiger Webservice call:

- **Auth & session** — login, logout, lock-screen unlock, signup, password reset are AJAX. (Auth
  is a *reserved* kernel service, so these post to a thin controller that returns JSON — e.g.
  `/auth/login` — rather than literal `/api`; it's the same AJAX-JSON contract. See
  WEBSERVICES.md §8.)
- **Data in** — every insert / update / delete is an `/api` service call (validate → transaction);
  the JSON response drives the client (redirect, inline field errors, toast).
- **Data out** — list / table data (DataTables, Select2, autocompletes) is **fetched from `/api`**,
  not server-rendered into rows. DataTables loads its rows over AJAX and the service returns the
  DataTables shape (`{data:[…]}` for an ajax source, or `{draw, recordsTotal, recordsFiltered,
  data}` for server-side processing). See WEBSERVICES.md §5.

**SSR is for the shell, not the data.** A controller action renders the initial screen and then
gets out of the way; the logic and the data live in services. The payoff is the front-end-agnostic
contract (FEATURES.md): the same `/api` feeds the PUMA SSR theme, a future SPA theme, or a mobile
client, because the UI is always just a client.

## UI/UX: polished by default (don't hand-roll it)

The difference between a cheap-feeling UI and a polished one is almost entirely in the small
moments — how a button behaves while it's working, how a message arrives and leaves. Tiger ships
two tiny vanilla helpers (zero deps, in the PUMA theme) that make the *polished* path the *easy*
path. **Use them; never hand-roll a spinner, a busy flag, or `innerHTML = '<div class="alert">'`
again.**

- **`TigerButton.run(btn, task)`** (`tiger.button.js`) — wrap the promise behind any AJAX action
  button. It disables the button, swaps its icon to a spinner (injecting one if the button has
  none), holds a **minimum visible time (~400ms)** so the state actually registers even on instant
  calls, then restores + re-enables — **failure-safe** (`finally`) and `aria-busy`. The task must
  *return* the fetch chain; chain your response handling onto `run()`'s return:
  ```js
  TigerButton.run(this, function () {
      return fetch('/api', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
  }).then(function (res) { /* … */ }).catch(function () { /* … */ });
  ```
- **`TigerDOM.notify(container, msg, {type})`** / **`.toast(msg, {type})`** (`tiger.dom.js`) — the
  message envelope. Builds the themed alert (icon + message + close) and **reveals** it
  (container expands, then the content fades in — never slammed on), with smart defaults:
  **success auto-dismisses (~5s), errors stick, pause-on-hover, ✕/click to dismiss.** `notify`
  targets an inline feedback element; `toast` floats it top-right.
  ```js
  TigerDOM.notify(fb, 'Settings saved.', { type: 'success' });
  TigerDOM.notify(fb, m.message, { type: m.class });   // res.messages[].class maps straight through
  ```
- **`TigerDOM.expand/collapse/toggle`** — the underlying reveal primitives (Web Animations API,
  interruptible, `prefers-reduced-motion`-aware). Reach for these for any show/hide (submenus,
  accordions, panels) instead of a jQuery slide or a raw `display` flip.
- **`TigerModal.confirm/prompt/alert`** (`tiger.modal.js`) — the in-app replacement for the browser's
  native `confirm()`/`prompt()`/`alert()`, which are **never** used (unstyled, un-themeable, freeze the
  tab). Promise-based, themed, and it builds its one reusable Bootstrap modal on demand so a view needs
  no modal markup: `confirm()`→`Promise<boolean>`, `prompt()`→`Promise<string|null>` (`null` on cancel,
  mirroring `window.prompt` so `if (v === null) return;` swaps straight in). Enter submits; the field
  focuses on open. Depends only on Bootstrap (loaded ahead of it in every layout).
  ```js
  TigerModal.confirm({ title: 'Remove card', body: 'Remove this card?', confirmLabel: 'Remove', variant: 'danger' })
      .then(function (ok) { if (!ok) { return; } /* … */ });
  ```

The rule of thumb: **a control should always say what it's doing.** A save button that just sits
there, or a message that blinks into existence and never leaves, is the tell of a cheap UI. These
helpers are the house style — every admin + auth screen already uses them; match that.

## Views: a `.phtml` owns MARKUP, never behavior or styling

**No `<script>` and no `<style>` tag in a view — not an inline blob, and not a `<script src>`
either.** Behavior is a JS asset file, styling is the skin's job, and the theme **layout** is the
one place a tag is emitted. A view *registers* what it needs and the layout emits it:

```php
$this->pageScript('/_modules/shop/js/shop.cart.js', ['defer' => true]);  // → the pageScripts slot
$this->pageScript($this->themeAssets . '/vendor/sortable/Sortable.js');  // a vendored lib, same way
$this->pageStyle('/_modules/shop/css/shop.css');                         // → the pageHead slot
```

Both cache-bust through `asset()`, pass a remote URL through untouched, dedupe by URL, and keep
registration order — so a library still loads ahead of the script that uses it. `style="…"` on an
element is fine; a `<style>` block is not.

Three things a script used to reach for a PHP tag to get, and where they come from instead:

| Needs | Use |
|---|---|
| a localized string | `$this->i18n(['saved' => 'shop.cart.saved'])` in the view → `Tiger.t('saved')` in the JS |
| a server value (an id, a URL, a config flag, a JSON payload) | a `data-*` attribute on a container the JS reads |
| a list of rows | `/api` — never a payload baked into the page (WEBSERVICES.md §5) |

Why it is a hard rule and not a preference: a view full of behavior cannot be reskinned, cannot be
cached, cannot be CSP'd, and cannot be read — and an inline blob is invisible to every JS tool in
the repo. Keeping the i18n in the carrier has a second payoff: the page ships only the strings its
own JS uses, as translated *values* under generic aliases, so viewing source never exposes the key
taxonomy.

Three structural exemptions, none a matter of taste: an **email template** (mail clients drop
`<link>`, so inline CSS is the correct answer); a view that **is the document** — a full-screen
surface whose controller disabled the layout, like the CMS builder, which is a layout wearing a
view's filename and has no layout to register into; and a **JSON-LD** `<script
type="application/ld+json">`, which carries data rather than behavior and must be inline by spec.

`php tests/ui/bin/paradigm-scan.php` reports every violation across the tree, with those two
exemptions built in.

## Services (`/api`) — the canonical flow

Services extend `Tiger_Service_Service`; the message's `method` names the action, which
receives the whole `$params`. **Always validate a form first, then wrap mutations in a
transaction:**

```php
public function create(array $params): void
{
    if (!$this->_isAllowed('create')) { $this->_error('core.api.error.not_allowed'); return; }

    $form = new Billing_Form_Invoice();
    if (!$form->isValid($params)) { $this->_formErrors($form); return; }   // sparse PATCH? isValidPartial() is safe on a Tiger_Form — see below

    try {
        $id = $this->_transaction(function ($db) use ($params) {
            // ... inserts/updates; throw to abort + roll back ...
            return $newId;
        });
        $this->_success(['id' => $id], 'billing.invoice.created');
    } catch (Throwable $e) {
        $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
    }
}
```

Never emit business errors as bare strings or raw exceptions — use `_error`/`_formErrors`
with a translation key. Never put business logic in a controller; controllers are thin.

**Declaring the ACL is part of writing the call, not an afterthought.** `/api` is deny-by-default:
the dispatcher checks `isAllowed($role, <ServiceClass>, <method>)` — **the resource is the service
class, the privilege is the method name**. A service with no allow rule is refused outright, so
every service you add needs an entry in the module's `configs/acl.ini`:

```ini
; resource = the service class; a rule with NO privilege allows ALL its methods to the role
acl.resources.billing_invoice_svc.resource = "Billing_Service_Invoice"
acl.rules.billing_invoice_svc.role         = "admin"
acl.rules.billing_invoice_svc.resource     = "Billing_Service_Invoice"
acl.rules.billing_invoice_svc.permission   = "allow"
```

Adding a method to an already-allowed service inherits that blanket allow — correct when the whole
service shares one role (an admin-only settings service, a guest-allowed search service). When
methods need *different* access (a public read on an otherwise-admin service), name the method as
the `privilege` on a scoped rule instead of a blanket one. The in-method `_isAllowed('<method>')`
guard is defense-in-depth (and the only gate for an in-process caller) — it does not replace the
`acl.ini` rule.

## Forms

Extend `Tiger_Form` (not `Zend_Form` directly). Declare elements as an array schema via
`elements()` (array config, not `.ini`); the base handles POST method, ViewHelper-only
decorators (the view owns markup), CSRF, and the `_t()` translate helper.

```php
class Billing_Form_Invoice extends Tiger_Form
{
    protected function elements(): array
    {
        return [
            ['text', 'amount', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Float']],
                'attribs'    => ['class' => 'form-control', 'placeholder' => $this->_t('billing.invoice.amount')],
            ]],
        ];
    }
}
```

**Convenience validation is free — declare validators once.** The validators you put on an
element run at submit (`isValid()`) *and* on blur, before submit: add `data-tiger-validate
data-module="<mod>" data-form="<FormName>"` to the `<form>` and `tiger.validate.js` posts each
field to `Tiger_Service_Validate`, which runs that element's real validators
(`Tiger_Form::convenienceValidate`) and shows the first error inline. So don't hand-roll
per-field AJAX checks — just declare the validator. **`modules/signup/forms/Signup.php` +
its view are the reference form**: every field type, DB-uniqueness (`Zend_Validate_Db_NoRecordExists`),
password policy (`Tiger_Validate_Password`), address autocomplete, and the localized country
picker (`Tiger_I18n_Country`). Copy that when building a new form.

Platform helpers worth knowing before you build your own: **`Tiger_Location`** (address /
geocode / reverse / IP behind adapters, normalized `Place` payload) and **`Tiger_I18n_Country`**
(biased, localized country list). Both are config-driven and reachable via the public
`Tiger_Service_Location` when a form needs them.

### ⚠️ `isValidPartial()` only validates keys that are PRESENT — so the token must be present

`Zend_Form::isValidPartial()` validates **only the keys present in the data** (its loop body is
guarded by `isset($check[$key])`). `_csrf` is a key like any other, so on the *stock* ZF1 method a
caller who simply **omits** the token is never checked for it — the CSRF gate becomes opt-out, on
exactly the sparse PATCH-style writes people reach for it on.

Verified by mutation, not theory: swapping `isValid()` for `isValidPartial()` in
`Desk_Service_Ticket::update` let a tokenless cross-site edit through with `result=1` (TIGER-479).

**The fix is to make the token present, which is what `Tiger_Form::isValidPartial()` now does for
you** — it force-sets every hash element (to `''` when absent) before delegating to the parent, so a
tokenless sparse PATCH fails exactly as a full `isValid()` would. A hash element is a **gate**, not
data; an ordinary required field still legitimately stays absent, which is the entire point of a
partial validation.

So on a `Tiger_Form`, **`isValidPartial()` is the right tool for a sparse PATCH** and needs nothing
extra. Two things to remember anyway:

- If you call `isValidPartial()` on a plain `Zend_Form` (not a `Tiger_Form`), **you** own this —
  seed the token into the data yourself (`$data['_csrf'] = $data['_csrf'] ?? '';`) or it is not
  validated.
- The equally valid alternative is the full `isValid()` with every field declared
  `'required' => false` — ZF1 returns `true` for an optional element whose value is absent, while
  `_csrf` stays mandatory because `Tiger_Form_Element_Hash` is `required`. That is what TigerDesk's
  ticket/macro updates do, and it is fine; it just isn't the only safe option.

Two neighbouring gotchas from the same conversion:

- **An optional element must NOT set `allowEmpty(false)`.** `Zend_Form::isValid()` hands a missing
  element `null`, which `allowEmpty(false)` then sends through the validators — so "optional, but
  not blank when sent" silently becomes "required". An element cannot tell *sent blank* from *not
  sent*; that is a presence question, so ask it in the service against the raw params and push the
  answer back with `$form->getElement($f)->addError($key)` so it still reaches the client as a
  per-FIELD error.
- **`StringTrim` on a param that may arrive as an ARRAY** (a tag list, a custom-fields map) casts
  with `(string) $value` — an "Array to string conversion" warning, which a suite with
  `failOnWarning` treats as a failure. Declare those elements with no filters and let the service's
  normalizer own the shape.

### Several forms on one page? Share the CSRF salt

`Tiger_Form::csrfSalt()` defaults to the form class, so two forms mint two tokens in two session
slots. A screen that renders one token and then posts to a second endpoint fails with "your security
token expired" while behaving perfectly. Put one salt on a shared base for the set — see
`csrfSalt()`'s own docblock, and `Desk_Form_TicketWrite` for a worked example.

### A PUBLIC (guest-reachable) form — the checklist

An endpoint a stranger can POST to needs all of this, and the pieces live in different files, so
they are easy to half-do. `modules/signup` implements every line; read it rather than assembling
this from primitives.

| | How | Why it is not optional |
|---|---|---|
| **A `Tiger_Form`** | declare `elements()` | everything below hangs off it |
| **CSRF** | automatic — the base adds `_csrf`; the **view must render it** (`<?= $el('_csrf') ?>`) and the **JS must post it back** | a form whose view omits `_csrf` has no CSRF protection at all, silently |
| **reCAPTCHA** | `['recaptcha', 'recaptcha', ['action' => '…']]` | the element self-attaches `Tiger_Validate_Recaptcha`; building from `Tiger_Recaptcha::verify()` by hand is how you get a gate that doesn't gate |
| **A honeypot** | a hidden field; on a hit **silently accept and drop** | telling a bot it failed just helps it iterate |
| **Rate limiting** | per-IP / per-identity, as the comment store does | the honeypot and reCAPTCHA stop scripts, not a determined human |
| **Guest vs signed-in** | branch the form (`['guest' => …]`) and the view together | a signed-in caller is already identified; challenging them is friction with no gain |

**Never read a verify response as a boolean.** `Tiger_Recaptcha::verify()` returns an **array** —
`['success' => false]` for a bad or absent token — so `(bool) verify($token)` is `true` on failure.
That exact cast shipped in TigerDesk's public portal and made its bot gate a no-op that passed
everything (TIGER-479). Use the element, or `Tiger_Validate_Recaptcha::isValid(null, $params)` as
`Signup_Service_Signup::create` does; both honour `failOpen()` and the v3 score.

⚠️ **AJAX-on-click forms and v3:** `Tiger_View_Helper_FormRecaptcha`'s v3 path binds the **form's
`submit` event**. A form that submits from a `<button type="button">` handler never fires one, so
the token stays empty and every submit is refused. Mint it in JS before the call —
`grecaptcha.execute(site, {action})` → write it into `input.g-recaptcha-response` — exactly as
`signup.index-index.js` does.

## Models & schema

- Extend `Tiger_Model_Table`. It mints the UUID PK on insert (v7 default; set
  `$_uuidVersion = 4` for opaque tokens), stamps timestamps + `created_by`/`updated_by`, and
  soft-deletes. Domain finders build on `activeSelect()` (excludes deleted).
- **Build every query with the query builder — never a raw SQL string.** `activeSelect()` /
  `$db->select()->from(...)` + bound `where('col = ?', $v)`, and `new Zend_Db_Expr('COUNT(*)')` for
  aggregates. Parameterized, portable, injection-safe. No `$db->query("SELECT …")` /
  string-concatenated SQL in models or services.
- Every domain table gets the **standard columns**: `status`, `deleted`, `created_by`,
  `updated_by`, `created_at`, `updated_at`. UUIDs are `CHAR(36)`; unique text is `VARCHAR(191)`.
- **JSON-shaped data → `LONGTEXT`, never the `JSON` type.** The DB stores text; validating JSON is the
  app's job (you already `json_encode`/`json_decode` it). MariaDB's `JSON` is `LONGTEXT` + an implicit
  `CHECK(json_valid())` that **rejects JSON nested ≥ 32 levels** (which PHP encodes fine) — it broke the
  CMS builder's deep project blob (migration `0041` converted the existing columns).
- Migrations are **additive-only** PHP files (`NNNN_name.php` returning `['up'=>[], 'down'=>[]]`)
  in `migrations/`. One logical DDL change per migration (MySQL auto-commits DDL).
- Migration versions are **one namespace across core, the app and every module**. The ledger is keyed by
  (version, name), so two files sharing a number both run, but `migrate:status` flags it. In a module, use a
  time-of-day suffix (`YYYYMMDDHHMMSS_name.php`), never a bare `YYYYMMDD000001` another module can pick too.
  Don't rename an applied migration's file at the same version unless its old name is gone from every
  module (the migrator treats an unclaimed ledger name at that version as a rename).

## Authorization

Deny-by-default. Access is data in `acl.ini` / `acl_*` tables, never a hardcoded role compare.
A resource with no allow rule is denied. Role comes from `org_user` (role-on-membership) and
is resolved live each request.

**Know a role's SCOPE before you write a finder — [ACL.md §1a](ACL.md) is the standard.**
`user` is SELF-scoped, `manager` is ORG, `supermanager` is ORG TREE, and **`admin`/`superadmin`/
`developer` are APP-WIDE** — they administer every org. So the org scope is the SERVICE's decision
from the caller's role, passed into the model finder; a finder that hard-codes
`org_id = <caller's org>` has decided no app-wide role exists. And a missing org filter is only a
defect if a tenant-scoped role can actually reach that surface — read the `acl.ini` grant first.

Module `configs/acl.ini` grants access to that module's controllers/services. Two resource shapes: a **controller** dispatch gates on
`isAllowed($role, <Controller>, <action>)` (privilege = action); an **`/api` service** gates on
`isAllowed($role, <ServiceClass>, <method>)` (privilege = method). Declaring the rule is part of
writing the call — see "Services (`/api`)".

### In-method authorization — the one primitive (TIGER-335)

The dispatcher gates every `/api` call, but a public service often needs a finer, in-method check
(an admin-only method on a user-reachable service, or a question about *someone else's* role). There
is **ONE primitive for this, and it never compares a role string**:

- **`_isAllowed($privilege, $resource = null)`** — "does the CALLER hold `$privilege`?" `$privilege`
  is required and first (so a resource-level "any privilege" check — the TIGER-114/F5 footgun — can't
  be written by accident); `$resource` defaults to the service class.
- **`_roleIsAllowed($role, $privilege, $resource = null)`** — the same question about **any** role,
  including a third party's (e.g. "may the member I'm about to act on do this?"). Both fail **closed**.

The four rules that keep the F5 class from recurring — follow them in every new service + `acl.ini`:

1. **Name the privilege = the method**, one rule per privilege. **No blanket resource-level grant on
   any service a non-admin role can reach.** A blanket grant is only acceptable where the whole
   service shares one role (an admin-only settings service). Adding a method then *requires* adding
   its rule — access is never granted by omission.
2. **A cross-cutting capability gets its own named resource** (`Tiger_Message_Compose`,
   `Tiger_Agent_Forge`) — named for the **capability**, never a role tier. A resource named after a
   role tier (`..._Admin`) is a role compare with one level of indirection.
3. **Ask `_isAllowed($privilege)` about the caller, `_roleIsAllowed($role, $privilege)` about anyone
   else.** Never a role string, never `inheritsRole()` as a seniority test — role inheritance in
   `acl.ini` already makes a renamed/added admin-class role Just Work.
4. **A feature flag is checked AFTER authorization, never instead of it** (authz before the flag).

The old role comparers `_isAdmin()` / `_isAtLeastAdmin()` / `Tiger_Message::isAtLeastAdmin()` were
**removed** (TIGER-335/358) — do not reintroduce them; a guard test fails the build on a role-string
comparison in a service. `modules/message` is the reference conversion.

## Internationalization

- **Never hardcode user-facing strings.** Use a semantic, **owner-prefixed** key: `core.*`
  (framework), `app.*` (app), `<module>.*` (module), then `.area.type.name` —
  e.g. `billing.invoice.created`, `core.api.error.not_allowed`.
- Put strings in PHP array files: `<module>/languages/<lang>/<module>.php` returning
  `['key' => 'text']`. Locales are **language-only** (`en`, `es`).
- Response messages translate automatically (`Tiger_Model_MessageObject`). In a **form** use
  `$this->_t('key')` (`Tiger_Form::_t`). In a **view** there is **NO `_t` helper** — pull the
  registered translator: `Zend_Registry::get('Zend_Translate')->translate('key')` (or a small local
  `$t = fn($k) => Zend_Registry::get('Zend_Translate')->translate($k);` closure). `$this->_t()` /
  `$this->view->_t()` in a view/controller throws `Plugin '_t' not found`.

## The live-override pattern (reuse it)

Config and translations both follow: **files/ini = base, a DB table = the runtime override
tier (last wins), no deploy.** When you build a new "options/settings" surface, mirror this —
a `*_key`/`*_value` table with `scope` (global|org) + `scope_id`, loaded on top of the base in
a bootstrap `_init*`. See `Tiger_Model_Config` / `Tiger_Model_Translation`.

## Scaffolding & housekeeping

- New feature → `vendor/bin/tiger make:module <name>` (gives a live controller + `/api`
  service + ACL + views + config to build from).
- Keep secrets out of the repo — DB creds and keys go in `local.ini` (gitignored) or a secrets
  manager, never in `application.ini` or committed files.

## Anti-patterns (don't)

- Don't edit anything under `vendor/`.
- Don't use `array()` in TigerCore/app code.
- Don't hardcode user-facing strings, roles, or config — use keys, ACL data, and config.
- Don't put logic in controllers, or run mutations without a form-validate + transaction. **The
  symptom is `trim((string) $params[…])`, `filter_var(…)` or `=== ''` inside a service** — that is
  hand-validation, and it means a missing `Tiger_Form`. It costs you CSRF (the base adds `_csrf` to
  every form), per-field errors (`res.form`), and on-blur convenience validation — none of which can
  be retrofitted without a form. Reads may coerce a param; **writes declare a form.**
- Don't build REST-by-URL endpoints — use the `/api` message pattern.
- **Don't put routes in a Bootstrap — that's cruft.** Routes are *config*, not code. Prefer **automagic
  default MVC**: name the controller/action so the URL falls out (`/foo` → `FooController::indexAction`,
  `/index/vibe` → `IndexController::vibeAction`) and register **nothing**. When a *pretty alias* is
  genuinely needed, it goes in a **`.ini`**, never a Bootstrap `_init`/`addRoute`:
  - **Core / default-namespace** aliases → **`configs/routes.ini`** (ZF1-native
    `resources.router.routes.*`, folded into the config cascade; the Router resource applies them).
  - **Module** pretty aliases → a **`Tiger_Routing_Overrides`** override (one authority owns ordering;
    see ROUTING.md).
  Reach a feature at its canonical `<module>/<controller>/<action>` path by default; only add a route
  for a nicer URL.
- Don't page-POST forms to controllers or server-render list/table data — the UI is a client
  that calls `/api`; controllers render the initial shell only (see the client/server section).
- Don't hand-roll a button spinner/busy flag or `innerHTML = '<div class="alert">'` — use
  `TigerButton.run` and `TigerDOM.notify`/`toast` (see "UI/UX: polished by default").
