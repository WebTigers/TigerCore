/* SPDX-License-Identifier: BSD-3-Clause
 * Sites (multi-site host -> org) admin — DataTables grid + add/delete over /api
 * (System_Service_Sites). House UI primitives only; no inline handlers. */
(function () {
    'use strict';
    if (typeof tigerDataTable !== 'function') { return; }
    var $   = jQuery;
    var esc = tigerDataTable.esc;
    var fb  = document.getElementById('system-sites-feedback');
    var BADGE = { active: 'success', suspended: 'warning' };

    var table = tigerDataTable('#system-sites-table', {
        service: { module: 'system', service: 'sites', action: 'datatable' },
        order: [[0, 'asc']],
        language: { search: '', searchPlaceholder: Tiger.t('searchSites') },
        columns: [
            { data: 'domain', render: function (t, type) {
                return type !== 'display' ? t : '<code>' + esc(t) + '</code>';
            } },
            { data: 'org_name', render: function (t, type) {
                if (type !== 'display') { return t; }
                return t ? esc(t) : '<span class="text-danger">—</span>';
            } },
            { data: 'status', render: function (t, type) {
                if (type !== 'display') { return t; }
                return '<span class="badge text-bg-' + (BADGE[t] || 'secondary') + '">' + esc(t) + '</span>';
            } },
            { data: 'created' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                if (!row.can_delete) { return '<span class="text-body-secondary">—</span>'; }
                return '<button type="button" class="btn btn-sm btn-outline-danger" data-del="' + esc(row.site_domain_id) +
                       '" data-domain="' + esc(row.domain) + '" title="' + esc(Tiger.t('del')) + '" aria-label="' + esc(Tiger.t('del')) + '"><i class="fa-solid fa-trash"></i></button>';
            } }
        ]
    });

    // Add a host -> org mapping.
    var saveBtn = document.getElementById('system-sites-save');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            var form = document.getElementById('system-sites-form');
            var fd = new URLSearchParams(new FormData(form));
            fd.set('module', 'system'); fd.set('service', 'sites'); fd.set('method', 'save');
            // A fresh mapping each time — never carry a prior id.
            fd.set('site_domain_id', '');
            $(form).find('.is-invalid').removeClass('is-invalid');
            TigerButton.run(this, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                    .then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function (res) {
                if (res && res.result === 1) {
                    TigerDOM.notify(fb, Tiger.t('added'), { type: 'success' });
                    var d = form.querySelector('#system-site-domain'); if (d) { d.value = ''; }
                    table.ajax.reload(null, false);
                    return;
                }
                if (res && res.form) {
                    Object.keys(res.form).forEach(function (f) {
                        var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); }
                    });
                }
                (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
            }).catch(function () { TigerDOM.notify(fb, Tiger.t('netErr'), { type: 'error' }); });
        });
    }

    // Delete a mapping.
    $('#system-sites-table tbody').on('click', '[data-del]', function () {
        var btn = this;
        TigerModal.confirm({
            title: Tiger.t('del'),
            body: Tiger.t('confirmDel').replace('%s', btn.getAttribute('data-domain')),
            confirmLabel: Tiger.t('del'), variant: 'danger'
        }).then(function (ok) {
            if (!ok) { return; }
            var body = new URLSearchParams({ module: 'system', service: 'sites', method: 'delete', site_domain_id: btn.getAttribute('data-del') });
            TigerButton.run(btn, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                    .then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function () { table.ajax.reload(null, false); })
              .catch(function () { table.ajax.reload(null, false); });
        });
    });
})();
