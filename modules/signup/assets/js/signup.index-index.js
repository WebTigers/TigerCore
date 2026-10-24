// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * signup.index-index.js
 *
 * Signup — the platform's reference form view. Every field is convenience-validated on blur
 * (data-tiger-validate + data-module/data-form → Tiger_Service_Validate), the password has a
 * live strength meter, the Save button shows its busy state (TigerButton), and feedback comes
 * through TigerDOM.notify. This is the copy-me template for building any Tiger form.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('signup-form');
    var fb   = document.getElementById('signup-feedback');
    var rcSite = form.getAttribute('data-recaptcha-site') || '';   // v3 site key (empty = off/v2 → skip)
    function rcToken(action) {
        if (!rcSite || !window.grecaptcha) { return Promise.resolve(''); }
        return new Promise(function (resolve) {
            try { grecaptcha.ready(function () { grecaptcha.execute(rcSite, { action: action }).then(resolve, function () { resolve(''); }); }); }
            catch (e) { resolve(''); }
        });
    }

    document.getElementById('signup-submit').addEventListener('click', function () {
        var btn = this;
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        rcToken('signup').then(function (token) {
        var hid = form.querySelector('input.g-recaptcha-response');
        if (hid && token) { hid.value = token; }
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'signup'); fd.set('service', 'signup'); fd.set('method', 'create');

        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) {
                form.style.display = 'none';
                TigerDOM.notify(document.getElementById('signup-done'),
                    Tiger.t('verifySent'),
                    { type: 'success', dismissAfter: 0 });
                return;
            }
            if (res && res.form) {
                Object.keys(res.form).forEach(function (field) {
                    var input = form.querySelector('[name="' + field + '"]');
                    if (!input) { return; }
                    input.classList.add('is-invalid');
                    var cell = input.closest('[data-field]');
                    if (cell) {
                        var fbk = cell.querySelector('.invalid-feedback') || cell.appendChild(Object.assign(document.createElement('div'), { className: 'invalid-feedback' }));
                        var msgs = res.form[field];
                        fbk.textContent = (msgs && typeof msgs === 'object') ? (Object.values(msgs)[0] || Tiger.t('checkField')) : String(msgs || Tiger.t('checkField'));
                        fbk.style.display = 'block';
                    }
                });
                TigerDOM.notify(fb, Tiger.t('fixFields'), { type: 'error' });
                return;
            }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class, replace: false }); });
            if (!res || !res.messages || !res.messages.length) { TigerDOM.notify(fb, Tiger.t('wentWrong'), { type: 'error' }); }
        }).catch(function () {
            TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' });
        });
        });
    });
});
