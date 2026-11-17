/*! SPDX-License-Identifier: BSD-3-Clause · © 2026 WebTigers · Tiger™/WebTigers™ are trademarks */
/**
 * Theme content screen (TIGER-455): "Update now" posts System_Service_Themecontent::fetch with the screen's token,
 * then reports files checked / updated / removed and the manifest's publish stamp, and refreshes the status rows.
 */
(function () {
    'use strict';
    var btn = document.getElementById('tc-fetch');
    if (!btn) { return; }
    var fb = document.getElementById('tc-feedback');

    function t(key, fallback) { return (window.Tiger && Tiger.t) ? Tiger.t(key) : fallback; }
    function set(id, text) { var el = document.getElementById(id); if (el) { el.textContent = text; } }
    function utc(ts) {
        if (!ts) { return '—'; }
        var d = new Date(ts * 1000);
        return d.toISOString().slice(0, 16).replace('T', ' ') + ' UTC';
    }

    btn.addEventListener('click', function () {
        var fd  = new URLSearchParams();
        var tok = document.querySelector('#tc-token [name="_csrf"]');
        var force = document.getElementById('tc-force');
        fd.set('module', 'system'); fd.set('service', 'themecontent'); fd.set('method', 'fetch');
        fd.set('_csrf', tok ? tok.value : '');
        fd.set('force', force && force.checked ? '1' : '0');

        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1 && res.data) {
                var d = res.data;
                set('tc-files', String(d.files));
                set('tc-generated', d.generated || '—');
                set('tc-synced', utc(d.synced_at));
                var msg = t('result', '%1$s files checked · %2$s updated · %3$s removed · published %4$s')
                    .replace('%1$s', d.files).replace('%2$s', d.changed).replace('%3$s', d.removed).replace('%4$s', d.generated || '—');
                TigerDOM.notify(fb, msg, { type: 'success' });
                return;
            }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () {
            TigerDOM.notify(fb, t('networkError', 'Network error — please try again.'), { type: 'error' });
        });
    });
})();
