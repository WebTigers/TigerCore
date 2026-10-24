// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * code.index-index.js
 *
 * Code list — a DataTables grid whose DATA loads from Code_Service_Code::datatable. Server
 * renders the shell; the browser fetches rows. Activate / deactivate / delete are /api calls.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var $ = jQuery, esc = tigerDataTable.esc;

    function api(method, id) {
        var body = new URLSearchParams({ module: 'code', service: 'code', method: method, code_id: id });
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body }).then(function (r) { return r.json(); });
    }

    var table = tigerDataTable('#code-table', {
        service: { module: 'code', service: 'code', action: 'datatable' },
        order: [[0, 'asc']],
        language: { search: '', searchPlaceholder: 'Search snippets…' },
        columns: [
            { data: 'name', render: function (t, type, row) {
                if (type !== 'display') { return t; }
                var h;
                if (row.source === 'module') {
                    h = '<span class="fw-semibold">' + esc(t) + '</span>'
                      + ' <span class="badge text-bg-info-subtle text-info-emphasis border border-info-subtle" title="From the ' + esc(row.module || '') + ' module">module</span>';
                } else {
                    h = '<a href="/code/index/edit/id/' + encodeURIComponent(row.code_id) + '" class="fw-semibold text-decoration-none">' + esc(t) + '</a>';
                }
                if (row.errored && row.last_error) { h += ' <i class="fa-solid fa-triangle-exclamation text-danger ms-1" title="' + esc(row.last_error) + '"></i>'; }
                return h;
            } },
            { data: 'language', render: function (t, type) { return type !== 'display' ? t : '<code class="text-uppercase">' + esc(t) + '</code>'; } },
            { data: 'location', render: function (t, type) { return type !== 'display' ? t : '<span class="badge text-bg-light">' + esc(t) + '</span>'; } },
            { data: 'priority', className: 'text-body-secondary', render: function (t, type) { return (t === null || t === undefined) ? '<span class="text-body-secondary">—</span>' : t; } },
            { data: 'active', render: function (t, type, row) {
                if (type !== 'display') { return t ? 1 : 0; }
                if (row.errored) { return '<span class="badge text-bg-danger">Error</span>'; }
                return t ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>';
            } },
            { data: 'updated', render: function (t, type) { return (type === 'display' && !t) ? '<span class="text-body-secondary">—</span>' : t; } },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                var h = '';
                if (row.can_view) {
                    h += '<button type="button" class="btn btn-sm btn-outline-secondary" data-code-view="' + esc(row.code_id) + '" title="View source" aria-label="View source"><i class="fa-solid fa-code"></i></button> ';
                }
                if (row.can_edit) {
                    h += '<a href="/code/index/edit/id/' + encodeURIComponent(row.code_id) + '" class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></a> ';
                }
                if (row.active) {
                    h += '<button type="button" class="btn btn-sm btn-outline-warning" data-code-toggle="deactivate" data-id="' + esc(row.code_id) + '" title="Deactivate" aria-label="Deactivate"><i class="fa-solid fa-pause"></i></button> ';
                } else {
                    h += '<button type="button" class="btn btn-sm btn-outline-success" data-code-toggle="activate" data-id="' + esc(row.code_id) + '" title="Activate" aria-label="Activate"><i class="fa-solid fa-play"></i></button> ';
                }
                if (row.can_delete) {
                    h += '<button type="button" class="btn btn-sm btn-outline-danger" data-code-delete="' + esc(row.code_id) + '" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>';
                }
                return h || '<span class="text-body-secondary">—</span>';
            } }
        ]
    });

    $('#code-table tbody').on('click', '[data-code-toggle]', function () {
        var btn = this;
        var m = btn.getAttribute('data-code-toggle'), id = btn.getAttribute('data-id');
        TigerButton.run(btn, function () { return api(m, id); }).then(function (res) {
            (res.messages || []).forEach(function (msg) { if (msg.class === 'error') { alert(msg.message); } });
            table.ajax.reload(null, false);
        });
    });
    $('#code-table tbody').on('click', '[data-code-delete]', function () {
        var btn = this;
        if (!confirm(Tiger.t('confirmDeleteSnippet'))) { return; }
        TigerButton.run(btn, function () { return api('delete', btn.getAttribute('data-code-delete')); }).then(function () { table.ajax.reload(null, false); });
    });

    // View source (module snippets) — read the PHP (read-only CodeMirror) before activating.
    var srcModalEl = document.getElementById('code-source-modal');
    var srcModal   = (srcModalEl && window.bootstrap) ? new bootstrap.Modal(srcModalEl) : null;
    var srcTa      = document.getElementById('code-source-body');
    var srcCm      = null;   // lazily created read-only editor
    var srcKey     = null, srcActive = false;

    function setSource(text, mode) {
        if (window.TigerCodeEditor && typeof CodeMirror !== 'undefined') {
            if (!srcCm) { srcCm = TigerCodeEditor.from(srcTa, { mode: mode || 'php', readOnly: true }); }
            srcCm.setValue(text);
            // CM mismeasures while the modal is hidden — refresh once it's laid out.
            requestAnimationFrame(function () { srcCm.refresh(); });
        } else {
            srcTa.value = text;   // no-CM fallback: plain read-only textarea
        }
    }

    $('#code-table tbody').on('click', '[data-code-view]', function () {
        var id = this.getAttribute('data-code-view');
        document.getElementById('code-source-sub').textContent  = '';
        document.getElementById('code-source-desc').textContent = '';
        setSource('// Loading…');
        if (srcModal) { srcModal.show(); }
        api('moduleSource', id).then(function (res) {
            if (!res || res.result !== 1 || !res.data) { setSource('// Could not load the source.'); return; }
            var d = res.data;
            srcKey = d.key; srcActive = !!d.active;
            document.getElementById('code-source-title').textContent = d.name || d.key;
            document.getElementById('code-source-sub').textContent   = d.module + ' · ' + (d.category || 'Snippet') + ' · ' + (d.scope || 'global');
            document.getElementById('code-source-desc').textContent  = d.description || '';
            setSource(d.source || '', d.language || 'php');
            var act = document.getElementById('code-source-activate');
            act.innerHTML = srcActive
                ? '<i class="fa-solid fa-pause me-2"></i>Deactivate'
                : '<i class="fa-solid fa-play me-2"></i>Activate';
            act.className = 'btn ' + (srcActive ? 'btn-warning' : 'btn-success');
        });
    });

    // Refresh CM after the modal's open transition so line numbers + gutters lay out correctly.
    if (srcModalEl) { srcModalEl.addEventListener('shown.bs.modal', function () { if (srcCm) { srcCm.refresh(); } }); }

    document.getElementById('code-source-activate').addEventListener('click', function () {
        var btn = this;
        if (!srcKey) { return; }
        var method = srcActive ? 'deactivate' : 'activate';
        TigerButton.run(btn, function () { return api(method, 'module:' + srcKey); }).then(function (res) {
            (res.messages || []).forEach(function (msg) { if (msg.class === 'error') { alert(msg.message); } });
            if (res && res.result === 1 && srcModal) { srcModal.hide(); }
            table.ajax.reload(null, false);
        });
    });
});
