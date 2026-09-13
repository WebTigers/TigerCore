// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** message.blocked.js — the people I've blocked, and unblocking them (TIGER-114). */
(function () {
    'use strict';

    var list = document.getElementById('msg-blocked');
    if (!list) { return; }
    var empty = document.getElementById('msg-blocked-empty');
    var fb    = document.getElementById('msg-feedback');
    var t     = (window.Tiger && Tiger.t) ? Tiger.t : function (k) { return k; };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function api(method, params) {
        var body = new URLSearchParams({ module: 'message', service: 'message', method: method });
        Object.keys(params || {}).forEach(function (k) { body.append(k, params[k]); });
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    function notify(res, fallback) {
        var msgs = (res && res.messages) || [];
        if (!msgs.length && fallback) { msgs = [{ message: fallback, class: 'error' }]; }
        msgs.forEach(function (m) { if (window.TigerDOM) { TigerDOM.notify(fb, m.message, { type: m.class }); } });
    }

    function load() {
        api('blocked', {}).then(function (res) {
            if (!res || res.result !== 1) { notify(res, t('loadFailed')); return; }
            var rows = res.data.blocked || [];
            list.innerHTML = rows.map(function (r) {
                return '<div class="list-group-item d-flex justify-content-between align-items-center">'
                     + '<span>' + esc(r.username) + ' <span class="text-body-secondary">· ' + esc(t('since', r.created_at)) + '</span></span>'
                     + '<button type="button" class="btn btn-sm btn-outline-secondary" data-unblock="' + esc(r.blocked_user_id) + '">' + esc(t('unblock')) + '</button>'
                     + '</div>';
            }).join('');
            empty.textContent = t('none');
            empty.classList.toggle('d-none', rows.length > 0);
        });
    }

    list.addEventListener('click', function (e) {
        var b = e.target.closest('[data-unblock]');
        if (!b) { return; }
        b.disabled = true;
        api('unblock', { user_id: b.getAttribute('data-unblock') }).then(function (res) {
            notify(res, t('actionFailed'));
            load();
        });
    });

    load();
})();
