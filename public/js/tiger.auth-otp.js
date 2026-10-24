// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.auth-otp.js
 *
 * Passwordless sign-in card (two steps). Step 1 emails a one-time code; step 2 verifies it. Both
 * POST over AJAX to /auth/otp (JSON). The response never reveals whether the account exists, so
 * step 1 always advances to step 2. Email channel only in this version (SMS plugs in at the
 * service layer later).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var reqForm = document.getElementById('otp-request-form');
    var verForm = document.getElementById('otp-verify-form');
    var fb      = document.getElementById('otp-feedback');
    var reqBtn  = document.getElementById('otp-request-submit');
    var verBtn  = document.getElementById('otp-verify-submit');

    function fail(msg) { TigerDOM.notify(fb, msg, { type: 'error' }); }
    function rcReset() { if (window.grecaptcha) { try { grecaptcha.reset(); } catch (e) {} } }
    function post(body) {
        return fetch('/auth/otp', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(body)
        }).then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    // Step 1 -> request a code, then reveal step 2 (regardless of whether the email exists).
    reqForm.addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        var email = document.getElementById('otp-email').value;
        // FormData carries the reCAPTCHA token (g-recaptcha-response) when the widget is on.
        TigerButton.run(reqBtn, function () {
            return post(new FormData(reqForm));
        }).then(function (res) {
            if (res && res.result === 0 && res.recaptcha) {
                rcReset();
                fail(Tiger.t('recaptcha'));
                return;
            }
            document.getElementById('otp-email-hidden').value = email;
            document.getElementById('otp-sent-note').textContent =
                'If an account exists for ' + email + ', we sent a 6-digit code. Enter it below.';
            reqForm.style.display = 'none';
            verForm.style.display = '';
            document.getElementById('otp-code').focus();
        }).catch(function () {
            fail(Tiger.t('networkError'));
        });
    });

    // Step 2 -> verify + sign in.
    verForm.addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        TigerButton.run(verBtn, function () {
            return post({ email: document.getElementById('otp-email-hidden').value, code: document.getElementById('otp-code').value });
        })
        .then(function (res) {
            if (res && res.result === 1) { window.location = res.redirect || '/'; return; }
            fail(Tiger.t('codeInvalid'));
            document.getElementById('otp-code').value = '';
        }).catch(function () {
            fail(Tiger.t('networkError'));
        });
    });

    document.getElementById('otp-restart').addEventListener('click', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        verForm.style.display = 'none';
        reqForm.style.display = '';
        reqBtn.disabled = false;
        reqBtn.textContent = 'Email me a code';
        document.getElementById('otp-email').focus();
    });
});
