/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger and WebTigers are trademarks of WebTigers.
 *
 * TigerPathbox — a searchable "pick OR type" control over an /api search service, for a field whose
 * value is one of a discoverable set BUT may also be typed freely (a path, an id). Distinct from
 * TigerCombo (tiger.combobox.js), which enhances a CLOSED <select> and never accepts a free value —
 * this one is OPEN (a typed value that matches nothing is kept, "right or wrong", validated on save).
 *
 * Two backing fields, the classic combobox split:
 *   - a VISIBLE search <input> (what the user types / the chosen label), and
 *   - a HIDDEN value field (data-value-field="<id>") that holds the committed value the form submits.
 *
 * Wrapper carries data-tg-pathbox plus:
 *   data-service="module/service/method"   the /api op the search POSTs to (returns {groups:[{label,options:[{value,label}]}]})
 *   data-value-field="<hidden input id>"    where the committed value is written
 *   [data-tg-pathbox-search]                the visible search input (else the first non-checkbox input)
 *   [data-advanced-field="<checkbox id>"]   an optional checkbox → sent as `advanced` (the "litterbox" toggle)
 *   [data-tg-pathbox-panel]                 the results panel (else auto-created; Bootstrap .dropdown-menu)
 *
 * Committing: clicking an option commits {value,label}; blurring/Enter/flush() with no pick commits the
 * typed text verbatim. `el._tgPathbox.flush()` (and TigerPathbox.flushAll(scope)) commit SYNCHRONOUSLY —
 * a form's Save handler must call flushAll BEFORE it serialises FormData, so a value typed but not yet
 * blurred is captured on the first click. The wrapper fires `tiger:pathbox:change` {value,label} on commit.
 *
 * Stale-response safety: every fetch carries a generation stamp; a response renders only if it is the
 * newest request AND the control is still open — so a slow/older response can neither overwrite newer
 * results nor reopen a control that was dismissed or committed. Vanilla, zero-dep (fetch + Bootstrap).
 */
