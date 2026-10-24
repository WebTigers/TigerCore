// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.auth-lock.js
 *
 * Lock card. The session stays signed in; re-verify to unlock — by password OR, for passwordless
 * (OTP) users, by a one-time code emailed to the (known) address.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. The target address rides on #lock-code-mode[data-email]; strings come through the
 * $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var fb      = document.getElementById('lock-feedback');
    var pwMode  = document.getElementById('lock-pw-mode');
    var codeMode= document.getElementById('lock-code-mode');
    var email   = codeMode.getAttribute('data-email') || '';
    var sendForm= document.getElementById('lock-send-form');
    var codeForm= document.getElementById('lock-code-form');

    function fail(msg) { TigerDOM.notify(fb, msg, { type: 'error' }); }
    function post(body) {
        return fetch('/auth/lock', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams(body) })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }
    function unlocked(res) { window.location = (res && res.redirect) || '/admin'; }

    // --- password unlock ---
    document.getElementById('lock-pw-form').addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        var btn = document.getElementById('lock-pw-submit');
        TigerButton.run(btn, function () {
            return post({ password: document.getElementById('lock-password').value });
        }).then(function (res) {
            if (res && res.result === 1) { unlocked(res); return; }
            fail(Tiger.t('incorrectPassword')); document.getElementById('lock-password').value = '';
        }).catch(function () { fail(Tiger.t('networkError')); });
    });

    // --- mode toggles ---
    document.getElementById('lock-use-code').addEventListener('click', function (e) {
        e.preventDefault(); fb.innerHTML = ''; pwMode.style.display = 'none'; codeMode.style.display = '';
    });
    document.getElementById('lock-use-pw').addEventListener('click', function (e) {
        e.preventDefault(); fb.innerHTML = ''; codeMode.style.display = 'none'; sendForm.style.display = '';
        codeForm.style.display = 'none'; pwMode.style.display = ''; document.getElementById('lock-password').focus();
    });

    // --- code unlock: send ---
    sendForm.addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        var btn = document.getElementById('lock-send-submit');
        TigerButton.run(btn, function () {
            return post({ send_code: 1 });
        }).then(function () {
            document.getElementById('lock-code-note').textContent = Tiger.t('codeSent', email);
            sendForm.style.display = 'none'; codeForm.style.display = ''; document.getElementById('lock-code').focus();
        }).catch(function () { fail(Tiger.t('networkError')); });
    });

    // --- code unlock: verify ---
    codeForm.addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        var btn = document.getElementById('lock-code-submit');
        TigerButton.run(btn, function () {
            return post({ code: document.getElementById('lock-code').value });
        }).then(function (res) {
            if (res && res.result === 1) { unlocked(res); return; }
            fail(Tiger.t('codeInvalid')); document.getElementById('lock-code').value = '';
        }).catch(function () { fail(Tiger.t('networkError')); });
    });
});
