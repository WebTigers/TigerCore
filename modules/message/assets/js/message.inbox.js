// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * message.inbox.js — the inbox, archive and sent views (TIGER-114).
 *
 * Every read and every action is a /api call to Message_Service_Message: this only draws. Strings
 * come from Tiger.t(), registered by the view. No browser dialogs — confirmation is the in-app modal.
 */
(function () {
    'use strict';

    var root = document.getElementById('msg-root');
    if (!root) { return; }

    var view    = root.getAttribute('data-view') || 'inbox';
    var maySend = root.getAttribute('data-may-send') === '1';
    var list    = document.getElementById('msg-list');
    var empty   = document.getElementById('msg-empty');
    var fb      = document.getElementById('msg-feedback');
    var reader  = { empty: document.getElementById('msg-reader-empty'), body: document.getElementById('msg-reader-body') };
    var badge   = document.getElementById('msg-unread-badge');
    var t       = (window.Tiger && Tiger.t) ? Tiger.t : function (k) { return k; };
    var current = null;

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

    function when(iso) {
        var d = new Date(iso.replace(' ', 'T') + 'Z');
        return isNaN(d) ? esc(iso) : esc(d.toLocaleString());
    }

    function sender(m) {
        return m.sender_id === null ? '<span class="badge text-bg-secondary">' + esc(t('system')) + '</span>' : esc(m.sender_name || '');
    }

    /* ---- the list ------------------------------------------------------------------------ */

    function row(m) {
        var unread = m.read === false;
        return '<a href="#" class="list-group-item list-group-item-action msg-row' + (unread ? ' fw-semibold' : '') + (current === m.message_id ? ' active' : '') + '"'
             + ' data-id="' + esc(m.message_id) + '">'
             + '<div class="d-flex justify-content-between align-items-start gap-2">'
             +   '<div class="text-truncate">'
             +     (unread ? '<i class="fa-solid fa-circle text-primary me-2" aria-hidden="true"></i>' : '')
             +     esc(m.subject)
             +   '</div>'
             +   '<span class="text-body-secondary text-nowrap">' + when(m.created_at) + '</span>'
             + '</div>'
             + '<div class="d-flex justify-content-between gap-2 text-body-secondary">'
             +   '<span class="text-truncate">' + (view === 'sent' ? '' : t('from') + ' ' + sender(m) + ' — ') + esc(m.preview) + '</span>'
             + '</div>'
             + '</a>';
    }

    function load() {
        return api('list', { view: view }).then(function (res) {
            if (!res || res.result !== 1) { notify(res, t('loadFailed')); return; }
            var msgs = res.data.messages || [];
            list.innerHTML = msgs.map(row).join('');
            empty.textContent = t('noMessages');
            empty.classList.toggle('d-none', msgs.length > 0);
            setUnread(res.data.unread);
        });
    }

    function setUnread(n) {
        if (!badge) { return; }
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.classList.toggle('d-none', !(n > 0));
        // The header bell is server-rendered; keep it honest without a reload.
        document.querySelectorAll('.admin-header-badge').forEach(function (b) {
            b.textContent = n > 99 ? '99+' : String(n);
            b.classList.toggle('d-none', !(n > 0));
        });
    }

    /* ---- the reader ---------------------------------------------------------------------- */

    function open(id) {
        api('get', { message_id: id }).then(function (res) {
            if (!res || res.result !== 1) { notify(res, t('loadFailed')); return; }
            var m = res.data.message;
            current = id;
            reader.empty.classList.add('d-none');
            reader.body.classList.remove('d-none');

            var actions = '';
            if (view !== 'sent') {
                actions += btn('read',    m.read ? t('unread') : t('read'), 'outline-secondary');
                actions += btn('archive', m.archived ? t('unarchive') : t('archive'), 'outline-secondary');
                if (maySend && m.sender_id) { actions += btn('reply', t('reply'), 'outline-primary'); }
                if (m.sender_id) { actions += btn('block', t('block'), 'outline-danger'); }
                actions += btn('delete', t('delete'), 'outline-danger');
            }

            reader.body.innerHTML =
                '<div class="d-flex justify-content-between align-items-start gap-3 mb-3">'
              +   '<div><h2 class="h5 mb-1">' + esc(m.subject) + '</h2>'
              +   '<div class="text-body-secondary">' + (m.sender_id === null ? sender(m) : t('from') + ' ' + sender(m)) + ' · ' + when(m.created_at) + '</div>'
              +   (m.recipients ? '<div class="text-body-secondary">' + esc(t('to')) + ' ' + m.recipients.map(function (r) { return esc(r.username); }).join(', ') + '</div>' : '')
              +   '</div>'
              + '</div>'
              + '<div class="msg-body mb-4">' + esc(m.body).replace(/\n/g, '<br>') + '</div>'
              + '<div class="d-flex flex-wrap gap-2" id="msg-actions" data-sender="' + esc(m.sender_id || '') + '">' + actions + '</div>';

            document.querySelectorAll('.msg-row').forEach(function (r) {
                r.classList.toggle('active', r.getAttribute('data-id') === id);
                if (r.getAttribute('data-id') === id) { r.classList.remove('fw-semibold'); var dot = r.querySelector('.fa-circle'); if (dot) { dot.remove(); } }
            });
            // Opening marks it read on the server; reflect that in the badge.
            if (m.read === false) { api('unreadCount', {}).then(function (r2) { if (r2 && r2.data) { setUnread(r2.data.unread); } }); }
        });
    }

    function btn(act, label, variant) {
        return '<button type="button" class="btn btn-sm btn-' + variant + '" data-act="' + act + '">' + esc(label) + '</button>';
    }

    /* ---- actions ------------------------------------------------------------------------- */

    list.addEventListener('click', function (e) {
        var a = e.target.closest('.msg-row');
        if (!a) { return; }
        e.preventDefault();
        open(a.getAttribute('data-id'));
    });

    reader.body.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-act]');
        if (!b || !current) { return; }
        var act = b.getAttribute('data-act');
        var isRead = b.textContent.trim() === t('unread');
        var isArch = b.textContent.trim() === t('unarchive');

        switch (act) {
            case 'read':    return call(isRead ? 'markUnread' : 'markRead', { message_id: current });
            case 'archive': return call(isArch ? 'unarchive' : 'archive', { message_id: current }, true);
            case 'reply':   window.location.href = '/message/compose/reply/' + encodeURIComponent(current); return;
            case 'delete':  return confirm(t('deleteTitle'), t('deleteBody'), function () { call('delete', { message_id: current }, true); });
            case 'block':
                var sid = document.getElementById('msg-actions').getAttribute('data-sender');
                return confirm(t('blockTitle'), t('blockBody'), function () { call('block', { user_id: sid }); });
        }
    });

    function call(method, params, clearsReader) {
        api(method, params).then(function (res) {
            notify(res, t('actionFailed'));
            if (res && res.result === 1) {
                if (clearsReader) { current = null; reader.body.classList.add('d-none'); reader.empty.classList.remove('d-none'); }
                load().then(function () { if (current) { open(current); } });
            }
        });
    }

    function confirm(title, body, onOk) {
        var el = document.getElementById('msg-confirm');
        document.getElementById('msg-confirm-title').textContent = title;
        document.getElementById('msg-confirm-body').textContent  = body;
        var ok = document.getElementById('msg-confirm-ok');
        ok.textContent = t('confirm');
        var modal = bootstrap.Modal.getOrCreateInstance(el);
        function go() { ok.removeEventListener('click', go); modal.hide(); onOk(); }
        ok.addEventListener('click', go);
        modal.show();
    }

    load();
})();
