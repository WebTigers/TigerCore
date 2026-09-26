/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger and WebTigers are trademarks of WebTigers.
 *
 * TigerCombobox — a searchable "pick OR type" control over an /api search service. The house primitive
 * for any field whose value is one of a discoverable set BUT may also be typed freely (a path, an id).
 *
 * Two backing fields, the classic combobox split:
 *   - a VISIBLE search <input> (what the user types / the chosen label), and
 *   - a HIDDEN value field (data-value-field="<id>") that holds the committed value the form submits.
 *
 * Contract (data-* on a wrapper carrying data-tg-combobox):
 *   data-service="module/service/method"   the /api op the search POSTs to (returns {groups:[{label,options:[{value,label}]}]})
 *   data-value-field="<hidden input id>"    where the committed value is written
 *   [data-tg-combobox-search]               the visible search input (else the first <input> in the wrapper)
 *   [data-tg-combobox-advanced]             an optional checkbox → sent as `advanced` (the "litterbox" toggle)
 *   [data-tg-combobox-panel]                the results panel (else auto-created; Bootstrap .dropdown-menu)
 *
 * Committing: clicking an option commits {value,label}; blurring/Enter with no pick commits the typed
 * text verbatim (so a free-typed path is kept, "right or wrong" — the server validates on save). The
 * wrapper fires a `tiger:combobox:change` event with {value,label} on every commit.
 *
 * Vanilla, zero-dep (fetch + Bootstrap dropdown classes). Auto-inits every [data-tg-combobox] on DOM ready.
 */
(function (window, document) {
    'use strict';

    var DEBOUNCE = 200;

    function TigerCombobox(root) {
        if (!root || root._tgCombobox) { return; }
        root._tgCombobox = true;

        var search   = root.querySelector('[data-tg-combobox-search]') || root.querySelector('input:not([type=checkbox]):not([type=hidden])');
        var valueEl  = document.getElementById(root.getAttribute('data-value-field') || '');
        var advEl    = root.querySelector('[data-tg-combobox-advanced]')
                       || document.getElementById(root.getAttribute('data-advanced-field') || '');
        var panel    = root.querySelector('[data-tg-combobox-panel]');
        var service  = (root.getAttribute('data-service') || '').split('/');
        if (!search || !valueEl || service.length < 3) { return; }

        if (!panel) {
            panel = document.createElement('div');
            panel.className = 'dropdown-menu w-100';
            panel.setAttribute('data-tg-combobox-panel', '');
            root.appendChild(panel);
        }
        panel.style.maxHeight = panel.style.maxHeight || '20rem';
        panel.style.overflowY = 'auto';
        panel.style.top = '100%'; panel.style.left = '0';   // pin under the search input
        if (getComputedStyle(root).position === 'static') { root.style.position = 'relative'; }

        // The committed selection whose LABEL currently fills the search box (so re-blurring an
        // untouched pick keeps its real value instead of committing the label text).
        var picked = { value: valueEl.value, label: search.value };
        var timer = null, active = -1, options = [];

        function open()  { panel.classList.add('show'); search.setAttribute('aria-expanded', 'true'); }
        function close() { panel.classList.remove('show'); search.setAttribute('aria-expanded', 'false'); active = -1; }

        function commit(value, label) {
            picked = { value: value, label: label };
            valueEl.value = value;
            search.value  = label;
            root.dispatchEvent(new CustomEvent('tiger:combobox:change', { detail: { value: value, label: label }, bubbles: true }));
        }

        // Blur/Enter with no pick: keep the last pick if the box is unchanged, else take the typed text.
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
                    a.addEventListener('mousedown', function (e) { e.preventDefault(); commit(o.value, o.label); close(); });
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
            active = -1;
            open();
        }

        function fetchOptions() {
            var body = new URLSearchParams();
            body.set('module', service[0]); body.set('service', service[1]); body.set('method', service[2]);
            body.set('q', search.value.trim());
            body.set('advanced', advEl && advEl.checked ? '1' : '0');
            fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json().catch(function () { return {}; }); })
                .then(function (res) { if (res && res.result === 1 && res.data) { render(res.data.groups); } })
                .catch(function () { /* leave the last list; free-typing still works */ });
        }

        function schedule() { if (timer) { clearTimeout(timer); } timer = setTimeout(fetchOptions, DEBOUNCE); }

        function highlight(next) {
            if (!options.length) { return; }
            if (active >= 0 && options[active]) { options[active].classList.remove('active'); }
            active = (next + options.length) % options.length;
            options[active].classList.add('active');
            options[active].scrollIntoView({ block: 'nearest' });
        }

        search.addEventListener('focus', fetchOptions);
        search.addEventListener('input', schedule);
        if (advEl) { advEl.addEventListener('change', fetchOptions); }

        search.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (!panel.classList.contains('show')) { fetchOptions(); } else { highlight(active + 1); } }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
            else if (e.key === 'Enter')  { if (active >= 0 && options[active]) { e.preventDefault(); options[active].dispatchEvent(new MouseEvent('mousedown')); } else { commitTyped(); close(); } }
            else if (e.key === 'Escape') { close(); }
        });

        search.addEventListener('blur', function () { setTimeout(function () { commitTyped(); close(); }, 150); });
    }

    function initAll(scope) {
        (scope || document).querySelectorAll('[data-tg-combobox]').forEach(function (el) { new TigerCombobox(el); });
    }

    window.TigerCombobox = { init: initAll, create: function (el) { return new TigerCombobox(el); } };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(); });
    } else {
        initAll();
    }
})(window, document);
