# Tiger — building an admin screen

Every admin surface in Tiger — core's, and every module's — is built the **same way**, so the
whole back office looks and behaves like one product even though modules ship it. Follow this
template when you add an admin screen; don't invent a new shell. For UI primitives read the
"UI/UX" section of [AGENTS.md](AGENTS.md); for the `/api` contract read
[WEBSERVICES.md](WEBSERVICES.md).

> **Why a template at all?** The admin *layout* (sidebar, top bar, content well) is one theme
> file; the admin *screens* are per-module views. If each module hand-rolled its own header,
> spacing, and save button, a reskin would fix the shell but leave a patchwork of screens. This
> template keeps the **screen HTML** consistent and semantic, so reskinning the `admin` layout —
> or a tenant swapping a skin — restyles every module's screens at once. Consistency lives in the
> *convention*, not in copied CSS.

## The five pieces

An admin feature is always the same five parts. `Docs` (in the TigerDocs module) and `System`
(core) are the reference implementations — copy them.

### 1. Controller — extend `Tiger_Controller_Admin_Action`

Never call `setLayout('admin')` yourself; the base does it. The controller is **thin** — it reads
and renders; every mutation is an `/api` call, not a page-POST.

```php
class Docs_AdminController extends Tiger_Controller_Admin_Action
{
    public function settingsAction()
    {
        $form = new Docs_Form_Settings();
        $form->populate([...]);          // prefill from live config/model
        $this->view->title = 'Docs Settings — Tiger Admin';
        $this->view->form  = $form;
    }
}
```

A single action that must go full-screen (a builder, a canvas) calls
`$this->_helper->layout()->disableLayout();` in *that* action — init sets the default, the action
overrides.

### 2. ACL — admin+, deny-by-default

The controller and its `/api` service are resources in the module's `configs/acl.ini`, allowed to
`admin` (or `superadmin` for platform-level tools). No route-role compare in code.

```ini
acl.resources.docs_admin_ctrl.resource   = "Docs_AdminController"
acl.resources.docs_settings_svc.resource = "Docs_Service_Settings"
acl.rules.docs_admin_ctrl.role       = "admin"
acl.rules.docs_admin_ctrl.resource   = "Docs_AdminController"
acl.rules.docs_admin_ctrl.permission = "allow"
; ...same for the service
```

### 3. Settings-tree registration (for a settings page)

If the screen is the module's settings page, register it into the shared **Settings** tree from
the module Bootstrap — it appears under Settings in the sidebar, ACL-filtered live, no core edit:

```php
protected function _initAdminSettings()
{
    Tiger_Admin_Settings::register([
        'key' => 'docs', 'label' => 'Docs', 'icon' => 'fa-book',
        'href' => '/docs/admin/settings', 'resource' => 'Docs_AdminController', 'order' => 60,
    ]);
}
```

### 4. Form — declare elements; the view owns markup

Extend `Tiger_Form` (ViewHelper-only decorators — see AGENTS.md "Forms"). Validators run at submit
*and* on blur (convenience validation). The form declares fields; it renders no layout.

### 5. View — the standard screen skeleton

This is the consistent HTML. **Header** (title + one-line description on the left, primary
action(s) on the right) · a **feedback mount** · **card(s)** for the content · a footer script that
wires the save through the house primitives. Use semantic Bootstrap utilities only — **no bespoke
CSS, no inline `<style>`** (that's what keeps it reskinnable).

```php
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Docs</h1>
        <p class="text-body-secondary mb-0">Where your documentation site lives.</p>
    </div>
    <div class="text-nowrap">
        <button type="button" id="docs-settings-save" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk me-2"></i>Save
        </button>
    </div>
</div>

<div id="docs-settings-feedback"></div>

<form id="docs-settings-form" onsubmit="return false;" novalidate>
    <?= $this->form->getElement('_csrf') ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header fw-semibold"><i class="fa-solid fa-signs-post me-2"></i>Public route</div>
                <div class="card-body"><!-- fields --></div>
            </div>
        </div>
    </div>
</form>
```

The save is **always** this shape (never a page-POST): disable-during-flight via `TigerButton`,
POST to `/api`, feedback via `TigerDOM.notify`, inline field errors from `res.form`.

```js
document.getElementById('docs-settings-save').addEventListener('click', function () {
    var form = document.getElementById('docs-settings-form');
    var fb   = document.getElementById('docs-settings-feedback');
    var fd = new URLSearchParams(new FormData(form));
    fd.set('module', 'docs'); fd.set('service', 'settings'); fd.set('method', 'save');
    TigerButton.run(this, function () {
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }).then(function (res) {
        if (res && res.result === 1) { TigerDOM.notify(fb, 'Settings saved.', { type: 'success' }); return; }
        if (res && res.form) { Object.keys(res.form).forEach(function (f) {
            var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); }
        }); }
        (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
    }).catch(function () { TigerDOM.notify(fb, 'Network error — please try again.', { type: 'error' }); });
});
```

The matching `/api` service (`Docs_Service_Settings`) validates the form, writes via the model
(config tier for settings — the live-override pattern, no deploy), and returns the standard
envelope. See WEBSERVICES.md.

## The house rules for an admin screen

- **Extend `Tiger_Controller_Admin_Action`** — never set the admin layout by hand.
- **Header = `h3` title + `text-body-secondary` one-liner on the left, action buttons right.** One
  primary `btn-primary`; secondary actions `btn-outline-*`/`btn-light`.
- **Group content in `.card`s** with a `.card-header` label; one concern per card; use tabs only
  when a screen genuinely has multiple sections.
- **A `#…-feedback` div** above the form; drive it with `TigerDOM.notify` — never
  `innerHTML = '<div class="alert">'`.
- **Save through `/api`** with `TigerButton.run` (never a page-POST, never a `<button type="submit">`).
- **Semantic Bootstrap classes only — no bespoke CSS, no inline `<style>`.** Reskinning is a skin's
  job; a screen that hardcodes colors/spacing can't be reskinned.
- **Icons:** Font Awesome, `me-2` before label text. Save is `fa-floppy-disk` (yes, the floppy).
- **Strings** are translate keys (`_t()` / message keys), never hardcoded — see AGENTS.md i18n.

Match `Docs_AdminController` + `modules/system` (Settings) exactly and a third-party module's admin
screen is indistinguishable from core's — which is the point.
