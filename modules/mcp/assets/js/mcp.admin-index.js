// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * mcp.admin-index.js
 *
 * MCP Connect — the enable toggle, minting/listing/revoking scoped tokens, and the paste-ready
 * mcpServers config for both the npx bridge and the zero-Node PHP one. Everything posts to /api
 * (Mcp_Service_Settings); a minted token’s plaintext is shown once and never re-read.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); };
    var fb  = document.getElementById('mcp-feedback');
    var MCP_URL = fb.getAttribute('data-mcp-url') || '';
    var BRIDGE  = fb.getAttribute('data-bridge') || '';

    function api(module, service, method, body) {
        body = body || {}; body.module = module; body.service = service; body.method = method;
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new URLSearchParams(body) })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    // ---- config blocks (rebuild when the token changes) ----
    var tokenField = document.getElementById('mcp-token-field');
    function renderConfigs() {
        var tok = tokenField.value.trim() || 'tgr_YOUR_TOKEN';
        var npx = {
            mcpServers: { tiger: {
                command: 'npx',
                args: ['-y', 'mcp-remote', MCP_URL, '--header', 'Authorization: Bearer ' + tok]
            } }
        };
        var php = {
            mcpServers: { tiger: {
                command: 'php',
                args: ['/path/to/mcp-bridge.php'],
                env: { TIGER_MCP_URL: MCP_URL, TIGER_MCP_TOKEN: tok }
            } }
        };
        document.getElementById('mcp-cfg-npx').textContent = JSON.stringify(npx, null, 2);
        document.getElementById('mcp-cfg-php').textContent = JSON.stringify(php, null, 2);
    }
    tokenField.addEventListener('input', renderConfigs);
    renderConfigs();

    // ---- copy buttons ----
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-copy]');
        if (!b) { return; }
        var el = document.querySelector(b.getAttribute('data-copy'));
        if (!el) { return; }
        var text = (el.value !== undefined) ? el.value : el.textContent;
        navigator.clipboard.writeText(text).then(function () { TigerDOM.toast(Tiger.t('copied'), { type: 'success' }); });
    });

    // ---- enable toggle ----
    document.getElementById('mcp-save').addEventListener('click', function () {
        var on = document.getElementById('mcp-enabled').checked;
        TigerButton.run(this, function () { return api('mcp', 'settings', 'save', { enabled: on ? 1 : 0 }); })
            .then(function (res) {
                if (res && res.result === 1) { TigerDOM.notify(fb, on ? 'MCP server enabled.' : 'MCP server disabled.', { type: 'success' }); }
                else { ((res && res.messages) || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); }); }
            });
    });

    // ---- token scope pickers (curated starter set checked; extras opt-in) ----
    var MODULES = [
        { m: 'cms', on: true }, { m: 'blog', on: true }, { m: 'media', on: true },
        { m: 'search', on: true }, { m: 'docs', on: true },
        { m: 'access', on: false }, { m: 'analytics', on: false }
    ];
    document.getElementById('mcp-scope-modules').innerHTML = MODULES.map(function (x) {
        return '<div class="form-check form-check-inline me-2"><input class="form-check-input mcp-mod" type="checkbox" value="' + x.m + '" id="mcp-mod-' + x.m + '"' + (x.on ? ' checked' : '') + '>'
             + '<label class="form-check-label small" for="mcp-mod-' + x.m + '">' + x.m + '</label></div>';
    }).join('');
    function scopeModules() {
        return Array.prototype.map.call(document.querySelectorAll('.mcp-mod:checked'), function (c) { return c.value; }).join(',');
    }

    // ---- tokens (scoped: module=mcp, service=settings) ----
    function loadTokens() {
        api('mcp', 'settings', 'tokens').then(function (res) {
            var rows = (res && res.data && res.data.tokens) || [];
            var host = document.getElementById('mcp-tokens');
            host.innerHTML = rows.map(function (t) {
                var id = t.credential_id || '';
                var when = (t.created_at || '').substring(0, 16);
                var badges = (t.org_scoped ? '<span class="badge text-bg-info-subtle text-info-emphasis border ms-1">org</span>' : '')
                           + (t.read_only ? '<span class="badge text-bg-secondary ms-1">read-only</span>' : '');
                var mods = (t.modules || []).join(', ');
                return '<div class="border rounded p-2 d-flex justify-content-between align-items-start gap-2">'
                     + '<div style="min-width:0;"><div><code>' + esc(t.prefix || '') + '…</code>' + badges
                     + ' <span class="small text-body-secondary ms-1">' + esc(when) + '</span></div>'
                     + '<div class="small text-body-secondary text-truncate">' + esc(mods) + '</div></div>'
                     + '<button type="button" class="btn btn-sm btn-outline-danger mcp-revoke text-nowrap" data-id="' + esc(id) + '"><i class="fa-solid fa-trash"></i></button></div>';
            }).join('');
            document.getElementById('mcp-tokens-empty').hidden = rows.length > 0;
        });
    }
    document.getElementById('mcp-mint').addEventListener('click', function () {
        var body = {
            modules:    scopeModules(),
            read_only:  document.getElementById('mcp-scope-readonly').checked ? 1 : 0,
            org_scoped: document.getElementById('mcp-scope-org').checked ? 1 : 0
        };
        TigerButton.run(this, function () { return api('mcp', 'settings', 'mintToken', body); }).then(function (res) {
            if (res && res.result === 1 && res.data && res.data.token) {
                document.getElementById('mcp-minted').classList.remove('d-none');
                document.getElementById('mcp-minted-value').value = res.data.token;
                tokenField.value = res.data.token; renderConfigs();
                loadTokens();
            } else { ((res && res.messages) || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); }); }
        });
    });
    document.getElementById('mcp-tokens').addEventListener('click', function (e) {
        var b = e.target.closest('.mcp-revoke'); if (!b) { return; }
        TigerModal.confirm({ title: Tiger.t('revokeTitle'), body: Tiger.t('revokeBody'), confirmLabel: Tiger.t('revokeLabel'), variant: 'danger' }).then(function (ok) {
            if (!ok) { return; }
            api('mcp', 'settings', 'revokeToken', { credential_id: b.getAttribute('data-id') }).then(function () { TigerDOM.toast(Tiger.t('tokenRevoked'), { type: 'success' }); loadTokens(); });
        });
    });

    loadTokens();
});
