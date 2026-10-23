/* Email Templates — the authoring screens (TIGER-267).
 * Two surfaces share this file: the LIST (#email-templates-table) and the EDITOR (#email-tpl-form).
 * All data/mutations are /api calls through the house primitives; never a page-POST, never a browser
 * confirm()/alert() (TigerModal only). */
(function () {
    'use strict';

    function T(k) { return (window.Tiger && Tiger.t) ? Tiger.t(k) : k; }
    function post(fd) {
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }
    function notify(fb, msg, type) { if (window.TigerDOM && fb) { TigerDOM.notify(fb, msg, { type: type || 'info' }); } }
    function msgs(res, fb, fallback) {
        (res && res.messages && res.messages.length ? res.messages : [{ message: fallback, class: 'error' }])
            .forEach(function (m) { notify(fb, m.message, m.class); });
    }

    /* ---------------------------------------------------------------- LIST */
    function initList() {
        var tableEl = document.getElementById('email-templates-table');
        if (!tableEl || typeof tigerDataTable !== 'function') { return; }
        var fb  = document.getElementById('email-feedback');
        var esc = tigerDataTable.esc;

        var table = tigerDataTable('#email-templates-table', {
            service: { module: 'email', service: 'template', action: 'datatable' },
            order: [[0, 'asc']],
            columns: [
                { data: 'label', render: function (t, type, row) {
                    return type !== 'display' ? t :
                        '<a href="/email/admin/edit/key/' + encodeURIComponent(row.key) + '" class="fw-semibold text-decoration-none">' + esc(t) + '</a>';
                } },
                { data: 'key', render: function (t, type) {
                    return type !== 'display' ? t : '<code class="text-body-secondary">' + esc(t) + '</code>';
                } },
                { data: 'group', render: function (t, type) {
                    return type !== 'display' ? t : '<span class="badge text-bg-light text-uppercase">' + esc(t) + '</span>';
                } },
                { data: 'customized', render: function (t, type) {
                    if (type !== 'display') { return t ? 1 : 0; }
                    return t
                        ? '<span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle">' + esc(T('statusCustomized')) + '</span>'
                        : '<span class="text-body-tertiary small">' + esc(T('statusDefault')) + '</span>';
                } },
                { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                    if (type !== 'display') { return ''; }
                    var h = '';
                    if (row.can_edit) {
                        h += '<a href="/email/admin/edit/key/' + encodeURIComponent(row.key) + '" class="btn btn-sm btn-outline-secondary" title="' + esc(T('actionEdit')) + '" aria-label="' + esc(T('actionEdit')) + '"><i class="fa-solid fa-pen"></i></a> ';
                    }
                    if (row.can_reset) {
                        h += '<button type="button" class="btn btn-sm btn-outline-danger" data-email-reset="' + esc(row.key) + '" title="' + esc(T('actionReset')) + '" aria-label="' + esc(T('actionReset')) + '"><i class="fa-solid fa-rotate-left"></i></button>';
                    }
                    return h || '<span class="text-body-secondary">—</span>';
                } }
            ]
        });

        tableEl.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-email-reset]');
            if (!btn) { return; }
            var key = btn.getAttribute('data-email-reset');
            TigerModal.confirm({ title: T('confirmResetTitle'), body: T('confirmResetBody'), confirmLabel: T('actionReset'), variant: 'danger' })
                .then(function (ok) {
                    if (!ok) { return; }
                    var fd = new URLSearchParams({ module: 'email', service: 'template', method: 'reset', key: key });
                    TigerButton.run(btn, function () { return post(fd); }).then(function (res) {
                        if (res && res.result === 1) { notify(fb, T('resetDone'), 'success'); table.ajax.reload(null, false); return; }
                        msgs(res, fb, T('general'));
                    }).catch(function () { notify(fb, T('networkError'), 'error'); });
                });
        });
    }

    /* -------------------------------------------------------------- EDITOR */
    function initEditor() {
        var form = document.getElementById('email-tpl-form');
        if (!form) { return; }
        var wrap    = document.querySelector('[data-email-key]');
        var key     = wrap ? wrap.getAttribute('data-email-key') : '';
        var fb      = document.getElementById('email-feedback');
        var bodyEl  = document.getElementById('email-tpl-body');
        var subjEl  = document.getElementById('email-tpl-subject');
        var frame   = document.getElementById('email-preview-frame');
        var subjOut = document.getElementById('email-preview-subject');

        /* Insert a {{var}} at the cursor in the body textarea (the variable palette). */
        document.querySelectorAll('[data-email-var]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var token = '{{' + chip.getAttribute('data-email-var') + '}}';
                var start = bodyEl.selectionStart || 0;
                var end   = bodyEl.selectionEnd || 0;
                bodyEl.value = bodyEl.value.slice(0, start) + token + bodyEl.value.slice(end);
                bodyEl.focus();
                bodyEl.selectionStart = bodyEl.selectionEnd = start + token.length;
            });
        });

        function doPreview(btn) {
            var fd = new URLSearchParams({ module: 'email', service: 'template', method: 'preview',
                key: key, subject: subjEl ? subjEl.value : '', body: bodyEl ? bodyEl.value : '' });
            var run = function () { return post(fd); };
            var done = function (res) {
                if (res && res.result === 1 && res.data) {
                    if (subjOut) { subjOut.textContent = res.data.subject || ''; }
                    if (frame) { frame.srcdoc = res.data.html || ''; }
                    return;
                }
                msgs(res, fb, T('general'));
            };
            if (btn && window.TigerButton) { TigerButton.run(btn, run).then(done).catch(function () { notify(fb, T('networkError'), 'error'); }); }
            else { run().then(done).catch(function () {}); }
        }

        var prevBtn = document.getElementById('email-tpl-preview');
        if (prevBtn) { prevBtn.addEventListener('click', function () { doPreview(this); }); }
        doPreview(null);   // first paint

        document.getElementById('email-tpl-save').addEventListener('click', function () {
            var fd = new URLSearchParams(new FormData(form));
            fd.set('module', 'email'); fd.set('service', 'template'); fd.set('method', 'save');
            TigerButton.run(this, function () { return post(fd); }).then(function (res) {
                if (res && res.result === 1) { notify(fb, T('saved'), 'success'); doPreview(null); return; }
                if (res && res.form) {
                    Object.keys(res.form).forEach(function (f) {
                        var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); }
                    });
                    notify(fb, T('fixFields'), 'error');
                }
                msgs(res, fb, T('general'));
            }).catch(function () { notify(fb, T('networkError'), 'error'); });
        });

        var resetBtn = document.getElementById('email-tpl-reset');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                var btn = this;
                TigerModal.confirm({ title: T('confirmResetTitle'), body: T('confirmResetBody'), confirmLabel: T('actionReset'), variant: 'danger' })
                    .then(function (ok) {
                        if (!ok) { return; }
                        var fd = new URLSearchParams({ module: 'email', service: 'template', method: 'reset', key: key });
                        TigerButton.run(btn, function () { return post(fd); }).then(function (res) {
                            if (res && res.result === 1) { window.location = '/email/admin'; return; }
                            msgs(res, fb, T('general'));
                        }).catch(function () { notify(fb, T('networkError'), 'error'); });
                    });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initList();
        initEditor();
    });
})();
