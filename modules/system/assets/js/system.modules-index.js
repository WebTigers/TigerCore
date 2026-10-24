// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * system.modules-index.js
 *
 * Modules admin — the installed-modules list (the WordPress "Plugins" screen for Tiger).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var esc = tigerDataTable.esc;

    /* ---- type filter: the active pill is set server-side from the cookie; a click re-filters + re-fetches ---- */
    var filterEl   = document.getElementById('mods-type-filter');
    var activeEl   = filterEl ? filterEl.querySelector('.nav-link.active') : null;
    var activeType = activeEl ? (activeEl.getAttribute('data-type') || '') : '';

    /* Clamp a 2-line description (full text in the cell title). */
    var CLAMP = 'display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;';

    var table = tigerDataTable('#mods-table', {
        service: { module: 'system', service: 'modules', action: 'datatable' },
        order: [[0, 'asc']],
        extraData: function () { return { type: activeType }; },
        /* Keep the filter pill counts in step with the catalog after an activate / delete, with no page
           reload — the service returns the full-catalog counts on every fetch. */
        drawCallback: function () {
            var json = this.api().ajax.json();
            var cts  = (json && json.counts) || null;
            if (!cts || !filterEl) { return; }
            var all = 0;
            Object.keys(cts).forEach(function (k) { all += cts[k]; });
            filterEl.querySelectorAll('[data-type]').forEach(function (pill) {
                var t = pill.getAttribute('data-type');
                var badge = pill.querySelector('.badge');
                if (badge) { badge.textContent = (t === '') ? all : (cts[t] || 0); }
            });
        },
        columns: [
            /* Module — name + /slug (+ lock), description, author + license, compat notice. */
            { data: 'name', render: function (t, type, row) {
                if (type !== 'display') { return t; }
                var html = '<div class="fw-semibold">' + esc(row.name) +
                    ' <code class="text-body-secondary fw-normal ms-1">/' + esc(row.slug) + '</code>';
                if (row.protected) { html += ' <i class="fa-solid fa-lock text-body-secondary ms-1" title="' + esc(Tiger.t('protectedTitle')) + '"></i>'; }
                html += '</div>';
                if (row.description) { html += '<div class="small text-body-secondary" style="' + CLAMP + '" title="' + esc(row.description) + '">' + esc(row.description) + '</div>'; }
                if (row.author || row.license_short) {
                    html += '<div class="small text-body-secondary">';
                    if (row.author)        { html += '<span>' + esc(row.author) + '</span>'; }
                    if (row.license_short) { html += '<span class="ms-2 badge text-bg-light" title="' + esc(row.license) + '">' + esc(row.license_short) + '</span>'; }
                    html += '</div>';
                }
                if (row.compat_message) { html += '<div class="small text-warning-emphasis mt-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>' + esc(row.compat_message) + '</div>'; }
                return html;
            } },
            /* Type badge. */
            { data: 'type_label', orderable: false, render: function (t, type, row) {
                if (type !== 'display') { return t; }
                return '<span class="badge text-bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle"><i class="fa-solid ' + esc(row.type_icon) + ' me-1"></i>' + esc(row.type_label) + '</span>';
            } },
            /* Version. */
            { data: 'version', orderable: false, render: function (t, type) {
                if (type !== 'display') { return t; }
                return t ? '<span class="text-body-secondary">' + esc(t) + '</span>' : '<span class="text-body-secondary">—</span>';
            } },
            /* Source badge. */
            { data: 'source', orderable: false, render: function (t, type) {
                if (type !== 'display') { return t; }
                var bundled = (t === 'bundled');
                return '<span class="badge text-bg-' + (bundled ? 'secondary' : 'info') + '">' + esc(bundled ? Tiger.t('sourceBundled') : Tiger.t('sourceAddon')) + '</span>';
            } },
            /* Status — active/inactive (+ default-theme star). */
            { data: 'active', render: function (t, type, row) {
                if (type !== 'display') { return row.active ? 1 : 0; }
                var html = row.active
                    ? '<span class="badge text-bg-success">' + esc(Tiger.t('statusActive')) + '</span>'
                    : '<span class="badge text-bg-secondary">' + esc(Tiger.t('statusInactive')) + '</span>';
                if (row.is_default) { html += ' <span class="badge text-bg-primary ms-1" title="' + esc(Tiger.t('defaultThemeTitle')) + '"><i class="fa-solid fa-star me-1"></i>' + esc(Tiger.t('defaultTheme')) + '</span>'; }
                return html;
            } },
            /* Actions — always-on | activate/deactivate | delete, off per-row flags. */
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                if (type !== 'display') { return ''; }
                var html = '';
                if (row.protected) {
                    html += '<span class="text-body-secondary small">' + esc(Tiger.t('alwaysOn')) + '</span>';
                } else if (row.active) {
                    html += '<button type="button" class="btn btn-sm btn-outline-warning" data-mod-toggle="deactivate"' +
                        ' data-slug="' + esc(row.slug) + '" data-theme="' + (row.is_theme ? 1 : 0) + '"' +
                        ' data-required-by="' + esc((row.required_by || []).join(',')) + '">' +
                        '<i class="fa-solid fa-pause me-1"></i>' + esc(Tiger.t('deactivate')) + '</button>';
                } else {
                    html += '<button type="button" class="btn btn-sm btn-outline-success" data-mod-toggle="activate"' +
                        ' data-slug="' + esc(row.slug) + '" data-theme="' + (row.is_theme ? 1 : 0) + '"' +
                        ' data-name="' + esc(row.name) + '" data-default-exists="' + (row.default_exists ? 1 : 0) + '">' +
                        '<i class="fa-solid fa-play me-1"></i>' + esc(Tiger.t('activate')) + '</button>';
                }
                if (row.can_delete) {
                    html += ' <button type="button" class="btn btn-sm btn-outline-danger ms-1" data-mod-delete' +
                        ' data-slug="' + esc(row.slug) + '" data-token="' + esc(row.token) + '" data-name="' + esc(row.name) + '"' +
                        ' data-deps="' + esc(row.dep_tokens) + '" title="' + esc(Tiger.t('deleteTitle')) + '"><i class="fa-solid fa-trash-can"></i></button>';
                }
                // WordPress-style per-module auto-update toggle (off by default); hidden for bundled/core.
                if (row.can_autoupdate) {
                    html += ' <span class="form-check form-switch d-inline-block align-middle ms-2 mb-0"' +
                        ' title="' + esc(Tiger.t('autoUpdateTitle')) + '">' +
                        '<input class="form-check-input" type="checkbox" role="switch" data-mod-autoupdate' +
                        ' data-slug="' + esc(row.slug) + '" data-name="' + esc(row.name) + '"' +
                        ' aria-label="' + esc(Tiger.t('autoUpdateAria', row.name)) + '"' + (row.auto_update ? ' checked' : '') + '>' +
                        '</span>';
                }
                return html;
            } }
        ]
    });

    if (filterEl) {
        filterEl.querySelectorAll('[data-type]').forEach(function (pill) {
            pill.addEventListener('click', function (e) {
                e.preventDefault();
                activeType = pill.getAttribute('data-type') || '';
                try { document.cookie = 'tiger_mods_type=' + encodeURIComponent(activeType) + '; path=/; max-age=31536000; samesite=Lax'; } catch (e2) {}
                filterEl.querySelectorAll('.nav-link').forEach(function (x) { x.classList.remove('active'); });
                pill.classList.add('active');
                table.ajax.reload();
            });
        });
    }

    /* ---- activate / deactivate (conflict-aware) — re-fetch the grid in place on success ---- */
    function handleToggle(btn) {
        var method = btn.getAttribute('data-mod-toggle'), slug = btn.getAttribute('data-slug');
        var isTheme = btn.getAttribute('data-theme') === '1';
        var requiredBy = (btn.getAttribute('data-required-by') || '').split(',').filter(Boolean);

        function send(extra) {
            var body = new URLSearchParams(Object.assign({ module: 'system', service: 'modules', method: method, slug: slug }, extra || {}));
            return TigerButton.run(btn, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                    .then(function (r) { return r.json(); });
            });
        }
        function run(extra) {
            send(extra).then(function (res) {
                if (res.result === 1) { table.ajax.reload(null, false); return; }
                if (res.data && res.data.conflicts && res.data.conflicts.length) {
                    var names = res.data.conflicts.map(function (c) { return '"' + (c.name || c.slug) + '"'; }).join(', ');
                    TigerModal.confirm({
                        title: Tiger.t('activateTitle'),
                        body: Tiger.t('confirmActivate', slug, names),
                        confirmLabel: Tiger.t('activateOk'),
                        variant: 'primary'
                    }).then(function (ok) {
                        if (!ok) { return; }
                        send(Object.assign({ confirm: '1' }, extra || {})).then(function (r2) {
                            if (r2.result === 1) { table.ajax.reload(null, false); return; }
                            (r2.messages || []).forEach(function (m) { TigerDOM.toast(m.message, { type: m.class || 'error' }); });
                        }).catch(function () {});
                    });
                    return;
                }
                (res.messages || []).forEach(function (m) { TigerDOM.toast(m.message, { type: m.class || 'error' }); });
            }).catch(function () {});
        }

        if (method === 'deactivate') {
            var body;
            if (requiredBy.length) {
                body = Tiger.t('deactivateRequired', requiredBy.join(', '));
            } else if (isTheme) {
                body = Tiger.t('deactivateTheme', slug);
            } else {
                body = Tiger.t('deactivateConfirm', slug);
            }
            TigerModal.confirm({
                title: Tiger.t('deactivateTitle'),
                body: body,
                confirmLabel: Tiger.t('deactivateOk'),
                variant: 'warning'
            }).then(function (ok) { if (ok) { run(); } });
            return;
        }

        /* Activating a THEME never deactivates another — multiple themes can be active at once. Offer a
           checkbox to also make this the DEFAULT site theme; pre-tick it when nothing is default yet. */
        if (isTheme) {
            var themeName    = btn.getAttribute('data-name') || slug;
            var noDefaultYet = btn.getAttribute('data-default-exists') === '0';
            TigerModal.confirm({
                title: Tiger.t('activateThemeTitle'),
                body: Tiger.t('activateThemeBody', themeName),
                confirmLabel: Tiger.t('activateOk'),
                variant: 'primary',
                checkbox: { label: Tiger.t('activateThemeMakeDefault', themeName), checked: noDefaultYet }
            }).then(function (res) {
                if (!res || !res.confirmed) { return; }
                run(res.checked ? { make_default: '1' } : {});
            });
            return;
        }
        run();
    }

    /* ---- delete (nuclear): dependents block → toast; else type-to-confirm DANGER modal ---- */
    var delEl    = document.getElementById('mod-delete-modal');
    var delModal = (delEl && window.bootstrap) ? bootstrap.Modal.getOrCreateInstance(delEl) : null;
    var delToken = document.getElementById('mod-delete-token');
    var delInput = document.getElementById('mod-delete-input');
    var delBtn   = document.getElementById('mod-delete-btn');
    var delFb    = document.getElementById('mod-delete-feedback');
    var delCtx   = { slug: '', token: '' };

    function handleDeleteTrigger(btn) {
        var deps = (btn.getAttribute('data-deps') || '').trim();
        if (deps) {                                  // a dependent would break — refuse, don't open the modal
            var many = deps.indexOf(',') !== -1;
            var msg = "'" + deps + "' depend" + (many ? '' : 's') + " on this module. Deactivate and/or delete "
                + (many ? 'them' : 'it') + " before you can delete this module.";
            if (window.TigerDOM) { TigerDOM.toast(msg, { type: 'error' }); } else { alert(msg); }
            return;
        }
        delCtx.slug  = btn.getAttribute('data-slug');
        delCtx.token = btn.getAttribute('data-token') || btn.getAttribute('data-name');
        if (delToken) { delToken.textContent = delCtx.token; }
        if (delInput) { delInput.value = ''; }
        if (delBtn)   { delBtn.disabled = true; }
        if (delFb)    { delFb.innerHTML = ''; }
        if (delModal) { delModal.show(); } else { alert(Tiger.t('deleteUnavailable')); }
    }

    /* One delegated listener handles both toggle + delete triggers across every grid redraw. */
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-mod-toggle]');
        if (toggle) { handleToggle(toggle); return; }
        var del = e.target.closest('[data-mod-delete]');
        if (del) { handleDeleteTrigger(del); return; }
    });

    /* Per-module auto-update switch (delegated — survives every grid redraw). Optimistic; reverts on failure. */
    document.addEventListener('change', function (e) {
        var sw = e.target.closest('[data-mod-autoupdate]');
        if (!sw) { return; }
        var slug = sw.getAttribute('data-slug'), on = sw.checked, prev = !on;
        sw.disabled = true;
        var body = new URLSearchParams({ module: 'system', service: 'modules', method: 'autoUpdate', slug: slug, on: on ? '1' : '0' });
        fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var ok = res && res.result === 1;
                if (!ok) { sw.checked = prev; }
                if (window.TigerDOM && TigerDOM.toast) {
                    TigerDOM.toast(Tiger.t(ok ? (on ? 'autoUpdateOn' : 'autoUpdateOff') : 'networkError'), { type: ok ? 'success' : 'error' });
                }
            })
            .catch(function () {
                sw.checked = prev;
                if (window.TigerDOM && TigerDOM.toast) { TigerDOM.toast(Tiger.t('networkError'), { type: 'error' }); }
            })
            .finally(function () { sw.disabled = false; });
    });

    if (delInput) {
        delInput.addEventListener('input', function () { if (delBtn) { delBtn.disabled = (delInput.value !== delCtx.token); } });
    }
    if (delBtn) {
        delBtn.addEventListener('click', function () {
            if (delInput && delInput.value !== delCtx.token) { return; }   // matches the server re-check
            var body = new URLSearchParams({ module: 'system', service: 'modules', method: 'delete', slug: delCtx.slug, confirm: delCtx.token });
            TigerButton.run(delBtn, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                    .then(function (r) { return r.json().catch(function () { return {}; }); });
            })
                .then(function (res) {
                    if (res && res.result === 1) { if (delModal) { delModal.hide(); } table.ajax.reload(null, false); return; }
                    var m = (res && res.messages && res.messages[0] && res.messages[0].message) || Tiger.t('deleteFailed');
                    if (window.TigerDOM && delFb) { TigerDOM.notify(delFb, m, { type: 'error' }); }
                    else if (delFb) { delFb.textContent = m; }
                })
                .catch(function () { if (delFb) { delFb.textContent = Tiger.t('networkError'); } });
        });
    }
});
