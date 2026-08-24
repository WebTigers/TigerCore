/*! SPDX-License-Identifier: BSD-3-Clause · © 2026 WebTigers · Tiger™/WebTigers™ are trademarks */
/**
 * Social Cards screen (TigerSEO) — the client for /seo/admin.
 *
 * Lists the public VIEW PAGES (shipped .phtml marketing pages with no CMS row) fetched from
 * Seo_Service_Social::pages, and edits one page's Open Graph title / description / share image in a
 * modal that saves through Seo_Service_Social::save. Nothing here is server-rendered data — the view
 * ships the shell, this file fills it (AGENTS.md, the client/server section).
 *
 * A BLANK field is meaningful: it removes the override so the fallback applies again, which is why
 * every row shows what it will inherit when a field is left empty.
 *
 * Depends only on what the admin layout already loads: TigerButton, TigerDOM, Tiger.t, Bootstrap,
 * and TigerMediaPicker (auto-wired to the mediaField markup).
 */
(function (document) {
    'use strict';

    var MODULE = 'seo', SERVICE = 'social';

    var body, fb, modalEl, modal, form, modalFb, defaults = {}, rows = [];

    function t(key, fallback) {
        var s = (window.Tiger && Tiger.t) ? Tiger.t(key) : '';
        return (s && s !== key) ? s : fallback;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /** POST a Tiger message to /api and unwrap the JSON envelope (never throws on a bad body). */
    function api(method, extra) {
        var fd = new URLSearchParams(extra || {});
        fd.set('module', MODULE); fd.set('service', SERVICE); fd.set('method', method);
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    /** A short readout of an authored value, or the italic "using the default" note. */
    function cell(value) {
        if (value) { return '<span>' + esc(value) + '</span>'; }
        return '<span class="fst-italic text-body-secondary">' + esc(t('usingDefault', 'Site default')) + '</span>';
    }

    function imageCell(row) {
        if (!row.image) { return cell(''); }
        if (row.image_url) {
            return '<img src="' + esc(row.image_url) + '" alt="" class="rounded" style="width:56px;height:32px;object-fit:cover;">';
        }
        return '<span class="badge text-bg-secondary">' + esc(t('authored', 'Set')) + '</span>';
    }

    function render() {
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="6" class="text-body-secondary">' + esc(t('empty', 'No public pages found.')) + '</td></tr>';
            return;
        }
        var editLabel = body.getAttribute('data-seo-edit-label') || 'Edit';
        body.innerHTML = rows.map(function (row, i) {
            return '<tr>'
                + '<td class="fw-semibold">' + esc(row.key) + '</td>'
                + '<td><a href="' + esc(row.url) + '" target="_blank" rel="noopener">' + esc(row.url) + '</a></td>'
                + '<td>' + cell(row.title) + '</td>'
                + '<td>' + cell(row.description) + '</td>'
                + '<td>' + imageCell(row) + '</td>'
                + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-seo-edit="' + i + '">'
                + '<i class="fa-solid fa-pen me-1"></i>' + esc(editLabel) + '</button></td>'
                + '</tr>';
        }).join('');
    }

    function renderDefaults() {
        var map = {
            site_name: defaults.site_name,
            site_description: defaults.site_description,
            og_image: defaults.og_image
        };
        Object.keys(map).forEach(function (k) {
            var el = document.querySelector('[data-seo-default="' + k + '"]');
            if (!el) { return; }
            el.textContent = map[k] || '—';
        });
        var img = document.querySelector('[data-seo-default="og_image"]');
        if (img && defaults.og_image_url) {
            img.innerHTML = '<img src="' + esc(defaults.og_image_url) + '" alt="" class="rounded me-2" style="width:56px;height:32px;object-fit:cover;">'
                + '<span>' + esc(defaults.og_image) + '</span>';
        }
    }

    function load() {
        return api('pages', {}).then(function (res) {
            if (!res || res.result !== 1 || !res.data) {
                body.innerHTML = '<tr><td colspan="6" class="text-danger">' + esc(t('loadError', 'Could not load the page list.')) + '</td></tr>';
                return;
            }
            rows = res.data.pages || [];
            defaults = res.data.defaults || {};
            render();
            renderDefaults();
        }).catch(function () {
            body.innerHTML = '<tr><td colspan="6" class="text-danger">' + esc(t('networkError', 'Network error — please try again.')) + '</td></tr>';
        });
    }

    // --- the editor ---------------------------------------------------------------------------

    function field(name) { return form.querySelector('[name="' + name + '"]'); }

    /** Show what this page inherits when a field is left blank — the point of a blank field. */
    function showFallbacks() {
        var map = {
            title: defaults.site_name || '',
            description: defaults.site_description || '',
            image: defaults.og_image || ''
        };
        Object.keys(map).forEach(function (k) {
            var el = form.querySelector('[data-seo-fallback="' + k + '"]');
            if (el) { el.textContent = map[k] ? ('→ ' + map[k]) : ''; }
        });
    }

    function setImage(row) {
        var hidden = field('image_media_id');
        var urlEl  = field('image_url');
        var wrap   = hidden ? hidden.closest('[data-media-field]') : null;
        var prev   = wrap ? wrap.querySelector('[data-media-preview]') : null;
        var clear  = wrap ? wrap.querySelector('[data-media-clear]') : null;
        var isUrl  = /^https?:\/\//i.test(row.image || '');

        if (hidden) { hidden.value = isUrl ? '' : (row.image || ''); }
        if (urlEl)  { urlEl.value  = isUrl ? row.image : ''; }
        if (prev)   { prev.innerHTML = (!isUrl && row.image_url)
            ? '<img src="' + esc(row.image_url) + '" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:6px;">' : ''; }
        if (clear)  { clear.hidden = !(hidden && hidden.value); }
    }

    function open(row) {
        modalFb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        field('page_key').value   = row.key;
        field('title').value      = row.title || '';
        field('description').value = row.description || '';
        setImage(row);
        showFallbacks();

        var heading = document.getElementById('seo-page-modal-title');
        if (heading) { heading.textContent = t('editTitle', 'Social card') + ' — ' + row.url; }
        modal.show();
    }

    function save(btn) {
        modalFb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', MODULE); fd.set('service', SERVICE); fd.set('method', 'save');

        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) {
                modal.hide();
                TigerDOM.notify(fb, t('saved', 'Social card saved.'), { type: 'success' });
                load();
                return;
            }
            if (res && res.form) {
                Object.keys(res.form).forEach(function (name) {
                    var el = field(name);
                    if (el) { el.classList.add('is-invalid'); }
                });
            }
            var msgs = (res && res.messages) || [];
            if (msgs.length) { msgs.forEach(function (m) { TigerDOM.notify(modalFb, m.message, { type: m.class, replace: false }); }); }
            else { TigerDOM.notify(modalFb, t('fixFields', 'Please correct the highlighted fields.'), { type: 'error' }); }
        }).catch(function () {
            TigerDOM.notify(modalFb, t('networkError', 'Network error — please try again.'), { type: 'error' });
        });
    }

    function clearFields() {
        field('title').value = '';
        field('description').value = '';
        setImage({ image: '', image_url: '' });
    }

    document.addEventListener('DOMContentLoaded', function () {
        body    = document.getElementById('seo-pages-body');
        fb      = document.getElementById('seo-pages-feedback');
        modalEl = document.getElementById('seo-page-modal');
        form    = document.getElementById('seo-page-form');
        modalFb = document.getElementById('seo-page-feedback');
        if (!body || !form || !modalEl) { return; }

        modal = bootstrap.Modal.getOrCreateInstance(modalEl);

        body.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-seo-edit]');
            if (!btn) { return; }
            var row = rows[parseInt(btn.getAttribute('data-seo-edit'), 10)];
            if (row) { open(row); }
        });

        document.getElementById('seo-page-save').addEventListener('click', function () { save(this); });
        document.getElementById('seo-page-clear').addEventListener('click', clearFields);

        load();
    });
})(document);
