// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * ally.index-index.js
 *
 * TigerAlly — nominal accessibility inspector. Scan a CMS page, paste HTML, or roll up every
 * page.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var fb     = document.getElementById('ally-feedback');
    var pageEl = document.getElementById('ally-page');
    var htmlEl = document.getElementById('ally-html');
    var report = document.getElementById('ally-report');

    var RULE_ICON = { 'img-alt': 'fa-image', 'link-name': 'fa-link', 'button-name': 'fa-hand-pointer',
        'input-label': 'fa-i-cursor', 'heading-order': 'fa-heading', 'heading-empty': 'fa-heading',
        'table-headers': 'fa-table', 'duplicate-id': 'fa-clone', 'doc-lang': 'fa-language',
        'positive-tabindex': 'fa-arrow-down-1-9' };

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

    function api(method, extra, btn) {
        var fd = new URLSearchParams(extra || {});
        fd.set('module', 'ally'); fd.set('service', 'scan'); fd.set('method', method);
        var task = function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        };
        return btn ? TigerButton.run(btn, task) : task();
    }

    function loadPages() {
        api('pages').then(function (res) {
            if (!res || res.result !== 1) { return; }
            var list = (res.data && res.data.pages) || [];
            pageEl.innerHTML = '<option value="">— choose a page —</option>';
            list.forEach(function (p) {
                var o = document.createElement('option');
                o.value = p.page_id;
                o.textContent = (p.title || p.slug || p.page_id) + '  (/' + (p.slug || '') + (p.locale ? ' · ' + p.locale : '') + ')';
                pageEl.appendChild(o);
            });
            if (!list.length) { pageEl.innerHTML = '<option value="">(no content pages yet)</option>'; }
        });
    }

    function badge(sev, n) {
        var cls = sev === 'error' ? 'text-bg-danger' : 'text-bg-warning';
        return '<span class="badge ' + cls + ' me-1">' + n + ' ' + sev + (n === 1 ? '' : 's') + '</span>';
    }

    function renderReport(res) {
        if (!res || res.result !== 1 || !res.data) {
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
            return;
        }
        var d = res.data;
        var src = d.source ? ('<div class="small text-body-secondary mb-2">Source: <strong>' + esc(d.source.title || d.source.slug) + '</strong> <span class="badge text-bg-light">' + esc(d.source.format) + '</span></div>') : '<div class="small text-body-secondary mb-2">Source: pasted HTML</div>';
        var head;
        if (d.passed && d.summary.warning === 0) {
            head = '<div class="alert alert-success mb-3"><i class="fa-solid fa-circle-check me-2"></i><strong>Nominal.</strong> No accessibility gaps found.</div>';
        } else {
            head = '<div class="mb-3">' + (d.summary.error ? badge('error', d.summary.error) : '') + (d.summary.warning ? badge('warning', d.summary.warning) : '') +
                (d.passed ? ' <span class="text-success small">no blocking errors</span>' : '') + '</div>';
        }
        var body = '';
        (d.findings || []).forEach(function (f) {
            var icon = RULE_ICON[f.rule] || 'fa-triangle-exclamation';
            var sevcls = f.severity === 'error' ? 'border-danger' : 'border-warning';
            body += '<div class="border-start border-3 ' + sevcls + ' ps-3 mb-3">' +
                '<div class="fw-medium"><i class="fa-solid ' + icon + ' me-2 text-body-secondary"></i>' + esc(f.message) +
                ' <span class="badge text-bg-secondary">' + f.count + '</span></div>';
            (f.samples || []).forEach(function (s) {
                body += '<pre class="small bg-body-tertiary rounded p-2 mt-1 mb-0" style="white-space:pre-wrap;overflow:auto;">' + esc(s) + '</pre>';
            });
            body += '</div>';
        });
        report.innerHTML = src + head + body;
    }

    function renderAll(res) {
        if (!res || res.result !== 1 || !res.data) { return; }
        var t = res.data.totals, rows = res.data.results || [];
        var html = '<div class="mb-3"><span class="badge text-bg-secondary me-1">' + t.pages + ' pages</span>' +
            '<span class="badge text-bg-success me-1">' + t.passed + ' nominal</span>' +
            (t.error ? badge('error', t.error) : '') + (t.warning ? badge('warning', t.warning) : '') + '</div>';
        html += '<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Page</th><th class="text-center">Status</th><th class="text-end">Errors</th><th class="text-end">Warnings</th></tr></thead><tbody>';
        rows.forEach(function (r) {
            var status = r.skipped ? '<span class="badge text-bg-light">skipped</span>'
                : (r.passed ? '<span class="badge text-bg-success">nominal</span>' : '<span class="badge text-bg-danger">issues</span>');
            html += '<tr style="cursor:pointer;" data-scan-page="' + esc(r.page_id) + '"><td>' + esc(r.title || r.slug) +
                ' <span class="text-body-secondary small">/' + esc(r.slug || '') + '</span></td>' +
                '<td class="text-center">' + status + '</td>' +
                '<td class="text-end">' + (r.error || 0) + '</td><td class="text-end">' + (r.warning || 0) + '</td></tr>';
        });
        html += '</tbody></table></div>';
        report.innerHTML = html;
        report.querySelectorAll('[data-scan-page]').forEach(function (tr) {
            tr.addEventListener('click', function () {
                pageEl.value = this.getAttribute('data-scan-page');
                api('scan', { page_id: this.getAttribute('data-scan-page') }).then(renderReport);
            });
        });
    }

    document.getElementById('ally-scan-page').addEventListener('click', function () {
        if (!pageEl.value) { TigerDOM.notify(fb, Tiger.t('choosePage'), { type: 'info' }); return; }
        api('scan', { page_id: pageEl.value }, this).then(renderReport)
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });
    document.getElementById('ally-scan-html').addEventListener('click', function () {
        if (!htmlEl.value.trim()) { TigerDOM.notify(fb, Tiger.t('pasteHtml'), { type: 'info' }); return; }
        api('scan', { html: htmlEl.value }, this).then(renderReport)
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });
    document.getElementById('ally-scan-all').addEventListener('click', function () {
        api('scanAll', {}, this).then(renderAll)
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });

    loadPages();
});
