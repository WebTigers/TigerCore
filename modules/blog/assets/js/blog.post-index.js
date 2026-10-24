// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * blog.post-index.js
 *
 * Article list — a DataTables grid whose DATA loads over AJAX from the /api service
 * (Blog_Service_Post::datatable). The server renders only this shell; the browser fetches rows
 * as a Tiger Webservice. Status filter + global search are server-side; action controls are
 * built from per-row permission flags (can_edit/can_delete).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var $   = jQuery;
    var esc = tigerDataTable.esc;
    var BADGE = { published: 'success', draft: 'secondary', archived: 'warning' };
    function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }

    var fStatus = document.getElementById('blog-filter-status');

    var table = tigerDataTable('#blog-posts-table', {
        service: { module: 'blog', service: 'post', action: 'datatable' },
        order: [[5, 'desc']],
        language: { search: '', searchPlaceholder: 'Search articles…' },
        extraData: function () { return { status: fStatus.value }; },
        columns: [
            { data: 'title', render: function (t, type, row) {
                if (type !== 'display') { return t; }
                var h = '<a href="/blog/post/edit/id/' + encodeURIComponent(row.page_id) + '" class="fw-semibold text-decoration-none">' + esc(t) + '</a>';
                if (row.kicker) { h = '<span class="d-block small text-uppercase text-body-secondary" style="letter-spacing:.08em;">' + esc(row.kicker) + '</span>' + h; }
                return h;
            } },
            { data: 'handle', render: function (t, type) {
                return type !== 'display' ? t : (t ? '<code class="text-body-secondary">' + esc(t) + '</code>' : '<span class="text-body-secondary">—</span>');
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
            { data: 'reading', searchable: false, render: function (t, type) {
                return type !== 'display' ? t : (t ? esc(t) + ' min' : '—');
            } },
            { data: 'updated' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                var html = '';
                if (row.can_edit) {
                    html += '<a href="/blog/post/edit/id/' + encodeURIComponent(row.page_id) + '" class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></a> ';
                }
                if (row.can_delete) {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" data-blog-delete="' + esc(row.page_id) + '" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>';
                } else {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" disabled title="Not permitted" aria-label="Not permitted"><i class="fa-solid fa-trash"></i></button>';
                }
                return html || '<span class="text-body-secondary">—</span>';
            } }
        ]
    });

    $('#blog-filter-status').on('change', function () { table.ajax.reload(); });
    $('#blog-filter-clear').on('click', function () { fStatus.value = ''; table.ajax.reload(); });

    $('#blog-posts-table tbody').on('click', '[data-blog-delete]', function () {
        var btn = this;
        if (!confirm(Tiger.t('confirmDeleteArticle'))) { return; }
        var body = new URLSearchParams({ module: 'blog', service: 'post', method: 'delete', post_id: btn.getAttribute('data-blog-delete') });
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json(); });
        })
            .then(function () { table.ajax.reload(null, false); })
            .catch(function () { table.ajax.reload(null, false); });
    });
});
