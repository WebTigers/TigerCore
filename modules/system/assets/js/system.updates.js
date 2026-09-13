// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * system.updates.js — the Updates screen (TIGER-113).
 *
 * Applying goes through /api (System_Service_Updates::apply); this only draws. Each result settles
 * its row into a state a person can read at a glance:
 *
 *   DONE     checkbox → green check, Update button removed, row dimmed, version line shows the
 *            version now installed as CURRENT. Nothing on the row is actionable any more.
 *   FAILED   red mark and status; checkbox and button STAY so it can be retried. A failed update
 *            that looked untouched was the old behaviour, and it is worse than a visible failure
 *            because the operator cannot tell the two apart.
 *   MANUAL   an advisory (a core update this host cannot apply itself). Not marked done — it is not.
 *
 * When the last pending row is done, the list gives way to the all-clear card without a reload, and
 * the sidebar's Updates badge is corrected in place.
 */
(function () {
    'use strict';

    var list = document.getElementById('updates-list');
    var fb   = document.getElementById('updates-feedback');
    if (!fb) { return; }

    var logCard = document.getElementById('updates-log-card');
    var logEl   = document.getElementById('updates-log');
    var t       = (window.Tiger && Tiger.t) ? Tiger.t : function (k) { return k; };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function post(method, body) {
        var fd = new URLSearchParams(body || {});
        fd.set('module', 'system'); fd.set('service', 'updates'); fd.set('method', method);
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    function log(line) { logEl.textContent += line + "\n"; logEl.scrollTop = logEl.scrollHeight; }

    function rowFor(slug) {
        return list ? list.querySelector('li[data-slug="' + (window.CSS && CSS.escape ? CSS.escape(slug) : slug) + '"]') : null;
    }

    /* ---- row states ---------------------------------------------------------------------- */

    function settleDone(li, version) {
        li.classList.add('update-done', 'opacity-50');
        li.setAttribute('aria-disabled', 'true');

        // The checkbox becomes a green check: there is no longer anything to select.
        var mark = li.querySelector('.update-mark');
        if (mark) { mark.innerHTML = '<i class="fa-solid fa-circle-check text-success fa-lg" aria-hidden="true"></i>'; }

        // The Update button is REMOVED, not disabled — a disabled button still reads as "something to do".
        var btn = li.querySelector('.update-one');
        if (btn) { btn.remove(); }

        // The version line now shows what is installed as current; the arrow and target are gone.
        var installed = li.querySelector('.update-installed');
        var arrow     = li.querySelector('.update-arrow');
        var latest    = li.querySelector('.update-latest');
        if (installed && version) { installed.textContent = version; installed.classList.add('fw-semibold'); }
        else if (installed && latest) { installed.textContent = latest.textContent; installed.classList.add('fw-semibold'); }
        if (arrow)  { arrow.remove(); }
        if (latest) { latest.remove(); }

        var status = li.querySelector('.update-status');
        if (status) { status.innerHTML = '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>' + esc(t('stateDone')) + ' · ' + esc(t('stateCurrent')) + '</span>'; }
    }

    function settleFailed(li) {
        li.classList.add('update-failed');
        var mark = li.querySelector('.update-mark input');
        if (mark) { mark.checked = false; }          // not selected for a blind retry; the operator chooses
        var status = li.querySelector('.update-status');
        if (status) { status.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-xmark me-1"></i>' + esc(t('stateFailed')) + '</span>'; }
    }

    function settleManual(li) {
        var status = li.querySelector('.update-status');
        if (status) { status.innerHTML = '<span class="text-warning-emphasis"><i class="fa-solid fa-circle-info me-1"></i>' + esc(t('stateManual')) + '</span>'; }
    }

    /** Rows that still need doing: not done, and not a manual advisory. */
    function remaining() {
        return list ? list.querySelectorAll('li:not(.update-done)').length : 0;
    }

    function maybeAllClear() {
        if (!list || remaining() > 0) { return; }
        var pending = document.getElementById('updates-pending');
        var clear   = document.getElementById('updates-uptodate');
        if (pending) { pending.classList.add('d-none'); }
        if (clear)   { clear.classList.remove('d-none'); }
    }

    /** The sidebar badge is server-rendered from the last check; keep it honest without a reload. */
    function setSidebarBadge(n) {
        var li = document.querySelector('.tiger-nav li[data-key="modules_update"] > a');
        var parent = document.querySelector('.tiger-nav li[data-key="modules"] > a');
        [li, parent].forEach(function (a) {
            if (!a) { return; }
            var b = a.querySelector('.tiger-nav-badge');
            if (n > 0) {
                if (!b) { b = document.createElement('span'); b.className = 'badge rounded-pill text-bg-danger tiger-nav-badge tiger-nav-label'; a.insertBefore(b, a.querySelector('.tiger-nav-caret')); }
                b.textContent = n > 99 ? '99+' : String(n);
            } else if (b) { b.remove(); }
        });
    }

    /* ---- apply ----------------------------------------------------------------------------- */

    function render(data) {
        logCard.classList.remove('d-none');
        (data.results || []).forEach(function (item) {
            log((item.ok ? '✓' : '✗') + ' ' + item.name);
            (item.log || []).forEach(function (st) { log('     ' + (st.ok ? '·' : '✗') + ' [' + st.step + '] ' + st.detail); });

            var li = rowFor(item.slug);
            if (!li) { return; }
            if (item.advisory)   { settleManual(li); }
            else if (item.ok)    { settleDone(li, item.version); }
            else                 { settleFailed(li); }
        });
        setSidebarBadge(remaining());
        maybeAllClear();
    }

    function selectedSlugs() {
        return Array.prototype.filter.call(document.querySelectorAll('#updates-list li:not(.update-done)'), function (li) {
            var c = li.querySelector('.update-check'); return c && c.checked;
        }).map(function (li) { return li.getAttribute('data-slug'); });
    }

    function apply(slugs, btn) {
        if (!slugs.length) { TigerDOM.notify(fb, t('selectUpdate'), { type: 'alert' }); return; }
        logEl.textContent = '';
        TigerButton.run(btn, function () { return post('apply', { items: slugs.join(',') }); })
            .then(function (res) {
                if (res && res.result === 1) {
                    render(res.data);
                    var anyFail = (res.data.results || []).some(function (r) { return !r.ok; });
                    TigerDOM.notify(fb, anyFail ? t('updatesAttention') : t('updatesApplied'), { type: anyFail ? 'alert' : 'success' });
                } else {
                    (res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
                }
            })
            .catch(function () { TigerDOM.notify(fb, t('networkError'), { type: 'error' }); });
    }

    var all = document.getElementById('updates-all');
    if (all) { all.addEventListener('click', function () { apply(selectedSlugs(), this); }); }

    var selAll = document.getElementById('updates-select-all');
    if (selAll) {
        selAll.addEventListener('change', function () {
            Array.prototype.forEach.call(document.querySelectorAll('#updates-list li:not(.update-done) .update-check'), function (c) { c.checked = selAll.checked; });
        });
    }

    if (list) {
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('.update-one');
            if (btn) { apply([btn.closest('li').getAttribute('data-slug')], btn); }
        });
    }

    var refresh = document.getElementById('updates-refresh');
    if (refresh) {
        refresh.addEventListener('click', function () {
            TigerButton.run(this, function () { return post('check', { refresh: 1 }); })
                .then(function (res) { if (res && res.result === 1) { window.location.reload(); } })
                .catch(function () { TigerDOM.notify(fb, t('networkErrorShort'), { type: 'error' }); });
        });
    }
})();
