// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * agent.skills-index.js
 *
 * Agent Skills manager — ONE DataTables grid (like the Modules screen): the browse catalog
 * merged with what's installed, so a row's Status + action controls tell you whether it's
 * installed. Installed skills pin to the top. Data loads from Agent_Service_Skills::datatable
 * over /api; install / toggle / remove / view-source are /api calls. Tiger is NOT a trust
 * authority — the grid shows PROVENANCE and you review the SKILL.md before installing
 * (TIGERSKILLS.md §2, §5).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var $ = jQuery, esc = tigerDataTable.esc;
    var rescan = false;   // one-shot: next load re-scans the sources

    function api(method, body) {
        body = body || {}; body.module = 'agent'; body.service = 'skills'; body.method = method;
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams(body) }).then(function (r) { return r.json(); });
    }
    function toast(res) { ((res && res.messages) || []).forEach(function (m) { TigerDOM.toast(m.message, { type: m.class }); }); }

    // A skill entry rides on the button as individual data-* attrs (not a JSON blob). esc() is
    // attribute-safe (escapes quotes), so it can go straight into the double-quoted attributes.
    function entryAttrs(row) {
        return ' data-source="' + esc(row.source) + '" data-sourcelabel="' + esc(row.sourceLabel) + '"'
             + ' data-name="' + esc(row.name) + '" data-repo="' + esc(row.repo) + '"'
             + ' data-ref="' + esc(row.ref) + '" data-path="' + esc(row.path) + '" data-url="' + esc(row.url) + '"';
    }
    function readEntry(el) {
        return {
            source:      el.getAttribute('data-source')      || '',
            sourceLabel: el.getAttribute('data-sourcelabel') || '',
            name:        el.getAttribute('data-name')        || '',
            repo:        el.getAttribute('data-repo')        || '',
            ref:         el.getAttribute('data-ref')         || '',
            path:        el.getAttribute('data-path')        || '',
            url:         el.getAttribute('data-url')         || ''
        };
    }

    var table = tigerDataTable('#sk-table', {
        service: { module: 'agent', service: 'skills', action: 'datatable' },
        ordering: false,               // installed-first pinning is authoritative (server-side)
        language: { search: '', searchPlaceholder: 'Search skills…' },
        extraData: function () { var r = rescan ? 1 : 0; rescan = false; return { refresh: r }; },
        columns: [
            { data: 'name', render: function (t, type, row) {
                if (type !== 'display') { return t; }
                return '<span class="fw-semibold">' + esc(t) + '</span>';
            } },
            { data: 'description', className: 'text-body-secondary small', render: function (t, type) {
                if (type !== 'display') { return t; }
                t = String(t || ''); if (t.length > 160) { t = t.slice(0, 160) + '…'; }
                return esc(t);
            } },
            { data: 'sourceLabel', render: function (t, type) {
                if (type !== 'display') { return t; }
                return t ? '<span class="badge text-bg-light border">' + esc(t) + '</span>' : '<span class="text-body-secondary">—</span>';
            } },
            { data: 'installed', className: 'text-nowrap', render: function (t, type, row) {
                if (type !== 'display') { return t ? 1 : 0; }
                if (!row.installed) { return '<span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">Available</span>'; }
                return row.active
                    ? '<span class="badge rounded-pill text-bg-success"><i class="fa-solid fa-circle-check me-1"></i>On</span>'
                    : '<span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle">Installed</span>';
            } },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                var h = '';
                // View source (always) — key if installed, else the repo/ref/path for a live fetch.
                h += '<button type="button" class="btn btn-sm btn-outline-secondary sk-src" title="View source" aria-label="View source"'
                   + ' data-key="' + esc(row.installed ? row.key : '') + '"' + entryAttrs(row) + '>'
                   + '<i class="fa-solid fa-file-lines"></i></button> ';
                if (!row.installed) {
                    h += '<button type="button" class="btn btn-sm btn-primary sk-install"' + entryAttrs(row) + '>'
                       + '<i class="fa-solid fa-download me-1"></i>Install</button>';
                } else {
                    h += row.active
                        ? '<button type="button" class="btn btn-sm btn-outline-warning sk-toggle" data-key="' + esc(row.key) + '" data-active="0" title="Turn off" aria-label="Turn off"><i class="fa-solid fa-pause"></i></button> '
                        : '<button type="button" class="btn btn-sm btn-outline-success sk-toggle" data-key="' + esc(row.key) + '" data-active="1" title="Turn on" aria-label="Turn on"><i class="fa-solid fa-play"></i></button> ';
                    h += '<button type="button" class="btn btn-sm btn-outline-danger sk-remove" data-key="' + esc(row.key) + '" title="Remove" aria-label="Remove"><i class="fa-solid fa-trash"></i></button>';
                }
                return h;
            } }
        ]
    });

    // ---- actions (delegated) ----
    $('#sk-table tbody').on('click', '.sk-install', function () {
        var entry = readEntry(this);
        TigerButton.run(this, function () { return api('install', entry); }).then(function (res) {
            toast(res); table.ajax.reload(null, false);
        });
    });
    $('#sk-table tbody').on('click', '.sk-toggle', function () {
        var btn = this;
        TigerButton.run(btn, function () { return api('toggle', { key: btn.getAttribute('data-key'), active: btn.getAttribute('data-active') }); })
            .then(function (res) { toast(res); table.ajax.reload(null, false); });
    });
    $('#sk-table tbody').on('click', '.sk-remove', function () {
        var btn = this, key = btn.getAttribute('data-key');
        TigerModal.confirm({ title: Tiger.t('removeSkillTitle'), body: Tiger.t('removeSkillBody'), confirmLabel: Tiger.t('removeLabel'), variant: 'danger' }).then(function (ok) {
            if (!ok) { return; }
            api('remove', { key: key }).then(function (res) { toast(res); table.ajax.reload(null, false); });
        });
    });

    // ---- view source (+ install-from-modal for a not-yet-installed skill) ----
    var srcModalEl = document.getElementById('sk-src-modal');
    var srcModal   = (srcModalEl && window.bootstrap) ? new bootstrap.Modal(srcModalEl) : null;
    var srcEntry   = null;   // set when the open row isn't installed (enables the modal Install)
    $('#sk-table tbody').on('click', '.sk-src', function () {
        var b = this;
        var key = b.getAttribute('data-key');
        srcEntry = key ? null : readEntry(b);
        document.getElementById('sk-src-title').textContent = (b.getAttribute('data-name') || 'SKILL') + '.md';
        document.getElementById('sk-src-sub').textContent   = b.getAttribute('data-repo') || '';
        document.getElementById('sk-src-body').textContent  = 'Loading…';
        var inst = document.getElementById('sk-src-install'); inst.hidden = !!key;
        if (srcModal) { srcModal.show(); }
        api('source', { key: key || '', repo: b.getAttribute('data-repo') || '', ref: b.getAttribute('data-ref') || '', path: b.getAttribute('data-path') || '' })
            .then(function (res) { document.getElementById('sk-src-body').textContent = (res && res.data && res.data.source) || '(empty)'; });
    });
    document.getElementById('sk-src-install').addEventListener('click', function () {
        if (!srcEntry) { return; }
        TigerButton.run(this, function () { return api('install', srcEntry); }).then(function (res) {
            toast(res); if (res && res.result === 1 && srcModal) { srcModal.hide(); } table.ajax.reload(null, false);
        });
    });

    // ---- header controls ----
    document.getElementById('sk-rescan').addEventListener('click', function () {
        rescan = true; TigerButton.run(this, function () { return Promise.resolve(table.ajax.reload(null, false)); });
    });
    document.getElementById('sk-url-install').addEventListener('click', function () {
        var url = document.getElementById('sk-url').value.trim();
        if (!url) { return; }
        TigerButton.run(this, function () { return api('install', { url: url }); }).then(function (res) {
            toast(res);
            if (res && res.result === 1) { document.getElementById('sk-url').value = ''; table.ajax.reload(null, false); }
        });
    });
});
