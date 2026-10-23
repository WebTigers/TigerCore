// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
//
// Phone & SMS card (profile Security tab) — the two-step opt-in client: enter a number + tick consent
// → a code is texted → enter it to verify. A verified number shows with Change / Remove. All data moves
// over /api (profile/sms/*); the server is the one source of truth (every response returns the full
// state + a rotated CSRF token). Config + strings arrive on #profile-sms[data-config]. House primitives
// only: TigerButton (busy state), TigerDOM (feedback), TigerModal (confirm) — no inline script, no alert.
(function () {
    'use strict';
    var root = document.getElementById('profile-sms');
    if (!root) { return; }

    var CFG = {};
    try { CFG = JSON.parse(root.getAttribute('data-config') || '{}'); } catch (e) { CFG = {}; }
    var STR = CFG.str || {};

    var fb        = document.getElementById('profile-feedback');
    var form      = document.getElementById('sms-form');
    var csrfInput = form ? form.querySelector('[name="_csrf"]') : null;

    var stAdd      = document.getElementById('sms-state-add');
    var stVerify   = document.getElementById('sms-state-verify');
    var stVerified = document.getElementById('sms-state-verified');

    var phoneEl   = document.getElementById('sms-phone');
    var consentEl = document.getElementById('sms-consent');
    var sendBtn   = document.getElementById('sms-send');
    var addCancel = document.getElementById('sms-add-cancel');

    var codeEl       = document.getElementById('sms-code');
    var verifyBtn    = document.getElementById('sms-verify');
    var resendBtn    = document.getElementById('sms-resend');
    var verifyCancel = document.getElementById('sms-verify-cancel');
    var pendingHint  = document.getElementById('sms-pending-hint');

    var verifiedNum  = document.getElementById('sms-verified-number');
    var verifiedHint = document.getElementById('sms-verified-hint');
    var changeBtn    = document.getElementById('sms-change');
    var removeBtn    = document.getElementById('sms-remove');

    var pendingPhone = '';         // the E.164 we're verifying (server-echoed; never user-visible input)
    var hasVerified  = false;      // a verified number exists (governs Cancel's destination)
    var loaded       = false;
    var iti          = null;

    function notify(msg, type) { if (window.TigerDOM && fb) { TigerDOM.notify(fb, msg, { type: type || 'info' }); } }
    function show(el) { if (el) { el.classList.remove('d-none'); } }
    function hide(el) { if (el) { el.classList.add('d-none'); } }

    function initIti() {
        if (iti || !window.intlTelInput || !phoneEl) { return; }
        iti = window.intlTelInput(phoneEl, {
            initialCountry: (CFG.phoneDefault || 'us'),
            separateDialCode: true,
            formatOnDisplay: true,
            countrySearch: true
        });
    }

    function post(method, extra) {
        var fd = new URLSearchParams();
        fd.set('module', 'profile'); fd.set('service', 'sms'); fd.set('method', method);
        fd.set('_csrf', csrfInput ? csrfInput.value : '');
        if (extra) { Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); }); }
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    // Reflect the server state into the right panel.
    function apply(res) {
        if (res && res.data && res.data._csrf && csrfInput) { csrfInput.value = res.data._csrf; }
        var s = (res && res.data && res.data.sms) ? res.data.sms : null;
        if (!s) { return; }
        hasVerified = !!s.verified;

        hide(stAdd); hide(stVerify); hide(stVerified);

        if (s.pending && !s.verified) {
            // Mid-enrollment: show the code step. Prefer the server-echoed pending phone from this call.
            pendingPhone = (res.data.pending_phone || s.pending_phone || '');
            var mask = (res.data.pending_mask || s.pending_mask || '');
            if (pendingHint) { pendingHint.textContent = (STR.pending_hint || '').replace('%s', mask); }
            show(stVerify);
            if (codeEl) { codeEl.value = ''; codeEl.focus(); }
        } else if (s.verified) {
            if (verifiedNum)  { verifiedNum.textContent = s.phone || ''; }
            if (verifiedHint) { verifiedHint.textContent = s.opted_out ? (STR.opted_out_hint || '') : (STR.verified_hint || ''); }
            show(stVerified);
        } else {
            show(stAdd);
            if (addCancel) { hasVerified ? show(addCancel) : hide(addCancel); }
        }
    }

    function load() {
        if (loaded) { return; }
        loaded = true;
        post('status').then(apply).catch(function () { loaded = false; });
    }

    // ---- actions ---------------------------------------------------------
    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            initIti();
            if (consentEl && !consentEl.checked) { notify(STR.consent_req, 'error'); return; }
            if (!iti || !iti.isValidNumber()) { if (phoneEl) { phoneEl.classList.add('is-invalid'); } notify(STR.bad_phone, 'error'); return; }
            if (phoneEl) { phoneEl.classList.remove('is-invalid'); }
            var e164 = iti.getNumber();
            TigerButton.run(this, function () { return post('requestCode', { phone: e164, consent: '1' }); })
                .then(function (res) {
                    if (res && res.result === 1) { apply(res); notify(STR.code_sent, 'success'); return; }
                    (res && res.messages || []).forEach(function (m) { notify(m.message, m.class); });
                }).catch(function () { notify(STR.net_error, 'error'); });
        });
    }

    if (verifyBtn) {
        verifyBtn.addEventListener('click', function () {
            var code = (codeEl ? codeEl.value : '').replace(/\D/g, '');
            TigerButton.run(this, function () { return post('verifyCode', { phone: pendingPhone, code: code }); })
                .then(function (res) {
                    if (res && res.result === 1) { apply(res); notify(STR.verified, 'success'); return; }
                    if (codeEl) { codeEl.classList.add('is-invalid'); }
                    (res && res.messages || []).forEach(function (m) { notify(m.message, m.class); });
                }).catch(function () { notify(STR.net_error, 'error'); });
        });
    }

    if (resendBtn) {
        resendBtn.addEventListener('click', function () {
            TigerButton.run(this, function () { return post('requestCode', { phone: pendingPhone, consent: '1' }); })
                .then(function (res) {
                    if (res && res.result === 1) { apply(res); notify(STR.code_sent, 'success'); return; }
                    (res && res.messages || []).forEach(function (m) { notify(m.message, m.class); });
                }).catch(function () { notify(STR.net_error, 'error'); });
        });
    }

    function toAdd() { hide(stVerify); hide(stVerified); show(stAdd); if (addCancel) { hasVerified ? show(addCancel) : hide(addCancel); } initIti(); if (phoneEl) { phoneEl.focus(); } }
    if (changeBtn)    { changeBtn.addEventListener('click', toAdd); }
    if (addCancel)    { addCancel.addEventListener('click', function () { load2(); }); }
    if (verifyCancel) { verifyCancel.addEventListener('click', function () { load2(); }); }

    // Re-pull state (used by Cancel) — forces a fresh status read.
    function load2() { loaded = false; load(); }

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            var go = function () {
                TigerButton.run(removeBtn, function () { return post('remove'); })
                    .then(function (res) {
                        if (res && res.result === 1) { apply(res); notify(STR.removed, 'success'); return; }
                        (res && res.messages || []).forEach(function (m) { notify(m.message, m.class); });
                    }).catch(function () { notify(STR.net_error, 'error'); });
            };
            if (window.TigerModal && TigerModal.confirm) {
                TigerModal.confirm({ title: STR.remove, body: STR.confirm_remove, confirmLabel: STR.remove, variant: 'danger' })
                    .then(function (ok) { if (ok) { go(); } });
            } else { go(); }
        });
    }

    // Load lazily when the Security tab is shown (the pane is hidden at page load), or now if it's active.
    var tabBtn = document.querySelector('[data-bs-toggle="tab"][data-bs-target="#security"]');
    if (tabBtn) { tabBtn.addEventListener('shown.bs.tab', function () { initIti(); load(); }); }
    var pane = document.getElementById('security');
    if (pane && pane.classList.contains('active')) { initIti(); load(); }
})();
