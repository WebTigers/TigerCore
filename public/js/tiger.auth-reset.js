// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.auth-reset.js
 *
 * Set-a-new-password form (reached from the emailed reset link). The token (cid + code) rides in
 * hidden fields; AJAX POST to /auth/reset redeems it and sets the password, then sends the user
 * to sign in.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('reset-form');
    var fb   = document.getElementById('reset-feedback');
    var btn  = document.getElementById('reset-submit');

    function fail(msg) { TigerDOM.notify(fb, msg, { type: 'error' }); }
    function rcReset() { if (window.grecaptcha) { try { grecaptcha.reset(); } catch (e) {} } }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        var pw = document.getElementById('reset-password').value;
        var cf = document.getElementById('reset-confirm').value;
        if (pw !== cf) { fail(Tiger.t('passwordsMismatch')); return; }

        TigerButton.run(btn, function () {
            return fetch('/auth/reset', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams(new FormData(form))
            })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
        })
        .then(function (res) {
            if (res && res.result === 1) {
                // Populate the username so the browser offers to save the new password, then go straight
                // to the dashboard (we're already signed in — no bounce to the login page).
                if (res.username) { var uf = document.getElementById('reset-username'); if (uf) { uf.value = res.username; } }
                window.location = res.redirect || '/auth/login/reset/1';
                return;
            }
            if (res && res.recaptcha) { rcReset(); fail(Tiger.t('recaptcha')); return; }
            fail((res && res.message) ? res.message : Tiger.t('resetFailed'));
        })
        .catch(function () {
            rcReset();
            fail(Tiger.t('networkError'));
        });
    });
});
