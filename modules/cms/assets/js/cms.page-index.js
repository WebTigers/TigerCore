// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * cms.page-index.js
 *
 * CMS content list — a DataTables grid whose DATA loads over AJAX from the /api service
 * (Cms_Service_Page::datatable), per the client/server paradigm: the server renders only this
 * shell; the browser fetches rows as a Tiger Webservice.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var $   = jQuery;
    var esc = tigerDataTable.esc;
    var TYPE_ICON = { page: 'fa-file-lines', layout: 'fa-table-columns', partial: 'fa-puzzle-piece', block: 'fa-cube' };
    var BADGE     = { published: 'success', draft: 'secondary', archived: 'warning' };
    function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }

    var fStatus = document.getElementById('cms-filter-status');
    var fType   = document.getElementById('cms-filter-type');

    var table = tigerDataTable('#cms-pages-table', {
        service: { module: 'cms', service: 'page', action: 'datatable' },
        order: [[5, 'desc']],
        language: { search: '', searchPlaceholder: 'Search content…' },
        // Toolbar filters ride along on every request (server-side scoping).
        extraData: function () {
            return { status: fStatus.value, type: fType.value };
        },
        columns: [
            { data: 'title', render: function (t, type, row) {
                return type !== 'display' ? t :
                    '<a href="/cms/page/edit/id/' + encodeURIComponent(row.page_id) + '" class="fw-semibold text-decoration-none">' + esc(t) + '</a>';
            } },
            { data: 'type', render: function (t, type) {
                return type !== 'display' ? t :
                    '<i class="fa-solid ' + (TYPE_ICON[t] || 'fa-file') + ' fa-fw text-body-secondary me-1"></i>' + esc(cap(t));
            } },
            { data: 'handle', render: function (t, type) {
                return type !== 'display' ? t : '<code class="text-body-secondary">' + esc(t) + '</code>';
            } },
            { data: 'locale', render: function (t, type) {
                return type !== 'display' ? t : '<span class="badge text-bg-light text-uppercase">' + esc(t) + '</span>';
            } },
            { data: 'status', render: function (t, type, row) {
                if (type !== 'display') { return t; }
                var h = '<span class="badge text-bg-' + (BADGE[t] || 'secondary') + '">' + esc(cap(t)) + '</span>';
                if (row.scheduled) { h += ' <span class="badge text-bg-info ms-1">Scheduled</span>'; }
                return h;
            } },
            { data: 'updated' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                var html = '';
                if (row.can_edit) {
                    html += '<a href="/cms/page/edit/id/' + encodeURIComponent(row.page_id) + '" class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></a> ';
                    // Pages, partials, AND blocks open in the visual builder (a fragment edits inside its
                    // layout's chrome — see Cms_PageController::designAction); layouts stay text-edited.
                    if (row.type === 'page' || row.type === 'partial' || row.type === 'block') {
                        html += '<a href="/cms/page/design/id/' + encodeURIComponent(row.page_id) + '" class="btn btn-sm btn-outline-primary" title="Design visually" aria-label="Design visually"><i class="fa-solid fa-panorama"></i></a> ';
                    }
                }
                if (row.can_delete) {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" data-cms-delete="' + esc(row.page_id) + '" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>';
                } else {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" disabled title="Not permitted" aria-label="Not permitted"><i class="fa-solid fa-trash"></i></button>';
                }
                return html || '<span class="text-body-secondary">—</span>';
            } }
        ]
    });

    // Re-query when a toolbar filter changes; Clear resets both.
    $('#cms-filter-status, #cms-filter-type').on('change', function () { table.ajax.reload(); });
    $('#cms-filter-clear').on('click', function () {
        fStatus.value = '';
        fType.value = '';
        table.ajax.reload();
    });

    // Theme Templates: a SERVER-SIDE DataTable (a theme may ship hundreds — Porto ~830). Built lazily on
    // first tab show — DataTables mis-measures column widths if initialized while its tab is hidden.
    (function () {
        var tabBtn = document.getElementById('tab-templates-btn');
        if (!tabBtn || typeof tigerDataTable !== 'function') { return; }
        var KIND = { page: { l: 'Page', i: 'fa-file-lines' }, layout: { l: 'Layout', i: 'fa-table-columns' }, partial: { l: 'Partial', i: 'fa-puzzle-piece' } };
        var built = false;
        tabBtn.addEventListener('shown.bs.tab', function () {
            if (built) { return; }
            built = true;
            tigerDataTable('#cms-templates-table', {
                service: { module: 'cms', service: 'page', action: 'themeTemplates' },
                order: [[1, 'asc']],
                language: { search: '', searchPlaceholder: 'Search templates…' },
                columns: [
                    { data: 'kind', render: function (t, type) {
                        if (type !== 'display') { return t; }
                        var k = KIND[t] || { l: t, i: 'fa-file' };
                        return '<i class="fa-solid ' + k.i + ' fa-fw text-body-secondary me-1"></i>' + esc(k.l);
                    } },
                    { data: 'theme', render: function (t, type) {
                        return type !== 'display' ? t : '<span class="badge text-bg-secondary-subtle text-secondary-emphasis">' + esc(t) + '</span>';
                    } },
                    { data: 'title', render: function (t, type, row) {
                        if (type !== 'display') { return t; }
                        return row.customized
                            ? '<a href="/cms/page/edit/id/' + encodeURIComponent(row.page_id) + '" class="fw-semibold text-decoration-none">' + esc(t) + '</a>'
                            : '<span class="fw-semibold">' + esc(t) + '</span>';
                    } },
                    { data: 'handle', render: function (t, type) {
                        return type !== 'display' ? t : '<code class="text-body-secondary">' + esc(t) + '</code>';
                    } },
                    { data: 'customized', render: function (t, type) {
                        if (type !== 'display') { return t ? 1 : 0; }
                        return t
                            ? '<span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">Customized</span>'
                            : '<span class="text-body-tertiary small">Available</span>';
                    } },
                    { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                        if (type !== 'display') { return ''; }
                        var h = '';
                        if (row.kind === 'page') {
                            h += '<a href="/' + esc(row.slug) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Preview" aria-label="Preview"><i class="fa-solid fa-eye"></i></a> ';
                        }
                        if (row.customized) {
                            h += '<a href="/cms/page/edit/id/' + encodeURIComponent(row.page_id) + '" class="btn btn-sm btn-outline-primary" title="Edit copy"><i class="fa-solid fa-pen me-1"></i>Edit copy</a>';
                        } else if (row.can_edit) {
                            h += '<button type="button" class="btn btn-sm btn-primary" data-theme-fork="' + esc(row.slug) + '" data-fork-kind="' + esc(row.kind) + '" data-fork-theme="' + esc(row.theme_key) + '" title="Customize"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>Customize</button>';
                        }
                        return h || '<span class="text-body-secondary">—</span>';
                    } }
                ]
            });
        });
    })();

    // Customize a theme template: fork it (page/layout/partial) into an editable row (server), then open it.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-theme-fork]');
        if (!btn) { return; }
        var body = new URLSearchParams({ module: 'cms', service: 'page', method: 'forkTheme',
            slug: btn.getAttribute('data-theme-fork'), kind: btn.getAttribute('data-fork-kind') || 'page',
            theme: btn.getAttribute('data-fork-theme') || '' });
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body }).then(function (r) { return r.json(); });
        }).then(function (res) {
            var url = (res && res.data && res.data.edit_url) || (res && res.redirect);
            if (res && res.result === 1 && url) { window.location = url; return; }
            (res && res.messages || []).forEach(function (m) { alert(m.message); });
        }).catch(function () { alert(Tiger.t('customizeFailed')); });
    });

    // Soft-delete via /api, then reload just the table data (no page reload).
    $('#cms-pages-table tbody').on('click', '[data-cms-delete]', function () {
        if (!confirm(Tiger.t('confirmDeletePage'))) { return; }
        var btn  = this;
        var body = new URLSearchParams({ module: 'cms', service: 'page', method: 'delete', page_id: this.getAttribute('data-cms-delete') });
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json(); });
        })
            .then(function () { table.ajax.reload(null, false); })
            .catch(function () { table.ajax.reload(null, false); });
    });
});
