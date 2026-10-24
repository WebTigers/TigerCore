// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * profile.index-addresses.js
 *
 * Profile → Addresses — the address collection (add / edit / delete / mark primary) with the
 * shared address autocomplete. Context-neutral: the same partial serves the user and the org,
 * and the service to call arrives on the host element. Everything goes through /api.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var fb        = document.getElementById('profile-feedback');
    var form      = document.getElementById('address-form');
    var csrfInput = form.querySelector('[name="_csrf"]');
    var host      = document.getElementById('address-cards');
    var idInput   = document.getElementById('addr-id');
    var latInput  = document.getElementById('addr-lat');
    var lngInput  = document.getElementById('addr-lng');
    var f = {
        country: document.getElementById('addr-country'), type: document.getElementById('addr-type'),
        line1: document.getElementById('addr-line1'), line2: document.getElementById('addr-line2'),
        city: document.getElementById('addr-city'), region: document.getElementById('addr-region'),
        postal: document.getElementById('addr-postal'), primary: document.getElementById('addr-primary')
    };
    var saveBtn = document.getElementById('address-save');
    var saveLabel = document.getElementById('address-save-label');
    var cancelBtn = document.getElementById('address-cancel');

    // Server data rides on #address-cards; strings come through the $this->i18n() carrier.
    function cfg(name, fallback) {
        try { return JSON.parse(host.getAttribute('data-' + name) || fallback); } catch (e) { return JSON.parse(fallback); }
    }
    var TYPES = cfg('types', '{}');
    var SVC   = host.getAttribute('data-service') || 'address';   // 'address' (user) | 'OrgAddress' (org) — exact case, ucfirst-only
    var STR = {
        add:     Tiger.t('addressAdd'),
        update:  Tiger.t('addressUpdate'),
        none:    Tiger.t('addressNone'),
        saved:   Tiger.t('addressSaved'),
        del:     Tiger.t('addressDeleted'),
        confirm: Tiger.t('addressConfirmDelete')
    };

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function fmtAddr(a) {
        var parts = [];
        var l1 = (a.line1 || '') + (a.line2 ? ', ' + a.line2 : '');
        if (l1.trim()) { parts.push(l1); }
        var cr = [a.city, a.region].filter(Boolean).join(', ');
        if (a.postal) { cr += (cr ? ' ' : '') + a.postal; }
        if (cr) { parts.push(cr); }
        if (a.country) { parts.push(a.country); }
        return parts.join(', ');
    }

    function render(list) {
        host.innerHTML = '';
        if (!list || !list.length) { host.innerHTML = '<p class="text-body-secondary small mb-0">' + esc(STR.none) + '</p>'; return; }
        list.forEach(function (a) {
            var label = TYPES[a.label] || a.label;
            var el = document.createElement('div');
            el.className = 'd-flex align-items-center justify-content-between border rounded px-3 py-2 mb-2';
            el.innerHTML =
                '<div class="d-flex align-items-center gap-2">' +
                    (Number(a.is_primary) ? '<i class="fa-solid fa-star text-warning" title="Primary"></i>' : '') +
                    '<span class="badge text-bg-secondary">' + esc(label) + '</span>' +
                    '<span>' + esc(fmtAddr(a)) + '</span>' +
                '</div>' +
                '<div class="text-nowrap">' +
                    '<button type="button" class="btn btn-sm btn-link text-body p-1 js-edit" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-link text-danger p-1 js-del" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>' +
                '</div>';
            el.querySelector('.js-edit').addEventListener('click', function () { edit(a); });
            el.querySelector('.js-del').addEventListener('click', function (e) { del(a.link_id, e.currentTarget); });
            host.appendChild(el);
        });
    }

    function reset() {
        idInput.value = ''; latInput.value = ''; lngInput.value = '';
        f.country.selectedIndex = 0; f.type.selectedIndex = 0; f.primary.checked = false;
        ['line1', 'line2', 'city', 'region', 'postal'].forEach(function (k) { f[k].value = ''; });
        saveLabel.textContent = STR.add; cancelBtn.classList.add('d-none');
        form.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
    }
    function edit(a) {
        idInput.value = a.link_id;
        // Carry the stored geocode; it persists on save unless the user retypes the street
        // (tiger.address.js clears these on a manual edit).
        latInput.value = a.latitude == null ? '' : a.latitude;
        lngInput.value = a.longitude == null ? '' : a.longitude;
        f.country.value = a.country || ''; f.type.value = a.label || ''; f.primary.checked = !!Number(a.is_primary);
        f.line1.value = a.line1 || ''; f.line2.value = a.line2 || ''; f.city.value = a.city || '';
        f.region.value = a.region || ''; f.postal.value = a.postal || '';
        saveLabel.textContent = STR.update; cancelBtn.classList.remove('d-none'); f.line1.focus();
    }
    cancelBtn.addEventListener('click', reset);

    function post(method, extra, btn) {
        var fd = extra ? new URLSearchParams(extra) : new URLSearchParams(new FormData(form));
        fd.set('module', 'profile'); fd.set('service', SVC); fd.set('method', method);
        if (extra) { fd.set('_csrf', csrfInput.value); }
        return TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        });
    }

    saveBtn.addEventListener('click', function () {
        post('save', null, this).then(function (res) {
            if (res && res.result === 1 && res.data) {
                render(res.data.addresses || []);
                if (res.data._csrf) { csrfInput.value = res.data._csrf; }   // rotate token
                reset();
                TigerDOM.notify(fb, STR.saved, { type: 'success' });
                return;
            }
            form.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
            if (res && res.form) { Object.keys(res.form).forEach(function (k) { if (f[k]) { f[k].classList.add('is-invalid'); } }); }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('netError'), { type: 'error' }); });
    });

    function del(id, btn) {
        if (!window.confirm(STR.confirm)) { return; }
        post('delete', { link_id: id }, btn).then(function (res) {
            if (res && res.result === 1 && res.data) {
                render(res.data.addresses || []);
                if (res.data._csrf) { csrfInput.value = res.data._csrf; }
                TigerDOM.notify(fb, STR.del, { type: 'success' });
            }
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('netError'), { type: 'error' }); });
    }

    render(cfg('addresses', '[]'));
});
