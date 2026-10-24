// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * cms.menu-edit.js
 *
 * CMS Menu builder — the two-pane drag-drop editor. Sources drag into a FLAT SortableJS list
 * whose rows carry a depth; parent/child is derived from order + depth at save time. Dropping
 * inserts, any drag persists order+nesting, the ⚙ opens the properties modal — all over /api
 * (Cms_Service_Menu).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
var dataEl = document.getElementById('menu-data');
var MENU_ITEMS = [];
try { MENU_ITEMS = JSON.parse(dataEl.getAttribute('data-items') || '[]'); } catch (e) {}

document.addEventListener('DOMContentLoaded', function () {
    var ORG = dataEl.getAttribute('data-org') || '';
    var INDENT = 28;                 // px of horizontal drag per nesting level
    var keyField = document.getElementById('menu-key-field');
    var root     = document.getElementById('menu-root');
    var form     = document.getElementById('menu-item-form');
    var modalEl  = document.getElementById('menuItemModal');
    var errBox   = document.getElementById('mi-errors');
    var editId   = null;
    var persistT = null;
    var drag     = null;             // { item, startX, startDepth } during a tree drag

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    function currentKey() { return (keyField.value || '').trim(); }
    function modal() { return bootstrap.Modal.getOrCreateInstance(modalEl); }
    function items() { return Array.prototype.filter.call(root.children, function (li) { return li.classList && li.classList.contains('menu-item'); }); }
    function depthOf(li) { return parseInt((li && li.dataset.depth) || '0', 10); }
    function setDepth(li, d) { li.dataset.depth = d; li.style.setProperty('--depth', d); }
    function prevItem(li) { var p = li.previousElementSibling; while (p && !p.classList.contains('menu-item')) { p = p.previousElementSibling; } return p; }

    // Editing a theme menu forks it server-side on the first change; the response carries an
    // {syntheticId: realId} map. Rewrite the DOM ids + MENU_ITEMS keys in place so the next
    // action targets the real rows — no reload, and the theme banner's items become editable rows.
    function applyIdMap(map) {
        if (!map) { return; }
        Object.keys(map).forEach(function (syn) {
            var real = map[syn];
            var li = root.querySelector('li.menu-item[data-menu-id="' + syn + '"]');
            if (li) { li.dataset.menuId = real; }
            if (MENU_ITEMS[syn]) { MENU_ITEMS[real] = MENU_ITEMS[syn]; delete MENU_ITEMS[syn]; }
            if (editId === syn) { editId = real; }
        });
    }
    function api(method, params) {
        var body = new URLSearchParams(params || {});
        body.set('module', 'cms'); body.set('service', 'menu'); body.set('method', method);
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (res) { if (res && res.data && res.data.idmap) { applyIdMap(res.data.idmap); } return res; });
    }
    function chipHTML(d) {
        var hint = d.page_key ? ('#' + d.page_key) : (d.url && d.url !== '#' ? d.url : (d.url === '#' ? '(set link)' : 'heading'));
        var icon = d.icon ? '<i class="' + esc(d.icon) + '"></i>' : '';
        var draft = d.status === 'draft' ? ' <span class="badge text-bg-secondary">Draft</span>' : '';
        return '<div class="menu-chip">'
            + '<span class="menu-grip" title="Drag to move · drag right/left to nest"><i class="fa-solid fa-grip-vertical"></i></span>' + icon
            + '<span class="menu-chip-label">' + esc(d.label || '(no label)') + '</span>'
            + ' <code class="menu-chip-hint">' + esc(hint) + '</code>' + draft
            + '<span class="menu-chip-actions ms-auto">'
            + '<button type="button" class="btn btn-sm btn-outline-secondary" data-open title="Properties" aria-label="Properties"><i class="fa-solid fa-sliders"></i></button> '
            + '<button type="button" class="btn btn-sm btn-outline-danger" data-del title="Remove" aria-label="Remove"><i class="fa-solid fa-trash"></i></button>'
            + '</span></div>';
    }
    function toggleEmpty() { document.getElementById('menu-empty').hidden = root.querySelector('li.menu-item') !== null; }

    // Flat order + per-row depth -> [{menu_id, parent_id, sort_order}], clamping illegal
    // depths (max = row-above depth + 1) and re-deriving parents from a depth stack.
    function computeTree() {
        var stack = [], out = [], prevDepth = 0;
        items().forEach(function (li, i) {
            var depth = depthOf(li);
            depth = (i === 0) ? 0 : Math.max(0, Math.min(depth, prevDepth + 1));
            setDepth(li, depth);
            var parent = depth > 0 ? (stack[depth - 1] || null) : null;
            stack[depth] = li.dataset.menuId; stack.length = depth + 1;
            out.push({ menu_id: li.dataset.menuId, parent_id: parent, sort_order: i });
            prevDepth = depth;
        });
        return out;
    }
    function schedulePersist() {
        clearTimeout(persistT);
        persistT = setTimeout(function () {
            toggleEmpty();
            if (!currentKey()) { return; }
            api('reorder', { menu_key: currentKey(), org_id: ORG, tree: JSON.stringify(computeTree()) });
        }, 150);
    }

    // ---- WP-style horizontal indent tracking during a drag ----
    function onDragMove(e) {
        if (!drag) { return; }
        var x = (e.touches && e.touches[0]) ? e.touches[0].clientX : e.clientX;
        if (x == null) { return; }
        if (drag.startX === null) { drag.startX = x; return; }
        var above = prevItem(drag.item);
        var maxDepth = above ? depthOf(above) + 1 : 0;           // at most one deeper than the row above
        var d = drag.startDepth + Math.round((x - drag.startX) / INDENT);
        setDepth(drag.item, Math.max(0, Math.min(d, maxDepth)));
    }

    // Turn a dropped palette clone into a real, persisted flat item.
    function createFromSource(el) {
        if (!currentKey()) { TigerDOM.toast(Tiger.t('nameMenuFirst'), { type: 'error' }); el.remove(); return; }
        var d = el.dataset;
        var above = prevItem(el);
        var depth = above ? depthOf(above) : 0;                  // land at the neighbor's level
        var params = { menu_key: currentKey(), org_id: ORG, label: d.label, status: 'published' };
        if (d.source === 'page') { params.page_key = d.pageKey; }
        else if (d.kind === 'url') { params.url = '#'; }
        api('save', params).then(function (res) {
            if (!res || res.result !== 1 || !res.data) { TigerDOM.toast(Tiger.t('addItemFailed'), { type: 'error' }); el.remove(); return; }
            var id = res.data.menu_id;
            el.classList.remove('menu-source');
            ['source', 'kind', 'page-key', 'label'].forEach(function (k) { el.removeAttribute('data-' + k); });
            el.dataset.menuId = id; setDepth(el, depth);
            var d2 = { label: params.label, page_key: params.page_key || '', url: params.url || '', icon: '', css_class: '', dom_id: '', link_target: '', link_rel: '', resource: '', privilege: '', status: 'published' };
            el.innerHTML = chipHTML(d2); MENU_ITEMS[id] = d2;
            schedulePersist();
            TigerDOM.toast(Tiger.t('itemAdded', esc(params.label || Tiger.t('item'))), { type: 'success' });
            if (d.source === 'custom') { openModal(id); }
        });
    }

    Sortable.create(root, {
        group: 'menu-items', handle: '.menu-grip', animation: 150,
        forceFallback: true, fallbackOnBody: true,
        ghostClass: 'menu-ghost', chosenClass: 'menu-chosen', fallbackClass: 'menu-fallback',
        onStart: function (evt) {
            drag = { item: evt.item, startX: null, startDepth: depthOf(evt.item) };
            document.addEventListener('mousemove', onDragMove, true);
            document.addEventListener('touchmove', onDragMove, true);
        },
        onEnd: function () {
            document.removeEventListener('mousemove', onDragMove, true);
            document.removeEventListener('touchmove', onDragMove, true);
            drag = null;
            schedulePersist();
        },
        onAdd: function (evt) { if (evt.item.classList.contains('menu-source')) { createFromSource(evt.item); } }
    });
    function initPalette(ul) {
        Sortable.create(ul, { group: { name: 'menu-items', pull: 'clone', put: false }, sort: false,
            handle: '.menu-grip', forceFallback: true, fallbackOnBody: true, fallbackClass: 'menu-fallback' });
    }

    // ---- Properties modal ----
    function setField(name, val) { var el = form.querySelector('[name="' + name + '"]'); if (el) { el.value = (val == null) ? '' : val; } }
    function openModal(id) {
        var d = MENU_ITEMS[id]; if (!d) { return; }
        editId = id; errBox.innerHTML = '';
        setField('menu_id', id); setField('menu_key', currentKey());
        ['label', 'page_key', 'url', 'icon', 'css_class', 'dom_id', 'link_target', 'link_rel', 'resource', 'privilege', 'status'].forEach(function (k) { setField(k, d[k]); });
        document.getElementById('menuItemModalTitle').textContent = 'Properties — ' + (d.label || 'item');
        modal().show();
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errBox.innerHTML = '';
        var btn = document.getElementById('mi-save');
        var params = {}; new FormData(form).forEach(function (v, k) { params[k] = v; });
        params.menu_key = currentKey();
        TigerButton.run(btn, function () {
            return api('save', params);
        }).then(function (res) {
            if (!res || res.result !== 1) {
                var msg = Tiger.t('saveFailed');
                if (res && res.form) { var f0 = Object.values(res.form)[0]; if (f0) { msg = Object.values(f0)[0] || msg; } }
                TigerDOM.notify(errBox, esc(msg), { type: 'error' });
                return;
            }
            var d = MENU_ITEMS[editId] || {};
            ['label', 'page_key', 'url', 'icon', 'css_class', 'dom_id', 'link_target', 'link_rel', 'resource', 'privilege', 'status'].forEach(function (k) { d[k] = params[k] || ''; });
            MENU_ITEMS[editId] = d;
            var li = root.querySelector('li.menu-item[data-menu-id="' + editId + '"]');
            if (li) { var chip = li.querySelector(':scope > .menu-chip'); if (chip) { chip.outerHTML = chipHTML(d); } }
            modal().hide();
            TigerDOM.toast(Tiger.t('itemSaved', esc(d.label || Tiger.t('item'))), { type: 'success' });
        }).catch(function () { TigerDOM.notify(errBox, Tiger.t('networkErrorShort'), { type: 'error' }); });
    });

    // Delegated open / delete (delete removes the row + its deeper-depth descendants).
    root.addEventListener('click', function (e) {
        var open = e.target.closest('[data-open]');
        var del  = e.target.closest('[data-del]');
        var li   = e.target.closest('li.menu-item');
        if (!li) { return; }
        if (open) { openModal(li.dataset.menuId); }
        else if (del) {
            TigerModal.confirm({ title: Tiger.t('removeItemTitle'), body: Tiger.t('removeItemBody'), confirmLabel: Tiger.t('removeLabel'), variant: 'danger' }).then(function (ok) {
                if (!ok) { return; }
                api('delete', { menu_id: li.dataset.menuId, menu_key: currentKey(), org_id: ORG }).then(function () {
                    var depth = depthOf(li), n = li.nextElementSibling;
                    li.remove();
                    while (n) { var nn = n.nextElementSibling; if (n.classList.contains('menu-item') && depthOf(n) > depth) { n.remove(); } else { break; } n = nn; }
                    schedulePersist(); TigerDOM.toast(Tiger.t('itemRemoved'), { type: 'success' });
                });
            });
        }
    });

    // Source filters.
    document.querySelectorAll('.menu-filter').forEach(function (inp) {
        inp.addEventListener('input', function () {
            var q = inp.value.toLowerCase();
            document.querySelectorAll(inp.getAttribute('data-filter') + ' .menu-source').forEach(function (li) {
                li.hidden = q !== '' && (li.getAttribute('data-label') || '').toLowerCase().indexOf(q) === -1;
            });
        });
    });

    initPalette(document.getElementById('palette-pages'));
    initPalette(document.getElementById('palette-custom'));
    toggleEmpty();
});
