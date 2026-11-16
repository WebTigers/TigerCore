// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * analytics.admin-index.js
 *
 * Analytics setup — step 1, the reporting-provider choice (cards → saveProvider); step 2, the chosen
 * provider's setup: GA's measurement id / property, OAuth connect-disconnect and save
 * (Analytics_Service_Analytics), or Matomo's save + Test connection (Matomo_Service_Matomo, then
 * Analytics_Service_Reports::test with provider=matomo). Every action is an /api call.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('analytics-form');
    var fb   = document.getElementById('analytics-feedback');

    // Show the bring-your-own-client fields only when that method is selected.
    var byo = document.getElementById('analytics-byo');
    function syncByo() {
        var on = document.getElementById('oauth-mode-byo');
        if (byo) { byo.classList.toggle('d-none', !(on && on.checked)); }
    }
    form.querySelectorAll('input[name="oauth_mode"]').forEach(function (r) { r.addEventListener('change', syncByo); });

    // Save the whole form (both tabs) over /api. Resolves true on success, false on a handled error
    // (field errors highlighted, messages shown). Shared by the Save button and Connect.
    function save(btn) {
        fb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'analytics'); fd.set('service', 'analytics'); fd.set('method', 'save');
        return TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) { return true; }
            if (res && res.form) {
                Object.keys(res.form).forEach(function (field) {
                    var input = form.querySelector('[name="' + field + '"]');
                    if (input) { input.classList.add('is-invalid'); }
                });
            }
            var msgs = (res && res.messages) || [];
            if (msgs.length) { msgs.forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class, replace: false }); }); }
            else { TigerDOM.notify(fb, Tiger.t('fixFields'), { type: 'error' }); }
            return false;
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); return false; });
    }

    document.getElementById('analytics-save').addEventListener('click', function () {
        save(this).then(function (ok) { if (ok) { TigerDOM.notify(fb, Tiger.t('saved'), { type: 'success' }); } });
    });

    // Connect: save the form first (so the property ID + method the user just typed are persisted),
    // then — only if the save succeeded — hand off to the OAuth flow. One click, no separate Save.
    var connectBtn = document.getElementById('analytics-connect');
    if (connectBtn) {
        connectBtn.addEventListener('click', function () {
            var prop = form.querySelector('[name="property_id"]');
            if (prop && prop.value.trim() === '') {
                TigerDOM.notify(fb, Tiger.t('connectNeedProperty'), { type: 'error' });
                prop.classList.add('is-invalid');
                prop.focus();
                return;
            }
            save(this).then(function (ok) { if (ok) { window.location.href = '/analytics/admin/connect'; } });
        });
    }

    // Troubleshooting: live "Test connection" — reports.test runs a real GA call and diagnoses it.
    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    var testBtn = document.getElementById('analytics-test');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            var out = document.getElementById('analytics-test-result');
            var fd  = new URLSearchParams({ module: 'analytics', service: 'reports', method: 'test' });
            TigerButton.run(this, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                    .then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function (res) {
                var t = res && res.data && res.data.test;
                if (!t) { out.innerHTML = '<div class="alert alert-danger mb-0"><i class="fa-solid fa-circle-exclamation me-2"></i>Couldn’t run the test — please try again.</div>'; return; }
                var kind = t.ok ? 'success' : (t.code === 'not_connected' ? 'secondary' : 'warning');
                var icon = t.ok ? 'circle-check' : (t.code === 'not_connected' ? 'circle-info' : 'triangle-exclamation');
                var html = '<div class="alert alert-' + kind + ' mb-0"><p class="mb-' + ((t.hint || t.detail) ? '2' : '0') + '"><i class="fa-solid fa-' + icon + ' me-2"></i>' + esc(t.message) + '</p>';
                if (t.hint)   { html += '<p class="small mb-' + (t.detail ? '2' : '0') + '">' + esc(t.hint) + '</p>'; }
                if (t.detail) { html += '<p class="small text-body-secondary mb-0"><code>' + esc(t.detail) + '</code></p>'; }
                out.innerHTML = html + '</div>';
            }).catch(function () { out.innerHTML = '<div class="alert alert-danger mb-0"><i class="fa-solid fa-circle-exclamation me-2"></i>Network error — please try again.</div>'; });
        });
    }

    // ---- Step 1: choose the reporting provider -----------------------------------------------------
    // Picking a card opens that provider's setup panel at once and saves the choice over /api
    // (analytics/analytics/saveProvider, riding the GA form's CSRF token — the two forms share a salt).
    // A refused save puts the previous choice back.
    function post(params) {
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: params })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }
    function notifyAll(box, res, fallback) {
        var msgs = (res && res.messages) || [];
        if (msgs.length) { msgs.forEach(function (m) { TigerDOM.notify(box, m.message, { type: m.class, replace: false }); }); }
        else { TigerDOM.notify(box, fallback, { type: 'error' }); }
    }
    function showPanel(name) {
        document.querySelectorAll('[data-provider-panel]').forEach(function (p) {
            var on = p.getAttribute('data-provider-panel') === name;
            p.classList.toggle('d-none', !on);
            if (on) {
                var h = document.getElementById('analytics-setup-heading');
                if (h) { h.textContent = p.getAttribute('data-heading') || ''; }
            }
        });
        var none = document.getElementById('analytics-panel-none');
        if (none) { none.classList.toggle('d-none', !!name); }
    }
    function markCurrent(name) {
        document.querySelectorAll('[data-provider-card]').forEach(function (card) {
            var on = card.getAttribute('data-provider-card') === name;
            card.classList.toggle('border-primary', on);
            var badge = card.querySelector('[data-provider-current]');
            if (badge) { badge.classList.toggle('d-none', !on); }
        });
        var detected = document.getElementById('analytics-provider-detected');
        if (detected) { detected.classList.add('d-none'); }
    }
    var providerForm = document.getElementById('analytics-provider-form');
    var checked = providerForm ? providerForm.querySelector('input[name="provider"]:checked') : null;
    var currentProvider = checked ? checked.value : '';
    if (providerForm) {
        providerForm.querySelectorAll('input[name="provider"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!radio.checked) { return; }
                var name = radio.value, prev = currentProvider;
                showPanel(name);
                markCurrent(name);
                var csrf = form.querySelector('[name="_csrf"]');
                var fd = new URLSearchParams({ module: 'analytics', service: 'analytics', method: 'saveProvider', provider: name });
                if (csrf) { fd.set('_csrf', csrf.value); }
                post(fd).then(function (res) {
                    if (res && res.result === 1) {
                        currentProvider = name;
                        TigerDOM.notify(fb, Tiger.t('providerSaved'), { type: 'success' });
                        return;
                    }
                    notifyAll(fb, res, Tiger.t('networkError'));
                    var back = providerForm.querySelector('input[name="provider"][value="' + prev + '"]');
                    if (back) { back.checked = true; } else { radio.checked = false; }
                    showPanel(prev); markCurrent(prev);
                }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
            });
        });
    }

    // ---- Step 2 (Matomo): save + test --------------------------------------------------------------
    // The matomo module's own form + service (matomo/matomo/save) — the one place Matomo is set up.
    var mForm = document.getElementById('matomo-form');
    var mFb   = document.getElementById('matomo-feedback');
    function saveMatomo(btn) {
        mFb.innerHTML = '';
        mForm.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        var fd = new URLSearchParams(new FormData(mForm));
        fd.set('module', 'matomo'); fd.set('service', 'matomo'); fd.set('method', 'save');
        return TigerButton.run(btn, function () { return post(fd); }).then(function (res) {
            if (res && res.result === 1) {
                // The token is write-only: clear the field and show the "token saved" badge instead.
                var tok = mForm.querySelector('[name="matomo_token_auth"]');
                if (tok) { tok.value = ''; }
                var badge = document.getElementById('matomo-token-saved');
                if (badge && res.data && res.data.has_token) { badge.classList.remove('d-none'); }
                return res;
            }
            if (res && res.form) {
                Object.keys(res.form).forEach(function (f) {
                    var input = mForm.querySelector('[name="' + f + '"]');
                    if (input) { input.classList.add('is-invalid'); }
                });
            }
            notifyAll(mFb, res, Tiger.t('fixFields'));
            return null;
        }).catch(function () { TigerDOM.notify(mFb, Tiger.t('networkError'), { type: 'error' }); return null; });
    }
    var mSave = document.getElementById('matomo-save');
    if (mForm && mSave) {
        mSave.addEventListener('click', function () {
            saveMatomo(this).then(function (res) {
                if (res) { TigerDOM.notify(mFb, (res.messages && res.messages[0] && res.messages[0].message) || Tiger.t('saved'), { type: 'success' }); }
            });
        });
    }
    // Test connection: save first (so what is typed is what is tested), then ask the adapter.
    var mTest = document.getElementById('matomo-test');
    if (mForm && mTest) {
        mTest.addEventListener('click', function () {
            var btn = this;
            saveMatomo(btn).then(function (saved) {
                if (!saved) { return; }
                var fd = new URLSearchParams({ module: 'analytics', service: 'reports', method: 'test', provider: 'matomo' });
                return TigerButton.run(btn, function () { return post(fd); }).then(function (res) {
                    var t = res && res.data && res.data.test;
                    if (!t) { TigerDOM.notify(mFb, Tiger.t('testFailed'), { type: 'error' }); return; }
                    var msg = t.message + (t.ok && t.site ? ' (' + t.site + ')' : '') + (!t.ok && t.detail ? ' — ' + t.detail : '');
                    TigerDOM.notify(mFb, esc(msg), { type: t.ok ? 'success' : 'alert' });
                });
            }).catch(function () { TigerDOM.notify(mFb, Tiger.t('networkError'), { type: 'error' }); });
        });
    }
});
