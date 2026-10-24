// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * profile.index-security.js
 *
 * Profile → Security — change your own password. The two fields are matched client-side before
 * the round trip (no request just to be told they differ) and server field errors render inline;
 * the live strength meter + TigerValidateJS run the real server policy per field on blur. Saves
 * over /api (Profile_Service_Security).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
(function () {
    var form = document.getElementById('profile-security-form');
    var fb   = document.getElementById('profile-feedback');
    var np   = form.querySelector('[name="new_password"]');
    var cp   = form.querySelector('[name="confirm_password"]');
    var MISMATCH = Tiger.t('formPasswordMismatch');

    // Find the field's .invalid-feedback within its column (the strength meter injects nodes right
    // after the input, so nextElementSibling isn't reliable). Force-show with d-block since the meter's
    // markup can sit between the input and the feedback, breaking Bootstrap's sibling display rule.
    function feedbackFor(el) { var w = el.closest('.col-md-6') || el.parentNode; return w ? w.querySelector('.invalid-feedback') : null; }
    function setError(el, msg) { el.classList.add('is-invalid'); var f = feedbackFor(el); if (f) { f.textContent = msg || ''; f.classList.add('d-block'); } }
    function clearError(el) { el.classList.remove('is-invalid'); var f = feedbackFor(el); if (f) { f.textContent = ''; f.classList.remove('d-block'); } }

    // Correcting a field clears its error immediately.
    [np, cp].forEach(function (el) { el.addEventListener('input', function () { clearError(el); }); });

    document.getElementById('profile-security-save').addEventListener('click', function () {
        clearError(np); clearError(cp);

        // Cheap client-side match — no round trip just to be told the two don't match.
        if (np.value !== cp.value) { setError(cp, MISMATCH); cp.focus(); return; }

        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'profile'); fd.set('service', 'security'); fd.set('method', 'changePassword');
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) { TigerDOM.notify(fb, Tiger.t('securityPasswordChanged'), { type: 'success' }); form.reset(); return; }
            // Render each field's (now localized) validator message inline.
            if (res && res.form) {
                Object.keys(res.form).forEach(function (name) {
                    var el = form.querySelector('[name="' + name + '"]'); if (!el) { return; }
                    var msgs = res.form[name];
                    var text = (msgs && typeof msgs === 'object') ? (Object.values(msgs)[0] || '') : (msgs || '');
                    setError(el, text);
                });
            }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('netError'), { type: 'error' }); });
    });
})();
