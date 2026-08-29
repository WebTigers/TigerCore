// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * comment.admin.js — the moderation queue grid.
 *
 * Rows come from /api (Comment_Service_Comment::datatable); every action posts back to the same
 * service. Authorization lives on the server — this only draws.
 */
(function () {
    var grid, fb;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function stars(n) {
        if (!n) { return ''; }
        var h = '';
        for (var i = 1; i <= 5; i++) { h += '<i class="fa-' + (i <= n ? 'solid' : 'regular') + ' fa-star"></i>'; }
        return '<span class="text-warning me-2">' + h + '</span>';
    }

    function act(id, status, label, variant) {
        return '<button type="button" class="btn btn-sm btn-outline-' + variant + ' ms-1 cm-act"'
             + ' data-id="' + esc(id) + '" data-status="' + esc(status) + '">' + esc(label) + '</button>';
    }

    function boot() {
        var table = document.getElementById('comment-grid');
        if (!table || !window.tigerDataTable) { return; }
        fb = document.getElementById('comment-feedback');

        var status = document.getElementById('comment-status');

        grid = tigerDataTable('#comment-grid', {
            service: { module: 'comment', service: 'comment', method: 'datatable' },
            extraData: function () { return { status: status ? status.value : 'pending' }; },
            order: [[3, 'desc']],
            columns: [
                { data: 'body', render: function (d, type, row) {
                    return stars(row.rating) + esc((d || '').slice(0, 240))
                         + (row.verified ? ' <span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">verified</span>' : '');
                } },
                { data: 'author', render: esc },
                { data: 'subject_label', render: function (d, type, row) {
                    // A subject that no longer exists is FLAGGED, not hidden — an orphaned thread is
                    // precisely what an operator needs to find.
                    var label = row.subject_url
                        ? '<a href="' + esc(row.subject_url) + '" target="_blank" rel="noopener">' + esc(d) + '</a>'
                        : esc(d);
                    return label + (row.subject_gone
                        ? ' <span class="badge text-bg-warning-subtle text-warning-emphasis border border-warning-subtle">deleted</span>' : '');
                } },
                { data: 'created_at', render: function (d) { return esc((d || '').slice(0, 16)); } },
                { data: 'comment_id', orderable: false, className: 'text-end', render: function (d, type, row) {
                    var out = '';
                    if (row.status !== 'approved') { out += act(d, 'approved', 'Approve', 'success'); }
                    if (row.status !== 'spam')     { out += act(d, 'spam',     'Spam',    'warning'); }
                    if (row.status !== 'rejected') { out += act(d, 'rejected', 'Reject',  'danger'); }
                    return out;
                } }
            ]
        });

        if (status) { status.addEventListener('change', function () { if (grid) { grid.ajax.reload(); } }); }

        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.cm-act');
            if (!btn) { return; }
            TigerButton.run(btn, function () {
                var body = new URLSearchParams({
                    module: 'comment', service: 'comment', method: 'moderate',
                    comment_id: btn.getAttribute('data-id'), status: btn.getAttribute('data-status')
                });
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                    .then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function (res) {
                (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
                if (res && res.result === 1 && grid) { grid.ajax.reload(null, false); }
            });
        });
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
