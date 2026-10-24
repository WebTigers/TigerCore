// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * identity.admin-index.js
 *
 * Site Identity — the site's name, tagline, description, logo, favicon, share image, and social
 * links. ViewHelper-only form (the view owns markup); logo, favicon + the share image use the
 * platform media picker (Choose from the Media Library or upload), and the share image adds a
 * URL element for an image that isn't in the library. Saving is an /api call to
 * Identity_Service_Identity; the JSON response drives the feedback. Semantic Bootstrap only — no
 * bespoke CSS (keeps it reskinnable), per ADMIN.md.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('identity-form');
    var fb   = document.getElementById('identity-feedback');

    document.getElementById('identity-save').addEventListener('click', function () {
        var btn = this;
        fb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'identity'); fd.set('service', 'identity'); fd.set('method', 'save');
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        })
            .then(function (res) {
                if (res && res.result === 1) { TigerDOM.notify(fb, Tiger.t('saved'), { type: 'success' }); return; }
                if (res && res.form) {
                    Object.keys(res.form).forEach(function (field) {
                        var input = form.querySelector('[name="' + field + '"]');
                        if (input) { input.classList.add('is-invalid'); }
                    });
                }
                var msgs = (res && res.messages) || [];
                if (msgs.length) { msgs.forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class, replace: false }); }); }
                else { TigerDOM.notify(fb, Tiger.t('fixFields'), { type: 'error' }); }
            })
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });
});
