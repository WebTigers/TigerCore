// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * register.admin-registration.js
 *
 * Settings → Registration — register this install with the TID authority, verify the domain,
 * resend the verification email. Every action is an /api call to Register_Service_Registration;
 * a success reloads so the status card redraws from the server.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
(function () {
    var fb = document.getElementById('reg-feedback');
    function call(method, btn, extra) {
        var fd = new URLSearchParams(extra || {});
        fd.set('module', 'register'); fd.set('service', 'registration'); fd.set('method', method);
        var csrf = document.querySelector('#reg-form [name="_csrf"]');
        if (csrf) { fd.set('_csrf', csrf.value); }
        return TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) { location.reload(); return; }
            if (res && res.form) { Object.keys(res.form).forEach(function (f) {
                var el = document.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); }
            }); }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    }
    function on(id, fn) { var el = document.getElementById(id); if (el) { fn(el); } }
    on('reg-register', function (b) { b.addEventListener('click', function () {
        var e = document.getElementById('reg-email'); call('register', b, { email: e ? e.value : '' });
    }); });
    on('reg-verify-domain', function (b) { b.addEventListener('click', function () { call('verifyDomain', b); }); });
    on('reg-resend', function (b) { b.addEventListener('click', function () { call('resendEmail', b); }); });
})();
