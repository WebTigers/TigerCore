// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * agent.mcp-index.js
 *
 * MCP Connections (admin). Register external MCP servers whose tools the in-app agent may call
 * (outbound; TIGERMCP.md §9). Add/edit a connection (label, URL, optional Bearer token,
 * enabled), test it (list its tools), remove it. All over /api → Agent_Service_Mcp; the token
 * never round-trips to the browser.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); };
    var fb  = document.getElementById('mcp-feedback');

    function api(method, body) {
        body = body || {}; body.module = 'agent'; body.service = 'mcp'; body.method = method;
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams(body) }).then(function (r) { return r.json().catch(function () { return {}; }); });
    }
    function toast(res) { ((res && res.messages) || []).forEach(function (m) { TigerDOM.toast(m.message, { type: m.class }); }); }

    function resetForm() {
        document.getElementById('mcp-id').value = '';
        document.getElementById('mcp-label').value = '';
        document.getElementById('mcp-url').value = '';
        document.getElementById('mcp-token').value = '';
        document.getElementById('mcp-enabled').checked = true;
        document.getElementById('mcp-form-title').textContent = 'Add a connection';
        document.getElementById('mcp-reset').classList.add('d-none');
    }
    document.getElementById('mcp-reset').addEventListener('click', resetForm);

    function render(rows) {
        var host = document.getElementById('mcp-list');
        host.innerHTML = (rows || []).map(function (c) {
            var state = c.enabled ? '<span class="badge text-bg-success">on</span>' : '<span class="badge text-bg-secondary">off</span>';
            var tok = c.has_token ? '<span class="badge text-bg-light border ms-1"><i class="fa-solid fa-key me-1"></i>token</span>' : '';
            return '<div class="border rounded p-2" data-id="' + esc(c.id) + '">'
                 + '<div class="d-flex justify-content-between align-items-start gap-2">'
                 + '<div style="min-width:0;"><div class="fw-semibold">' + esc(c.label) + ' ' + state + tok + '</div>'
                 + '<div class="small text-body-secondary text-truncate font-monospace">' + esc(c.url) + '</div>'
                 + '<div class="small text-success mcp-test-out"></div></div>'
                 + '<div class="text-nowrap d-flex gap-1">'
                 + '<button type="button" class="btn btn-sm btn-outline-secondary mcp-test" title="Test — list tools"><i class="fa-solid fa-vial"></i></button>'
                 + '<button type="button" class="btn btn-sm btn-outline-secondary mcp-edit"><i class="fa-solid fa-pen"></i></button>'
                 + '<button type="button" class="btn btn-sm btn-outline-danger mcp-remove"><i class="fa-solid fa-trash"></i></button>'
                 + '</div></div></div>';
        }).join('');
        document.getElementById('mcp-empty').hidden = (rows || []).length > 0;
        window._mcpRows = rows || [];
    }
    function load() { api('connections').then(function (res) { render((res && res.data && res.data.connections) || []); }); }

    document.getElementById('mcp-save').addEventListener('click', function () {
        var body = {
            id:      document.getElementById('mcp-id').value,
            label:   document.getElementById('mcp-label').value.trim(),
            url:     document.getElementById('mcp-url').value.trim(),
            token:   document.getElementById('mcp-token').value,
            enabled: document.getElementById('mcp-enabled').checked ? 1 : 0
        };
        TigerButton.run(this, function () { return api('save', body); }).then(function (res) {
            if (res && res.result === 1) { TigerDOM.notify(fb, Tiger.t('connectionSaved'), { type: 'success' }); resetForm(); render(res.data.connections || []); }
            else { toast(res); }
        });
    });

    document.getElementById('mcp-list').addEventListener('click', function (e) {
        var card = e.target.closest('[data-id]'); if (!card) { return; }
        var id = card.getAttribute('data-id');
        var row = (window._mcpRows || []).filter(function (c) { return c.id === id; })[0] || {};
        if (e.target.closest('.mcp-edit')) {
            document.getElementById('mcp-id').value = id;
            document.getElementById('mcp-label').value = row.label || '';
            document.getElementById('mcp-url').value = row.url || '';
            document.getElementById('mcp-token').value = '';
            document.getElementById('mcp-enabled').checked = !!row.enabled;
            document.getElementById('mcp-form-title').textContent = 'Edit connection';
            document.getElementById('mcp-reset').classList.remove('d-none');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else if (e.target.closest('.mcp-remove')) {
            TigerModal.confirm({ title: Tiger.t('removeConnectionTitle'), body: Tiger.t('removeConnectionBody'), confirmLabel: Tiger.t('removeLabel'), variant: 'danger' }).then(function (ok) {
                if (!ok) { return; }
                api('remove', { id: id }).then(function (res) { toast(res); render((res.data && res.data.connections) || []); });
            });
        } else if (e.target.closest('.mcp-test')) {
            var out = card.querySelector('.mcp-test-out'); out.textContent = 'Testing…'; out.className = 'small text-body-secondary mcp-test-out';
            api('test', { id: id }).then(function (res) {
                if (res && res.result === 1) {
                    var names = (res.data.tools || []).map(function (t) { return t.name; }).slice(0, 8).join(', ');
                    out.className = 'small text-success mcp-test-out';
                    out.textContent = '✓ ' + res.data.count + ' tool(s)' + (names ? ': ' + names : '');
                } else { out.className = 'small text-danger mcp-test-out'; out.textContent = '✗ could not reach the server'; }
            });
        }
    });

    load();
});
