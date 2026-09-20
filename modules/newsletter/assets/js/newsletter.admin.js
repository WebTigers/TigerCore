// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * newsletter.admin.js — the subscriber list grid.
 *
 * Rows come from /api (Newsletter_Service_Subscribe::datatable). Read-only: authorization and every
 * decision live on the server — this only draws.
 */
(function () {
    var grid;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function badge(status) {
        var map = {
            confirmed:    'text-bg-success-subtle text-success-emphasis border border-success-subtle',
            pending:      'text-bg-warning-subtle text-warning-emphasis border border-warning-subtle',
            unsubscribed: 'text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle'
        };
        return '<span class="badge ' + (map[status] || '') + '">' + esc(status) + '</span>';
    }

    function boot() {
        var table = document.getElementById('newsletter-grid');
        if (!table || !window.tigerDataTable) { return; }

        var status = document.getElementById('newsletter-status');

        grid = tigerDataTable('#newsletter-grid', {
            service: { module: 'newsletter', service: 'subscribe', method: 'datatable' },
            extraData: function () { return { status: status ? status.value : '' }; },
            order: [[4, 'desc']],
            columns: [
                { data: 'email', render: esc },
                { data: 'name', render: esc },
                { data: 'status', render: function (d) { return badge(d); } },
                { data: 'source', render: esc },
                { data: 'created_at', render: function (d) { return esc((d || '').slice(0, 16)); } }
            ]
        });

        if (status) { status.addEventListener('change', function () { if (grid) { grid.ajax.reload(); } }); }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
