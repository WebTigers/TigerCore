// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.auth-login.js
 *
 * Sign-in card. Renders in the 'auth' layout. Submits over AJAX to /auth/login (JSON), then
 * redirects client-side to the server-chosen target (the intended destination is remembered
 * server-side in the session, not a URL param). Auth is a reserved kernel service, so login is a
 * thin controller endpoint returning JSON.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form    = document.getElementById('login-form');
    var twofa   = document.getElementById('twofa-form');
    var alt     = document.getElementById('login-alt');
    var fb      = document.getElementById('login-feedback');
    var btn     = document.getElementById('login-submit');
    var vbtn    = document.getElementById('twofa-submit');
    var codeIn  = document.getElementById('twofa-code');

    function fail(msg) {
        TigerDOM.notify(fb, msg, { type: 'error' });
    }
    function rcReset() { if (window.grecaptcha) { try { grecaptcha.reset(); } catch (e) {} } }
    function post(body) {
        return fetch('/auth/login', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(body)
        }).then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    // Step 1: credentials. On success either sign in, or reveal the 2FA step.
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';

        TigerButton.run(btn, function () {
            return post(new FormData(form));
        }).then(function (res) {
            if (res && res.result === 1 && res.twofa) {
                form.style.display = 'none';
                if (alt) { alt.style.display = 'none'; }
                twofa.style.display = '';
                codeIn.focus();
                return;
            }
            if (res && res.result === 1) { window.location = res.redirect || '/'; return; }
            rcReset();   // the reCAPTCHA token (if any) is spent — let the user re-solve
            fail((res && res.recaptcha) ? Tiger.t('recaptcha') : Tiger.t('invalidLogin'));
            document.getElementById('login-password').value = '';
        }).catch(function () {
            rcReset();
            fail(Tiger.t('networkError'));
        });
    });

    // Step 2: the 2FA code (or a recovery code).
    twofa.addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';

        TigerButton.run(vbtn, function () {
            return post({ code: codeIn.value });
        }).then(function (res) {
            if (res && res.result === 1) { window.location = res.redirect || '/'; return; }
            fail(Tiger.t('codeIncorrect'));
            codeIn.value = '';
            codeIn.focus();
        }).catch(function () {
            fail(Tiger.t('networkError'));
        });
    });

    // Recovery-code mode: relax the numeric-only input constraints.
    document.getElementById('twofa-recovery-toggle').addEventListener('click', function (e) {
        e.preventDefault();
        codeIn.removeAttribute('pattern');
        codeIn.removeAttribute('inputmode');
        codeIn.setAttribute('maxlength', '20');
        codeIn.value = '';
        codeIn.placeholder = 'xxxxx-xxxxx';
        codeIn.style.letterSpacing = 'normal';
        document.querySelector('#twofa-form .form-label').textContent = 'Recovery code';
        codeIn.focus();
    });
});
