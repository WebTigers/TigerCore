// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * access.org-index.js
 *
 * Organizations list — a DataTables grid sourced from /api (Access_Service_Org:: datatable).
 * Server-side search/sort/paginate + a Status filter toolbar. Action controls render off per-row
 * ACL flags; you can't delete the org you're acting in.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var $   = jQuery;
    var esc = tigerDataTable.esc;
    var BADGE = { active: 'success', suspended: 'warning' };
    function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }

    var fStatus = document.getElementById('access-org-status');

    var table = tigerDataTable('#access-org-table', {
        service: { module: 'access', service: 'org', action: 'datatable' },
        order: [[0, 'asc']],
        language: { search: '', searchPlaceholder: Tiger.t('searchOrgs') },
        extraData: function () { return { status: fStatus.value }; },
        columns: [
            { data: 'name', render: function (t, type, row) {
                return type !== 'display' ? t :
                    '<a href="/access/org/edit/id/' + encodeURIComponent(row.org_id) + '" class="fw-semibold text-decoration-none">' + esc(t) + '</a>';
            } },
            { data: 'slug', render: function (t, type) {
                return type !== 'display' ? t : '<code class="text-body-secondary">' + esc(t) + '</code>';
            } },
            { data: 'parent', render: function (t, type) {
                if (type !== 'display') { return t; }
                return t ? esc(t) : '<span class="text-body-secondary">' + esc(Tiger.t('parentRoot')) + '</span>';
            } },
            { data: 'member_count', render: function (t, type) {
                return type !== 'display' ? t : '<span class="badge rounded-pill text-bg-secondary">' + (parseInt(t, 10) || 0) + '</span>';
            } },
            { data: 'status', render: function (t, type) {
                return type !== 'display' ? t : '<span class="badge text-bg-' + (BADGE[t] || 'secondary') + '">' + esc(cap(t)) + '</span>';
            } },
            { data: 'created' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                var html = '';
                if (row.can_edit) {
                    html += '<a href="/access/org/edit/id/' + encodeURIComponent(row.org_id) + '" class="btn btn-sm btn-outline-secondary" title="' + esc(Tiger.t('edit')) + '" aria-label="' + esc(Tiger.t('edit')) + '"><i class="fa-solid fa-pen"></i></a> ';
                }
                if (row.can_delete) {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" data-org-delete="' + esc(row.org_id) + '" title="' + esc(Tiger.t('del')) + '" aria-label="' + esc(Tiger.t('del')) + '"><i class="fa-solid fa-trash"></i></button>';
                } else {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" disabled title="' + esc(Tiger.t('orgNoDelete')) + '" aria-label="' + esc(Tiger.t('orgNoDelete')) + '"><i class="fa-solid fa-trash"></i></button>';
                }
                return html || '<span class="text-body-secondary">—</span>';
            } }
        ]
    });

    $('#access-org-status').on('change', function () { table.ajax.reload(); });
    $('#access-org-clear').on('click', function () { fStatus.value = ''; table.ajax.reload(); });

    $('#access-org-table tbody').on('click', '[data-org-delete]', function () {
        if (!confirm(Tiger.t('confirmDelOrg'))) { return; }
        var btn  = this;
        var body = new URLSearchParams({ module: 'access', service: 'org', method: 'delete', org_id: btn.getAttribute('data-org-delete') });
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json(); });
        })
            .then(function () { table.ajax.reload(null, false); })
            .catch(function () { table.ajax.reload(null, false); });
    });
});
