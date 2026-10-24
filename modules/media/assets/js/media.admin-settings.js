// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * media.admin-settings.js
 *
 * Media settings — tabbed admin screen (ADMIN.md): a header + two tabs.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
// ---- Filenames: save obfuscation flags ----
document.getElementById('media-settings-save').addEventListener('click', function () {
    var form = document.getElementById('media-settings-form');
    var fb   = document.getElementById('media-settings-feedback');
    var fd   = new URLSearchParams(new FormData(form));
    fd.set('module', 'media'); fd.set('service', 'settings'); fd.set('method', 'save');
    TigerButton.run(this, function () {
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }).then(function (res) {
        if (res && res.result === 1) { TigerDOM.notify(fb, Tiger.t('settingsSaved'), { type: 'success' }); return; }
        if (res && res.form) { Object.keys(res.form).forEach(function (f) { var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); } }); }
        (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
    }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
});

// ---- Storage: adapter toggle + test connection + save ----
(function () {
    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }
    var form = document.getElementById('media-storage-form');
    if (!form) { return; }
    var fb = document.getElementById('media-storage-feedback');

    function current() { var r = form.querySelector('input[name="adapter"]:checked'); return r ? r.value : 'none'; }
    function serviceLabel(a) { return { s3: 'AWS S3', gcs: 'Google Cloud Storage', azure: 'Azure Blob Storage' }[a] || 'Cloud'; }
    function sync() {
        var a = current();
        form.querySelectorAll('.storage-fields').forEach(function (el) { el.hidden = (el.getAttribute('data-adapter') !== a); });
        var isCloud = a !== 'none';
        var row = document.getElementById('storage-default-row'), wrap = document.getElementById('storage-default-wrap');
        if (row) { row.hidden = !isCloud; } if (wrap) { wrap.hidden = !isCloud; }
        // Name the exact service (not just "Cloud") on the default-disk picker.
        var co = document.querySelector('#st-default-disk option[value="cloud"]');
        if (co && isCloud) { co.textContent = serviceLabel(a); }
    }
    form.querySelectorAll('input[name="adapter"]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();

    // Only send the *visible* adapter's fields (each adapter's inputs share names like `bucket`/`key`).
    function payload(method) {
        var fd = new URLSearchParams();
        fd.set('module', 'media'); fd.set('service', 'settings'); fd.set('method', method);
        fd.set('adapter', current());
        var group = form.querySelector('.storage-fields[data-adapter="' + current() + '"]');
        if (group) {
            group.querySelectorAll('input, select').forEach(function (el) {
                if (!el.name) { return; }
                if (el.type === 'checkbox') { if (el.checked) { fd.set(el.name, el.value || '1'); } }
                else if (el.value !== '') { fd.set(el.name, el.value); }
            });
        }
        var dd = document.getElementById('st-default-disk');
        if (dd) { fd.set('default_disk', dd.value); }
        return fd;
    }

    document.getElementById('media-storage-test').addEventListener('click', function () {
        var res = document.getElementById('media-storage-test-result');
        res.innerHTML = '';
        if (current() === 'none') { TigerDOM.notify(fb, Tiger.t('localNoTest'), { type: 'info' }); return; }
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: payload('testConnection') })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (r) {
            var ok = r && r.result === 1;
            var msg = (r && r.messages && r.messages[0] && r.messages[0].message) || (ok ? Tiger.t('connectionOk') : Tiger.t('connectionFailed'));
            res.innerHTML = '<div class="alert alert-' + (ok ? 'success' : 'danger') + ' mb-0 small"><i class="fa-solid fa-' + (ok ? 'circle-check' : 'circle-xmark') + ' me-2"></i>' + esc(msg) + '</div>';
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });

    document.getElementById('media-storage-save').addEventListener('click', function () {
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: payload('saveStorage') })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (r) {
            if (r && r.result === 1) {
                TigerDOM.notify(fb, Tiger.t('storageSaved'), { type: 'success' });
                // No page refresh — so reflect the just-saved disk in the Migration tab's target list now.
                var adapter = (r.data && r.data.adapter) || current();
                var mt = document.getElementById('media-migrate-to');
                if (mt && adapter !== 'none') {
                    var opt = mt.querySelector('option[value="cloud"]');
                    if (!opt) { opt = document.createElement('option'); opt.value = 'cloud'; mt.appendChild(opt); }
                    opt.textContent = serviceLabel(adapter);
                    mt.value = 'cloud';   // the just-configured disk is the likely migration target
                }
                return;
            }
            (r && r.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class || 'error' }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });
})();

// ---- Storage migration ----
(function () {
    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }
    var btn = document.getElementById('media-migrate-run');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        var to   = document.getElementById('media-migrate-to').value;
        var move = document.getElementById('media-migrate-move').checked;
        var res  = document.getElementById('media-migrate-result');
        var fb   = document.getElementById('media-migrate-feedback');
        var ask  = move
            ? 'MOVE all media to "' + to + '" and DELETE each original after it is copied? (Files already there are skipped.)'
            : 'COPY all media to "' + to + '"? Originals are kept.';
        if (!confirm(ask)) { return; }
        res.innerHTML = '';
        var fd = new URLSearchParams({ module: 'media', service: 'settings', method: 'migrateDisk', to: to, move: move ? '1' : '0' });
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (r) {
            if (r && r.result === 1 && r.data && r.data.summary) {
                var s = r.data.summary, mb = Math.round((s.bytes || 0) / 1048576 * 10) / 10;
                var html = 'Migrated <strong>' + s.rows + '</strong> file(s), ' + s.objects + ' object(s), ' + mb + ' MB'
                    + (s.skipped ? ', ' + s.skipped + ' already present' : '')
                    + (s.move ? ' — originals deleted' : ' — originals kept') + '.';
                if (s.errors && s.errors.length) {
                    res.innerHTML = '<div class="alert alert-warning mb-0 small">' + html
                        + '<div class="mt-2 text-danger"><strong>' + s.errors.length + ' error(s):</strong><br>'
                        + s.errors.map(esc).join('<br>') + '</div></div>';
                } else {
                    res.innerHTML = '<div class="alert alert-success mb-0 small">' + html + '</div>';
                }
                return;
            }
            (r && r.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class || 'error' }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });
})();
