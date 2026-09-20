/*! SPDX-License-Identifier: BSD-3-Clause · © 2026 WebTigers · Tiger™/WebTigers™ are trademarks */
/**
 * tiger.header-flyout.js — the quick-view panel behind a header icon (TIGER-129).
 *
 * A GENERIC, module-agnostic fly-out for any header item that declared a `flyout` on Tiger_Admin_Header.
 * The theme rendered an empty panel next to the icon carrying a `data-tiger-flyout` descriptor (endpoint
 * to read, per-row `action` endpoint, the "view all" href, and ALREADY-TRANSLATED labels). This asset
 * fills that panel over /api on open, draws each row as a title + a short preview + the row action, and
 * links to the full management screen at the bottom — the peek before the page.
 *
 * House primitives only: TigerDOM.toggle for the reveal (Web Animations, reduced-motion-aware — the same
 * primitive the sidebar submenus use), TigerButton.run for the row action's busy state, TigerDOM.notify
 * for feedback. No jQuery, no bespoke CSS: chrome is Bootstrap (.card/.list-group + utilities); the only
 * styles set here are the panel's width / max-height / stacking, which have no Bootstrap utility.
 *
 * The message module is the first caller (the bell), but nothing here names it: a second module that
 * declares a `flyout` gets the same panel for free.
 */
