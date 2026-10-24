// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * system.acl-index.js
 *
 * ACL Simulator — pick a role × resource × privilege and see the decision AND why (the deciding
 * rule or deny-by-default, plus the role inheritance chain). The answer to "why am I locked
 * out?" Reads the live ACL over /api (System_Service_Acl); changes nothing. Semantic Bootstrap
 * only. See ADMIN.md.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
(function () {
    var fb = document.getElementById('acl-feedback');
    function post(method, body) {
        var fd = new URLSearchParams(body || {});
        fd.set('module', 'system'); fd.set('service', 'acl'); fd.set('method', method);
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }
    function opt(sel, val, label) { var o = document.createElement('option'); o.value = val; o.textContent = label || val; sel.appendChild(o); }

    // populate the pickers
    post('catalog', {}).then(function (res) {
        if (!res || res.result !== 1) { return; }
        var rs = document.getElementById('acl-role'), rr = document.getElementById('acl-resource');
        (res.data.roles || []).forEach(function (r) { opt(rs, r); });
        opt(rr, '', '— any resource —');
        (res.data.resources || []).forEach(function (r) { opt(rr, r); });
    });

    document.getElementById('acl-run').addEventListener('click', function () {
        var body = {
            role: document.getElementById('acl-role').value,
            resource: document.getElementById('acl-resource').value,
            privilege: document.getElementById('acl-privilege').value.trim()
        };
        TigerButton.run(this, function () { return post('simulate', body); }).then(function (res) {
            if (!res || res.result !== 1) {
                (res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
                return;
            }
            var x = res.data.explain;
            document.getElementById('acl-empty').classList.add('d-none');
            document.getElementById('acl-result').classList.remove('d-none');
            document.getElementById('acl-verdict').innerHTML = x.allowed
                ? '<span class="badge text-bg-success fs-6"><i class="fa-solid fa-lock-open me-1"></i>ALLOWED</span>'
                : '<span class="badge text-bg-danger fs-6"><i class="fa-solid fa-lock me-1"></i>DENIED</span>';
            document.getElementById('acl-reason').textContent = x.reason;
            document.getElementById('acl-rule').innerHTML = x.rule
                ? '<code>role=' + x.rule.role + ' · resource=' + x.rule.resource + ' · privilege=' + x.rule.privilege + ' · ' + x.rule.permission + '</code>'
                : '<span class="text-body-secondary">none — deny-by-default</span>';
            document.getElementById('acl-chain').textContent = (x.roleChain || []).join('  →  ') || '—';
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });
})();
