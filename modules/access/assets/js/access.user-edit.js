// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * access.user-edit.js
 *
 * User editor — thin identity (email / username / status). ViewHelper-only form elements (the
 * view owns markup). Saving is an /api call to Access_Service_User; the JSON response drives the
 * redirect / inline errors.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('access-user-form');
    var fb   = document.getElementById('access-feedback');

    function clearErrors() {
        fb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
    }
    document.getElementById('access-save').addEventListener('click', function () {
        var btn = this;
        clearErrors();
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'access'); fd.set('service', 'user'); fd.set('method', 'save');
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json(); });
        })
            .then(function (res) {
                if (res.result === 1) { window.location = res.redirect || '/access/user'; return; }
                if (res.form) {
                    Object.keys(res.form).forEach(function (field) {
                        var input = form.querySelector('[name="' + field + '"]');
                        if (input) { input.classList.add('is-invalid'); }
                    });
                }
                var msgs = (res.messages) || [];
                if (msgs.length) { fb.innerHTML = ''; msgs.forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class, replace: false }); }); }
                else { TigerDOM.notify(fb, Tiger.t('fixFields'), { type: 'error' }); }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });
});
