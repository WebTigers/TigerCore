// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * message.compose.js — pick recipients from my org, write, send (TIGER-114).
 *
 * The recipient picker asks the SERVICE (Message_Service_Message::recipients), so the same "active
 * member of this org" rule governs who can be picked and who can be sent to. Sending posts the same
 * recipients[] the service validates again — the picker is convenience, not authorization.
 */
(function () {
    'use strict';

    var form = document.getElementById('msg-compose');
    if (!form) { return; }

    var input   = document.getElementById('msg-to');
    var chips   = document.getElementById('msg-chips');
    var suggest = document.getElementById('msg-suggest');
    var fb      = document.getElementById('msg-feedback');
    var send    = document.getElementById('msg-send');
    var t       = (window.Tiger && Tiger.t) ? Tiger.t : function (k) { return k; };
    var picked  = {};          // user_id => name
    var timer   = null;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function api(method, params) {
        var body = new URLSearchParams({ module: 'message', service: 'message', method: method });
        Object.keys(params || {}).forEach(function (k) {
            if (Array.isArray(params[k])) { params[k].forEach(function (v) { body.append(k + '[]', v); }); }
            else { body.append(k, params[k]); }
        });
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    function notify(res, fallback) {
        var msgs = (res && res.messages) || [];
        if (!msgs.length && fallback) { msgs = [{ message: fallback, class: 'error' }]; }
        msgs.forEach(function (m) { if (window.TigerDOM) { TigerDOM.notify(fb, m.message, { type: m.class }); } });
    }

    /* ---- recipients ---------------------------------------------------------------------- */

    function drawChips() {
        chips.innerHTML = Object.keys(picked).map(function (id) {
            return '<span class="badge text-bg-primary d-inline-flex align-items-center gap-1">' + esc(picked[id])
                 + '<button type="button" class="btn-close btn-close-white" data-remove="' + esc(id) + '" aria-label="' + esc(t('remove')) + '"></button></span>';
        }).join('');
    }

    chips.addEventListener('click', function (e) {
        var b = e.target.closest('[data-remove]');
        if (b) { delete picked[b.getAttribute('data-remove')]; drawChips(); }
    });

    function search(q) {
        api('recipients', { q: q }).then(function (res) {
            var rows = (res && res.data && res.data.recipients) || [];
            rows = rows.filter(function (r) { return !picked[r.user_id]; });
            suggest.innerHTML = rows.length
                ? rows.map(function (r) {
                    return '<button type="button" class="list-group-item list-group-item-action" role="option" data-id="' + esc(r.user_id) + '" data-name="' + esc(r.name) + '">'
                         + esc(r.name) + ' <span class="text-body-secondary">· ' + esc(r.role) + '</span></button>';
                  }).join('')
                : '<div class="list-group-item text-body-secondary">' + esc(t('noMatches')) + '</div>';
            suggest.classList.remove('d-none');
            input.setAttribute('aria-expanded', 'true');
        });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { search(input.value); }, 200);
    });
    input.addEventListener('focus', function () { if (!suggest.children.length) { search(input.value); } else { suggest.classList.remove('d-none'); } });
    document.addEventListener('click', function (e) {
        if (!suggest.contains(e.target) && e.target !== input) { suggest.classList.add('d-none'); input.setAttribute('aria-expanded', 'false'); }
    });
    suggest.addEventListener('click', function (e) {
        var b = e.target.closest('[data-id]');
        if (!b) { return; }
        picked[b.getAttribute('data-id')] = b.getAttribute('data-name');
        drawChips();
        input.value = '';
        suggest.classList.add('d-none');
        input.focus();
    });

    /* ---- send ---------------------------------------------------------------------------- */

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var ids = Object.keys(picked);
        if (!ids.length) { input.focus(); return; }
        send.disabled = true;
        var label = send.innerHTML;
        send.textContent = t('sending');

        api('send', {
            recipients: ids,
            subject:    document.getElementById('msg-subject').value,
            body:       document.getElementById('msg-body').value,
            parent_id:  form.getAttribute('data-reply-to') || ''
        }).then(function (res) {
            notify(res, t('sendFailed'));
            if (res && res.result === 1) { window.location.href = '/message/view/sent'; return; }
            send.disabled = false; send.innerHTML = label;
        });
    });
})();