(function (window, document) {
    'use strict';

    var DEBOUNCE = 200;

    function TigerPathbox(root) {
        if (!root || root._tgPathbox) { return root && root._tgPathbox; }

        var search  = root.querySelector('[data-tg-pathbox-search]') || root.querySelector('input:not([type=checkbox]):not([type=hidden])');
        var valueEl = document.getElementById(root.getAttribute('data-value-field') || '');
        var advEl   = root.querySelector('[data-tg-pathbox-advanced]')
                      || document.getElementById(root.getAttribute('data-advanced-field') || '');
        var panel   = root.querySelector('[data-tg-pathbox-panel]');
        var service = (root.getAttribute('data-service') || '').split('/');
        if (!search || !valueEl || service.length < 3) { return; }

        if (!panel) {
            panel = document.createElement('div');
            panel.className = 'dropdown-menu w-100';
            panel.setAttribute('data-tg-pathbox-panel', '');
            root.appendChild(panel);
        }
        panel.style.maxHeight = panel.style.maxHeight || '20rem';
        panel.style.overflowY = 'auto';
        panel.style.top = '100%'; panel.style.left = '0';
        if (getComputedStyle(root).position === 'static') { root.style.position = 'relative'; }

        var picked = { value: valueEl.value, label: search.value };
        var timer = null, activeIdx = -1, options = [];
        var gen = 0;        // request generation — invalidates older/dismissed fetches
        var open = false;   // open intent — a late response never reopens a closed control

        function openPanel()  { open = true;  panel.classList.add('show'); search.setAttribute('aria-expanded', 'true'); }
        function closePanel() { open = false; gen++; panel.classList.remove('show'); search.setAttribute('aria-expanded', 'false'); activeIdx = -1; }

        function commit(value, label) {
            picked = { value: value, label: label };
            valueEl.value = value;
            search.value  = label;
            root.dispatchEvent(new CustomEvent('tiger:pathbox:change', { detail: { value: value, label: label }, bubbles: true }));
        }

        // Blur/Enter/flush with no pick: keep the last pick if the box is unchanged, else take the text.
        function commitTyped() {
            var typed = search.value.trim();
            if (typed === picked.label) { valueEl.value = picked.value; return; }
            commit(typed, typed);
        }

        function render(groups) {
            panel.innerHTML = '';
            options = [];
            (groups || []).forEach(function (g) {
                if (g.label) {
                    var h = document.createElement('h6');
                    h.className = 'dropdown-header';
                    h.textContent = g.label;
                    panel.appendChild(h);
                }
                (g.options || []).forEach(function (o) {
                    var a = document.createElement('button');
                    a.type = 'button';
                    a.className = 'dropdown-item text-wrap';
                    a.textContent = o.label;
                    a.setAttribute('role', 'option');
                    a.addEventListener('mousedown', function (e) { e.preventDefault(); commit(o.value, o.label); closePanel(); });
                    panel.appendChild(a);
                    options.push(a);
                });
            });
            if (!options.length) {
                var none = document.createElement('span');
                none.className = 'dropdown-item-text text-body-secondary small';
                none.textContent = search.getAttribute('data-empty-text') || 'No matches — type a path.';
                panel.appendChild(none);
            }
            activeIdx = -1;
            openPanel();
        }

        function fetchOptions() {
            var myGen = ++gen;
            open = true;   // a fetch expresses open intent (focus/typing/toggle)
            var body = new URLSearchParams();
            body.set('module', service[0]); body.set('service', service[1]); body.set('method', service[2]);
            body.set('q', search.value.trim());
            body.set('advanced', advEl && advEl.checked ? '1' : '0');
            fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json().catch(function () { return {}; }); })
                .then(function (res) {
                    if (myGen !== gen || !open) { return; }   // superseded by a newer request, or dismissed
                    if (res && res.result === 1 && res.data) { render(res.data.groups); }
                })
                .catch(function () { /* leave the last list; free-typing still works */ });
        }

        function schedule() { if (timer) { clearTimeout(timer); } timer = setTimeout(fetchOptions, DEBOUNCE); }

        function highlight(next) {
            if (!options.length) { return; }
            if (activeIdx >= 0 && options[activeIdx]) { options[activeIdx].classList.remove('active'); }
            activeIdx = (next + options.length) % options.length;
            options[activeIdx].classList.add('active');
            options[activeIdx].scrollIntoView({ block: 'nearest' });
        }

        search.addEventListener('focus', fetchOptions);
        search.addEventListener('input', schedule);
        if (advEl) { advEl.addEventListener('change', fetchOptions); }

        search.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (!panel.classList.contains('show')) { fetchOptions(); } else { highlight(activeIdx + 1); } }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(activeIdx - 1); }
            else if (e.key === 'Enter')  { if (activeIdx >= 0 && options[activeIdx]) { e.preventDefault(); options[activeIdx].dispatchEvent(new MouseEvent('mousedown')); } else { commitTyped(); closePanel(); } }
            else if (e.key === 'Escape') { closePanel(); }
        });

        search.addEventListener('blur', function () { setTimeout(function () { commitTyped(); closePanel(); }, 150); });

        var api = { flush: function () { commitTyped(); } };
        root._tgPathbox = api;
        return api;
    }

    function initAll(scope) {
        (scope || document).querySelectorAll('[data-tg-pathbox]').forEach(function (el) { TigerPathbox(el); });
    }

    window.TigerPathbox = {
        init: initAll,
        create: function (el) { return TigerPathbox(el); },
        flushAll: function (scope) {
            (scope || document).querySelectorAll('[data-tg-pathbox]').forEach(function (el) {
                if (el._tgPathbox) { el._tgPathbox.flush(); }
            });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(); });
    } else {
        initAll();
    }
})(window, document);
