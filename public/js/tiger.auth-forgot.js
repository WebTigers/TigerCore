// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.auth-forgot.js
 *
 * Request-a-reset form. AJAX POST to /auth/forgot; the server always answers the same way, so
 * this view always shows the same confirmation (no account enumeration).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form  = document.getElementById('forgot-form');
    var fb    = document.getElementById('forgot-feedback');
    var btn   = document.getElementById('forgot-submit');

    function rcReset() { if (window.grecaptcha) { try { grecaptcha.reset(); } catch (e) {} } }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        var email = document.getElementById('forgot-email').value;

        TigerButton.run(btn, function () {
            return fetch('/auth/forgot', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(new FormData(form))
            })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
        })
        .then(function (res) {
            // A reCAPTCHA block is a real error (not account enumeration) — surface it.
            if (res && res.result === 0 && res.recaptcha) {
                rcReset();
                TigerDOM.notify(fb, Tiger.t('recaptcha'), { type: 'error' });
                return;
            }
            // Hide the form and confirm via notify. Escape the (user-supplied) email —
            // notify takes HTML, so build the text safely rather than concatenating raw.
            var msg = document.createElement('span');
            msg.textContent = 'If an account exists for ' + email + ', a reset link is on its way. Check your inbox.';
            form.style.display = 'none';
            TigerDOM.notify(fb, msg.innerHTML, { type: 'success' });
        })
        .catch(function () {
            rcReset();
        });
    });
});
