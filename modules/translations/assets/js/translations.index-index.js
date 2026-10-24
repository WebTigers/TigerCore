// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * translations.index-index.js
 *
 * Translations — the per-locale string grid: rows come from /api
 * (Translations_Service_Translation::datatable), the editor writes an override into the
 * translation table, and Revert drops it so the shipped file string renders again.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var $   = jQuery;
    var esc = tigerDataTable.esc;

    var modalEl = document.getElementById('tr-modal');
    var modal   = new bootstrap.Modal(modalEl);
    var current = null;   // the entry payload being edited

    function targetLocale() { return document.getElementById('tr-locale').value; }
    function currentFilter() { return document.getElementById('tr-filter').value; }

    var table = tigerDataTable('#translations-table', {
        service: { module: 'translations', service: 'translation', action: 'datatable' },
        order: [[0, 'asc']],
        extraData: function () { return { locale: targetLocale(), filter: currentFilter() }; },
        language: { search: '', searchPlaceholder: Tiger.t('searchPlaceholder') },
        columns: [
            { data: 'key', render: function (t, type) {
                if (type !== 'display') { return t; }
                return '<code class="text-body">' + esc(t) + '</code>';
            } },
            { data: 'source', render: function (t, type) {
                if (type !== 'display') { return t; }
                return '<span class="text-body-secondary">' + esc(t) + '</span>';
            } },
            { data: 'value', render: function (t, type) {
                if (type !== 'display') { return t; }
                return t ? esc(t) : '<span class="text-warning-emphasis fst-italic">' + esc(Tiger.t('usesFile')) + '</span>';
            } },
            { data: null, orderable: false, searchable: false, className: 'text-center', render: function (t, type, row) {
                if (row.overridden) { return '<span class="badge text-bg-primary">' + esc(Tiger.t('badgeOverridden')) + '</span>'; }
                if (row.missing)    { return '<span class="badge text-bg-warning">' + esc('!') + '</span>'; }
                return '<span class="text-body-secondary">—</span>';
            } },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                if (!row.can_edit) { return '<span class="text-body-secondary">—</span>'; }
                return '<button type="button" class="btn btn-sm btn-outline-secondary" data-edit="' + esc(row.key) + '">'
                     + '<i class="fa-solid fa-pen"></i></button>';
            } }
        ]
    });

    document.getElementById('tr-locale').addEventListener('change', function () { table.ajax.reload(); });
    document.getElementById('tr-filter').addEventListener('change', function () { table.ajax.reload(); });

    // --- open the modal for a key ---
    $('#translations-table tbody').on('click', '[data-edit]', function () {
        openEditor(this.getAttribute('data-edit'));
    });

    function openEditor(key) {
        var body = new URLSearchParams({ module: 'translations', service: 'translation', method: 'entry', key: key });
        fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.result !== 1) { return; }
                current = res.data;
                renderModal(res.data);
                modal.show();
            }).catch(function () {});
    }

    function renderModal(d) {
        document.getElementById('tr-modal-key').textContent = d.key;
        document.getElementById('tr-modal-keyfield').value = d.key;
        document.getElementById('tr-modal-feedback').innerHTML = '';

        // Fields, one per locale — default first (the source), then targets with a Translate button.
        var host = document.getElementById('tr-modal-fields');
        host.innerHTML = '';
        d.locales.forEach(function (loc) {
            var wrap = document.createElement('div');
            wrap.className = 'mb-3';
            var badge = loc.is_default
                ? '<span class="badge text-bg-secondary ms-2">' + esc(Tiger.t('badgeDefault')) + '</span>'
                : (loc.overridden ? '<span class="badge text-bg-primary ms-2">' + esc(Tiger.t('badgeOverridden')) + '</span>' : '');
            var btn = loc.is_default ? '' :
                '<button type="button" class="btn btn-outline-secondary" data-tr-translate="' + esc(loc.code) + '"' + (d.ai ? '' : ' disabled title="Connect an AI provider in AI Agent settings"') + '>'
                + '<i class="fa-solid fa-language me-1"></i>' + esc(Tiger.t('translate')) + '</button>';
            wrap.innerHTML =
                '<label class="form-label small fw-semibold mb-1">' + esc(loc.name) + ' <span class="text-body-secondary">(' + esc(loc.code) + ')</span>' + badge + '</label>' +
                '<div class="input-group">' +
                    '<textarea class="form-control" rows="2" data-tr-value="' + esc(loc.code) + '">' + esc(loc.value) + '</textarea>' +
                    btn +
                '</div>';
            host.appendChild(wrap);
        });

        // Context: show grep usage immediately; AI summary behind the button (cached server-side).
        var summary = document.getElementById('tr-context-summary');
        var usage   = document.getElementById('tr-context-usage');
        summary.textContent = '';
        usage.innerHTML = '';
        (d.usage || []).forEach(function (u) {
            var li = document.createElement('li');
            li.innerHTML = '<code>' + esc(u.file) + ':' + u.line + '</code>';
            usage.appendChild(li);
        });
        if (!d.usage || !d.usage.length) { usage.innerHTML = '<li class="fst-italic">' + esc(Tiger.t('noRefs')) + '</li>'; }
        var ctxBtn = document.getElementById('tr-context-btn');
        ctxBtn.hidden = !d.ai;
    }

    // --- per-locale AI translate ---
    $('#tr-modal-fields').on('click', '[data-tr-translate]', function () {
        var code = this.getAttribute('data-tr-translate');
        var field = document.querySelector('[data-tr-value="' + code + '"]');
        var src = current ? current.source : '';
        var body = new URLSearchParams({ module: 'translations', service: 'translation', method: 'translate', source: src, locale: code, key: current ? current.key : '' });
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1 && res.data && field) { field.value = res.data.text; field.focus(); }
            else { (res && res.messages || []).forEach(function (m) { TigerDOM.notify(document.getElementById('tr-modal-feedback'), m.message, { type: m.class }); }); }
        }).catch(function () {});
    });

    // --- AI context summary ---
    document.getElementById('tr-context-btn').addEventListener('click', function () {
        if (!current) { return; }
        var body = new URLSearchParams({ module: 'translations', service: 'translation', method: 'context', key: current.key });
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1 && res.data) { document.getElementById('tr-context-summary').textContent = res.data.summary; }
        }).catch(function () {});
    });

    // --- save ---
    document.getElementById('tr-save').addEventListener('click', function () {
        if (!current) { return; }
        var fb = document.getElementById('tr-modal-feedback');
        var body = new URLSearchParams({ module: 'translations', service: 'translation', method: 'save', key: current.key });
        document.querySelectorAll('#tr-modal-fields [data-tr-value]').forEach(function (el) {
            body.append('values[' + el.getAttribute('data-tr-value') + ']', el.value);
        });
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) {
                modal.hide();
                TigerDOM.toast(Tiger.t('saved'), { type: 'success' });
                table.ajax.reload(null, false);
                return;
            }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });

    // --- revert all locales for the key ---
    document.getElementById('tr-revert').addEventListener('click', function () {
        if (!current) { return; }
        var key = current.key, self = this;
        TigerModal.confirm({ title: Tiger.t('revertConfirm'), body: key, confirmLabel: Tiger.t('revertConfirm') }).then(function (ok) {
            if (!ok) { return; }
            var body = new URLSearchParams({ module: 'translations', service: 'translation', method: 'revert', key: key });
            TigerButton.run(self, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                    .then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function () {
                modal.hide();
                TigerDOM.toast(Tiger.t('reverted'), { type: 'success' });
                table.ajax.reload(null, false);
            }).catch(function () {});
        });
    });
});
