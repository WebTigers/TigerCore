// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * profile.index-contacts.js
 *
 * Profile → Contacts — the contact collection (add / edit / delete / mark primary) with a lazily
 * initialised intl-tel-input for phone types. Context-neutral: the same partial serves the user
 * and the org, and the service to call arrives on the host element. Everything goes through
 * /api.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var fb        = document.getElementById('profile-feedback');
    var form      = document.getElementById('contact-form');
    var csrfInput = form.querySelector('[name="_csrf"]');
    var host      = document.getElementById('contact-cards');
    var idInput   = document.getElementById('contact-id');
    var valHidden = document.getElementById('contact-value-hidden');
    var ccHidden  = document.getElementById('contact-phone-country');
    var typeEl    = document.getElementById('contact-type');
    var valueEl   = document.getElementById('contact-value');
    var valueWrap = document.getElementById('contact-value-wrap');
    var phoneEl   = document.getElementById('contact-phone');
    var phoneWrap = document.getElementById('contact-phone-wrap');
    var primEl    = document.getElementById('contact-primary');
    var saveBtn   = document.getElementById('contact-save');
    var saveLabel = document.getElementById('contact-save-label');
    var cancelBtn = document.getElementById('contact-cancel');

    // Server data rides on #contact-cards; strings come through the $this->i18n() carrier.
    function cfg(name, fallback) {
        try { return JSON.parse(host.getAttribute('data-' + name) || fallback); } catch (e) { return JSON.parse(fallback); }
    }
    var TYPES = cfg('types', '{}');
    var SVC   = host.getAttribute('data-service') || 'contact';   // 'contact' (user) | 'OrgContact' (org) — exact case, ucfirst-only
    var PHONE_DEFAULT = host.getAttribute('data-phone-default') || '';
    var STR = {
        add:      Tiger.t('contactAdd'),
        update:   Tiger.t('contactUpdate'),
        none:     Tiger.t('contactNone'),
        saved:    Tiger.t('contactSaved'),
        del:      Tiger.t('contactDeleted'),
        confirm:  Tiger.t('contactConfirmDelete'),
        badphone: Tiger.t('contactBadPhone')
    };

    // intl-tel-input is initialised lazily: the Contacts pane is hidden at load, and initialising the
    // widget on a display:none input mis-measures the dropdown. Init on first show (or if deep-linked).
    var iti = null;
    function initIti() {
        if (iti || !window.intlTelInput) { return; }
        iti = window.intlTelInput(phoneEl, {
            initialCountry: PHONE_DEFAULT || 'us',
            separateDialCode: true,
            formatOnDisplay: true,
            countrySearch: true
        });
    }
    var tabBtn = document.querySelector('[data-bs-toggle="tab"][data-bs-target="#contacts"]');
    if (tabBtn) { tabBtn.addEventListener('shown.bs.tab', initIti); }
    if (document.getElementById('contacts') && document.getElementById('contacts').classList.contains('active')) { initIti(); }

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function isPhone() { return typeEl.value === 'phone'; }

    // Display formatter: numbers are stored as bare digits (E.164 preferred), shown formatted via
    // intl-tel-input's bundled libphonenumber — e.g. +1 407-461-7174. E.164 (leading +) formats itself;
    // legacy national digits use the ISO on contact.type or the default country. Falls back to raw.
    // A hidden intl-tel-input instance used purely as a formatter (stable public API), so we don't
    // depend on the internal utils global name across versions.
    var _fmtIti = null;
    function fmtIti() {
        if (_fmtIti || !window.intlTelInput) { return _fmtIti; }
        var hid = document.createElement('input');
        hid.type = 'tel'; hid.tabIndex = -1; hid.setAttribute('aria-hidden', 'true');
        hid.style.cssText = 'position:absolute;left:-9999px;top:-9999px;width:200px;';
        document.body.appendChild(hid);
        _fmtIti = window.intlTelInput(hid, { initialCountry: PHONE_DEFAULT || 'us' });
        return _fmtIti;
    }
    function fmtPhone(value, isoType) {
        if (!value) { return value; }
        var v = String(value);
        var iso = (v.charAt(0) === '+') ? '' : String(isoType || PHONE_DEFAULT || 'us').toLowerCase();
        try {
            // Prefer the utils global (pure function) if present…
            var U = window.intlTelInputUtilsTemp || window.intlTelInputUtils;
            if (U && U.formatNumber) {
                var fmt = (U.numberFormat && U.numberFormat.INTERNATIONAL != null) ? U.numberFormat.INTERNATIONAL : 1;
                var out = U.formatNumber(v, iso, fmt);
                if (out) { return out; }
            }
            // …otherwise a hidden instance: setNumber + getNumber(1 = INTERNATIONAL).
            var it = fmtIti();
            if (it) { if (iso) { it.setCountry(iso); } it.setNumber(v); return it.getNumber(1) || v; }
        } catch (e) {}
        return v;
    }

    function toggleType() {
        var phone = isPhone();
        phoneWrap.classList.toggle('d-none', !phone);
        valueWrap.classList.toggle('d-none', phone);
        valueEl.type = (typeEl.value === 'email') ? 'email' : (typeEl.value === 'website') ? 'url' : 'text';
        if (phone) { initIti(); }
    }
    typeEl.addEventListener('change', toggleType);

    function render(list) {
        host.innerHTML = '';
        if (!list || !list.length) { host.innerHTML = '<p class="text-body-secondary small mb-0">' + esc(STR.none) + '</p>'; return; }
        list.forEach(function (c) {
            var label = TYPES[c.kind] || c.kind;
            var el = document.createElement('div');
            el.className = 'd-flex align-items-center justify-content-between border rounded px-3 py-2 mb-2';
            el.innerHTML =
                '<div class="d-flex align-items-center gap-2">' +
                    (Number(c.is_primary) ? '<i class="fa-solid fa-star text-warning" title="Primary"></i>' : '') +
                    '<span class="badge text-bg-secondary">' + esc(label) + '</span>' +
                    '<span>' + esc(c.kind === 'phone' ? fmtPhone(c.value, c.type) : c.value) + '</span>' +
                '</div>' +
                '<div class="text-nowrap">' +
                    '<button type="button" class="btn btn-sm btn-link text-body p-1 js-edit" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="btn btn-sm btn-link text-danger p-1 js-del" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>' +
                '</div>';
            el.querySelector('.js-edit').addEventListener('click', function () { edit(c); });
            el.querySelector('.js-del').addEventListener('click', function (e) { del(c.link_id, e.currentTarget); });
            host.appendChild(el);
        });
    }

    function reset() {
        idInput.value = ''; valueEl.value = ''; primEl.checked = false; typeEl.selectedIndex = 0;
        if (iti) { try { iti.setNumber(''); } catch (e) {} }
        toggleType(); saveLabel.textContent = STR.add; cancelBtn.classList.add('d-none');
        form.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
    }
    function edit(c) {
        idInput.value = c.link_id; typeEl.value = c.kind; primEl.checked = !!Number(c.is_primary);
        toggleType();
        if (c.kind === 'phone') { initIti(); if (iti) { try { iti.setNumber(c.value || ''); } catch (e) {} } }
        else { valueEl.value = c.value || ''; valueEl.focus(); }
        saveLabel.textContent = STR.update; cancelBtn.classList.remove('d-none');
    }
    cancelBtn.addEventListener('click', reset);

    saveBtn.addEventListener('click', function () {
        // Move the active input's value into the submitted hidden fields.
        if (isPhone()) {
            initIti();
            if (iti && !iti.isValidNumber()) { phoneEl.classList.add('is-invalid'); TigerDOM.notify(fb, STR.badphone, { type: 'error' }); return; }
            valHidden.value = iti ? iti.getNumber() : '';
            ccHidden.value  = iti ? (iti.getSelectedCountryData().iso2 || '').toUpperCase() : '';
        } else {
            valHidden.value = valueEl.value.trim();
            ccHidden.value  = '';
        }
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'profile'); fd.set('service', SVC); fd.set('method', 'save');
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1 && res.data) {
                render(res.data.contacts || []);
                if (res.data._csrf) { csrfInput.value = res.data._csrf; }   // rotate token
                reset();
                TigerDOM.notify(fb, STR.saved, { type: 'success' });
                return;
            }
            phoneEl.classList.remove('is-invalid'); valueEl.classList.remove('is-invalid');
            if (res && res.form && res.form.value) { (isPhone() ? phoneEl : valueEl).classList.add('is-invalid'); }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('netError'), { type: 'error' }); });
    });

    function del(id, btn) {
        if (!window.confirm(STR.confirm)) { return; }
        var fd = new URLSearchParams();
        fd.set('module', 'profile'); fd.set('service', SVC); fd.set('method', 'delete');
        fd.set('link_id', id); fd.set('_csrf', csrfInput.value);
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1 && res.data) {
                render(res.data.contacts || []);
                if (res.data._csrf) { csrfInput.value = res.data._csrf; }
                TigerDOM.notify(fb, STR.del, { type: 'success' });
            }
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('netError'), { type: 'error' }); });
    }

    toggleType();
    render(cfg('contacts', '[]'));
});