(function (w, d) {
    'use strict';

    var t = (w.Tiger && Tiger.t) ? Tiger.t : function (k) { return k; };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /** A message-service envelope over /api: POST module/service/method + params, parse JSON, never throw. */
    function api(ep, params) {
        var body = new URLSearchParams({ module: ep.module, service: ep.service, method: ep.method });
        Object.keys(params || {}).forEach(function (k) { body.append(k, params[k]); });
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    function when(iso) {
        if (!iso) { return ''; }
        var dt = new Date(String(iso).replace(' ', 'T') + 'Z');
        return isNaN(dt) ? esc(iso) : esc(dt.toLocaleString());
    }

    /** Reflect an unread total on every header bell badge, so the panel and the bell agree without a reload. */
    function syncBadge(n) {
        if (typeof n !== 'number') { return; }
        d.querySelectorAll('.admin-header-badge').forEach(function (b) {
            b.textContent = n > 99 ? '99+' : String(n);
            b.classList.toggle('d-none', !(n > 0));
        });
    }

    function Flyout(toggle) {
        var wrap  = toggle.parentNode;
        var panel = wrap.querySelector('[data-tiger-flyout]');
        if (!panel) { return; }

        var cfg;
        try { cfg = JSON.parse(panel.getAttribute('data-tiger-flyout') || '{}'); }
        catch (e) { cfg = {}; }
        if (!cfg.endpoint) { return; }

        var L    = cfg.labels || {};
        var open = false;
        var body;                                       // the scrollable list host, created on first fill

        // Sizing the panel needs no Bootstrap utility, so it is set here rather than in a stylesheet.
        panel.style.display  = 'none';
        panel.style.width    = 'min(92vw, 24rem)';
        panel.style.zIndex   = '1050';
        panel.style.maxWidth = '92vw';

        function shell() {
            panel.innerHTML =
                '<div class="card-header d-flex justify-content-between align-items-center gap-2 py-2">'
              +   '<span class="fw-semibold">' + esc(L.title || '') + '</span>'
              + '</div>'
              + '<div class="list-group list-group-flush overflow-auto" data-flyout-body></div>'
              + '<div class="card-footer text-center py-2">'
              +   '<a href="' + esc(cfg.viewAll || '#') + '" class="text-decoration-none">' + esc(L.view_all || '') + '</a>'
              + '</div>';
            body = panel.querySelector('[data-flyout-body]');
            body.style.maxHeight = '60vh';              // scroll the list, keep header + footer fixed (no vh utility in Bootstrap)
        }

        function rowHtml(m) {
            var who = m.sender_id === null
                ? '<span class="badge text-bg-secondary">' + esc(L.system || '') + '</span>'
                : esc(m.sender_name || '');
            // "2-line preview": the server preview is trimmed to a two-line budget and allowed to wrap
            // (Bootstrap 5.3 ships no line-clamp utility, and the house rule is semantic Bootstrap only).
            var preview = String(m.preview || '');
            if (preview.length > 120) { preview = preview.slice(0, 119).replace(/\s+\S*$/, '') + '…'; }
            var unread = m.read === false;
            return '<div class="list-group-item d-flex justify-content-between align-items-start gap-2" data-flyout-row data-id="' + esc(m.message_id) + '">'
                 +   '<div class="flex-grow-1">'
                 +     '<div class="d-flex justify-content-between align-items-baseline gap-2">'
                 +       '<span class="' + (unread ? 'fw-semibold' : '') + '">'
                 +         (unread ? '<i class="fa-solid fa-circle text-primary me-2" aria-hidden="true"></i>' : '')
                 +         esc(m.subject || '')
                 +       '</span>'
                 +       '<span class="text-body-secondary text-nowrap">' + when(m.created_at) + '</span>'
                 +     '</div>'
                 +     '<div class="text-body-secondary">' + (m.sender_id === null ? who : (esc(L.from || '') + ' ' + who) + ' — ') + esc(preview) + '</div>'
                 +   '</div>'
                 +   (cfg.action
                        ? '<button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" data-flyout-archive'
                          + ' title="' + esc(L.archive || '') + '" aria-label="' + esc(L.archive || '') + '">'
                          + '<i class="fa-solid fa-box-archive" aria-hidden="true"></i></button>'
                        : '')
                 + '</div>';
        }

        function render(list) {
            if (!list.length) {
                body.innerHTML = '<div class="list-group-item text-body-secondary text-center py-4">' + esc(L.empty || '') + '</div>';
                return;
            }
            body.innerHTML = list.map(rowHtml).join('');
        }

        function fill() {
            body.innerHTML = '<div class="list-group-item text-center py-4"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i></div>';
            api(cfg.endpoint, {}).then(function (res) {
                if (!res || res.result !== 1 || !res.data) {
                    body.innerHTML = '<div class="list-group-item text-danger text-center py-4">' + esc(L.load_failed || '') + '</div>';
                    return;
                }
                render(res.data.messages || []);
                syncBadge(res.data.unread);
            });
        }

        function archive(btn) {
            var rowEl = btn.closest('[data-flyout-row]');
            if (!rowEl || !cfg.action) { return; }
            var id = rowEl.getAttribute('data-id');
            TigerButton.run(btn, function () {
                return api(cfg.action, { message_id: id });
            }).then(function (res) {
                if (res && res.result === 1) {
                    if (w.TigerDOM) { TigerDOM.dismiss(rowEl); } else { rowEl.remove(); }
                    // If that was the last one, show the empty state once the row is gone.
                    w.setTimeout(function () {
                        if (body && !body.querySelector('[data-flyout-row]')) { render([]); }
                    }, 320);
                } else if (w.TigerDOM) {
                    (res && res.messages || []).forEach(function (m) { TigerDOM.toast(m.message, { type: m.class }); });
                    if (!res || !res.messages || !res.messages.length) { TigerDOM.toast(L.action_failed || '', { type: 'error' }); }
                }
            }).catch(function () {
                if (w.TigerDOM) { TigerDOM.toast(L.action_failed || '', { type: 'error' }); }
            });
        }

        function show() {
            if (!panel.firstChild) { shell(); }
            open = true;
            toggle.setAttribute('aria-expanded', 'true');
            if (w.TigerDOM) { TigerDOM.expand(panel); } else { panel.style.display = ''; }
            fill();                                     // always fresh — messages arrive between opens
        }

        function hide() {
            open = false;
            toggle.setAttribute('aria-expanded', 'false');
            if (w.TigerDOM) { TigerDOM.collapse(panel); } else { panel.style.display = 'none'; }
        }

        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            open ? hide() : show();
        });

        panel.addEventListener('click', function (e) {
            var a = e.target.closest('[data-flyout-archive]');
            if (a) { e.preventDefault(); archive(a); }
        });

        // Dismiss on outside-click and Escape — the panel is not a Bootstrap dropdown, so we wire it.
        d.addEventListener('click', function (e) {
            if (open && !wrap.contains(e.target)) { hide(); }
        });
        d.addEventListener('keydown', function (e) {
            if (open && (e.key === 'Escape' || e.key === 'Esc')) { hide(); toggle.focus(); }
        });
    }

    function init() {
        d.querySelectorAll('[data-tiger-flyout-toggle]').forEach(function (tg) {
            if (tg.__tgFlyout) { return; }
            tg.__tgFlyout = true;
            Flyout(tg);
        });
    }

    if (d.readyState === 'loading') { d.addEventListener('DOMContentLoaded', init); }
    else { init(); }
})(window, document);
