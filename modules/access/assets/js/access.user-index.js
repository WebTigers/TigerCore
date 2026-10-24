// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * access.user-index.js
 *
 * Users list — a DataTables grid sourced from /api (Access_Service_User::datatable), per the
 * client/server paradigm. Server-side search/sort/paginate; a Status filter toolbar via the
 * tigerDataTable extraData hook. Action controls render off per-row ACL flags (can_edit /
 * can_delete); you can't delete your own account.
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

    var fStatus = document.getElementById('access-user-status');

    var table = tigerDataTable('#access-user-table', {
        service: { module: 'access', service: 'user', action: 'datatable' },
        order: [[5, 'desc']],
        language: { search: '', searchPlaceholder: Tiger.t('searchUsers') },
        extraData: function () { return { status: fStatus.value }; },
        columns: [
            { data: 'email', render: function (t, type, row) {
                return type !== 'display' ? t :
                    '<a href="/access/user/edit/id/' + encodeURIComponent(row.user_id) + '" class="fw-semibold text-decoration-none">' + esc(t) + '</a>';
            } },
            { data: 'username', render: function (t, type) {
                if (type !== 'display') { return t; }
                return t ? '<code class="text-body-secondary">' + esc(t) + '</code>' : '<span class="text-body-secondary">—</span>';
            } },
            { data: 'roles', orderable: false, render: function (t, type) {
                if (type !== 'display') { return t; }
                if (!t) { return '<span class="text-body-secondary">—</span>'; }
                return String(t).split(',').map(function (r) {
                    return '<span class="badge text-bg-light border me-1">' + esc(r.trim()) + '</span>';
                }).join('');
            } },
            { data: 'org_count', render: function (t, type) {
                return type !== 'display' ? t : '<span class="badge rounded-pill text-bg-secondary">' + (parseInt(t, 10) || 0) + '</span>';
            } },
            { data: 'status', render: function (t, type) {
                return type !== 'display' ? t : '<span class="badge text-bg-' + (BADGE[t] || 'secondary') + '">' + esc(cap(t)) + '</span>';
            } },
            { data: 'created' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                var html = '';
                if (row.can_edit) {
                    html += '<a href="/access/user/edit/id/' + encodeURIComponent(row.user_id) + '" class="btn btn-sm btn-outline-secondary" title="' + esc(Tiger.t('edit')) + '" aria-label="' + esc(Tiger.t('edit')) + '"><i class="fa-solid fa-pen"></i></a> ';
                }
                if (row.can_delete) {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" data-user-delete="' + esc(row.user_id) + '" title="' + esc(Tiger.t('del')) + '" aria-label="' + esc(Tiger.t('del')) + '"><i class="fa-solid fa-trash"></i></button>';
                } else {
                    var noDel = esc(row.is_self ? Tiger.t('deleteSelf') : Tiger.t('notPermitted'));
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" disabled title="' + noDel + '" aria-label="' + noDel + '"><i class="fa-solid fa-trash"></i></button>';
                }
                return html || '<span class="text-body-secondary">—</span>';
            } }
        ]
    });

    $('#access-user-status').on('change', function () { table.ajax.reload(); });
    $('#access-user-clear').on('click', function () { fStatus.value = ''; table.ajax.reload(); });

    $('#access-user-table tbody').on('click', '[data-user-delete]', function () {
        if (!confirm(Tiger.t('confirmDelUser'))) { return; }
        var btn  = this;
        var body = new URLSearchParams({ module: 'access', service: 'user', method: 'delete', user_id: btn.getAttribute('data-user-delete') });
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json(); });
        })
            .then(function () { table.ajax.reload(null, false); })
            .catch(function () { table.ajax.reload(null, false); });
    });
});
