// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * analytics.admin-index.js
 *
 * Analytics settings — the measurement id / property, the OAuth connect-disconnect, and the
 * save. Every action is an /api call to Analytics_Service_Analytics.
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
});
