// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.admin-index.js
 *
 * Admin dashboard — a Muuri masonry grid of module-registered widgets (Tiger_Dashboard). The
 * shell owns the card chrome (drag handle, collapse, sizing) and the layout; each widget owns
 * only its body.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
(function () {
    var el = document.getElementById('dash-grid');
    if (!el) { return; }

    // The grid starts at opacity 0 (CSS), so the browser never paints the pre-layout stack of
    // absolutely-positioned tiles (the FOUC). We fade it in only once Muuri has arranged them.
    // reveal() is idempotent; a 1s safety net guarantees the widgets show even if Muuri fails
    // to load or init throws before the first layoutEnd.
    var revealed = false;
    function reveal() { if (revealed) { return; } revealed = true; el.classList.add('dash-ready'); }
    setTimeout(reveal, 1000);
    if (!window.Muuri) { reveal(); return; }

    var grid = new Muuri(el, {
        items: '.dash-item:not(.d-none)',               // hidden widgets stay in the DOM but out of the grid
        dragEnabled: true,
        dragHandle: '.dash-handle',                 // the whole card header is the handle
        layout: { fillGaps: false, rounding: true },
        // Don't start a drag when the click is on the collapse toggle inside the header.
        dragStartPredicate: function (item, e) {
            if (e.target && e.target.closest && e.target.closest('.dash-collapse')) { return false; }
            return Muuri.ItemDrag.defaultStartPredicate(item, e);
        }
    });

    function debounce(fn, ms) { var t; return function () { clearTimeout(t); t = setTimeout(fn, ms); }; }
    window.addEventListener('resize', debounce(function () { grid.refreshItems().layout(); }, 150));

    // Seed each body's open/closed state for TigerDOM (server renders collapsed ones display:none).
    el.querySelectorAll('.dash-body').forEach(function (body) { body.__tgOpen = (body.style.display !== 'none'); });

    function persist() {
        var order = grid.getItems().map(function (it) { return it.getElement().getAttribute('data-id'); });
        var collapsed = {};
        el.querySelectorAll('.dash-item').forEach(function (it) {
            var body = it.querySelector('.dash-body');
            if (body && body.__tgOpen === false) { collapsed[it.getAttribute('data-id')] = true; }
        });
        var fd = new URLSearchParams();
        fd.set('module', 'system'); fd.set('service', 'dashboard'); fd.set('method', 'saveLayout');
        fd.set('layout', JSON.stringify({ order: order, collapsed: collapsed }));
        fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).catch(function () {});
    }

    grid.on('dragReleaseEnd', persist);

    // Collapse / expand a widget — animated via TigerDOM (the house reveal primitive), repacking the
    // Muuri grid each frame so the masonry reflows smoothly as the card resizes.
    el.addEventListener('click', function (e) {
        var btn = e.target.closest('.dash-collapse');
        if (!btn) { return; }
        var item   = btn.closest('.dash-item');
        var body   = item.querySelector('.dash-body');
        var icon   = btn.querySelector('i');
        var mItems = grid.getItems(item);
        var willOpen = !body.__tgOpen;
        icon.className = 'fa-solid fa-chevron-' + (willOpen ? 'up' : 'down');

        if (!window.TigerDOM) {                              // graceful fallback (no animation)
            body.style.display = willOpen ? '' : 'none'; body.__tgOpen = willOpen;
            if (mItems.length) { grid.refreshItems(mItems).layout(); }
            persist(); return;
        }
        var animating = true;
        (function tick() { if (!animating) { return; } if (mItems.length) { grid.refreshItems(mItems).layout(); } requestAnimationFrame(tick); })();
        TigerDOM.toggle(body).then(function () {
            animating = false;
            if (mItems.length) { grid.refreshItems(mItems).layout(); }
            persist();
        });
    });

    // --- Customize: per-user widget visibility (WP "Screen Options" style) ------------------------
    var custBtn = document.getElementById('dash-customize');
    var modalEl = document.getElementById('dash-customize-modal');
    var modal   = null;   // created lazily on first click — Bootstrap's JS loads in the layout
                          // footer, AFTER this inline script, so window.bootstrap isn't defined yet.

    function itemById(id) {
        var found = null;
        el.querySelectorAll('.dash-item').forEach(function (it) { if (it.getAttribute('data-id') === id) { found = it; } });
        return found;
    }
    function itemTitle(it) {
        var s = it.querySelector('.dash-handle .text-truncate');
        return s ? s.textContent : it.getAttribute('data-id');
    }
    function hiddenIds() {
        var ids = [];
        el.querySelectorAll('.dash-item.d-none').forEach(function (it) { ids.push(it.getAttribute('data-id')); });
        return ids;
    }
    function persistPrefs() {
        var fd = new URLSearchParams();
        fd.set('module', 'system'); fd.set('service', 'dashboard'); fd.set('method', 'saveWidgetPrefs');
        fd.set('hidden', JSON.stringify(hiddenIds()));
        fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).catch(function () {});
    }

    // Build the on/off list from EVERY widget in the DOM (visible + the hidden d-none shells).
    function buildPrefs() {
        var list = document.getElementById('dash-widget-prefs');
        if (!list) { return; }
        list.innerHTML = '';
        el.querySelectorAll('.dash-item').forEach(function (it) {
            var on  = !it.classList.contains('d-none');
            var row = document.createElement('label');
            row.className = 'list-group-item d-flex align-items-center gap-3';
            row.innerHTML = '<span class="form-check form-switch m-0"><input class="form-check-input dash-pref" type="checkbox" role="switch"' + (on ? ' checked' : '') + '></span>' +
                            '<span class="flex-grow-1 text-truncate"></span>';
            row.querySelector('.dash-pref').setAttribute('data-id', it.getAttribute('data-id'));
            row.querySelector('.text-truncate').textContent = itemTitle(it);
            list.appendChild(row);
        });
    }

    function showWidget(it) {
        it.classList.remove('d-none');
        var body = it.querySelector('.dash-body');
        var addToGrid = function () {
            if (!grid.getItems(it).length) { grid.add(it, { layout: true }); }
            body.__tgOpen = (body.style.display !== 'none');
            grid.refreshItems(grid.getItems(it)).layout();
        };
        if (body && body.getAttribute('data-lazy') === '1') {   // never rendered on load → fetch it now
            body.innerHTML = '<div class="text-center text-body-secondary py-3"><span class="spinner-border spinner-border-sm"></span></div>';
            var fd = new URLSearchParams();
            fd.set('module', 'system'); fd.set('service', 'dashboard'); fd.set('method', 'widgetBody');
            fd.set('id', it.getAttribute('data-id'));
            fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    body.innerHTML = (res && res.result === 1 && res.data && res.data.html) ? res.data.html : '<p class="text-body-secondary small mb-0">' + Tiger.t('widgetLoadError') + '</p>';
                    body.removeAttribute('data-lazy');
                    addToGrid();
                })
                .catch(function () { body.innerHTML = '<p class="text-body-secondary small mb-0">' + Tiger.t('widgetLoadError') + '</p>'; body.removeAttribute('data-lazy'); addToGrid(); });
        } else {
            addToGrid();
        }
    }
    function hideWidget(it) {
        var mItems = grid.getItems(it);
        if (mItems.length) { grid.remove(mItems, { removeElements: false }); }
        it.classList.add('d-none');
        grid.layout();
    }

    if (custBtn && modalEl) {
        custBtn.addEventListener('click', function () {
            if (!modal && window.bootstrap) { modal = new bootstrap.Modal(modalEl); }
            buildPrefs();
            if (modal) { modal.show(); }
        });
    }
    if (modalEl) {
        modalEl.addEventListener('change', function (e) {
            var cb = e.target.closest('.dash-pref');
            if (!cb) { return; }
            var it = itemById(cb.getAttribute('data-id'));
            if (!it) { return; }
            if (cb.checked) { showWidget(it); } else { hideWidget(it); }
            persistPrefs();
        });
    }

    // Fade the grid in once the first layout settles (see reveal() above) — kills the FOUC.
    grid.on('layoutEnd', function onFirstLayout() { grid.off('layoutEnd', onFirstLayout); reveal(); });
    grid.layout();
})();
