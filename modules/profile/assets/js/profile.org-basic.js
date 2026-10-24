// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * profile.org-basic.js
 *
 * Org profile → Basic — the organization's name + URL-safe slug and its logo (pick/drop → free-
 * aspect crop → PNG upload, so transparency survives). Saves over /api.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var fb = document.getElementById('profile-feedback');

    // ----- Basic (name + slug) save -----
    var form = document.getElementById('org-basic-form');
    document.getElementById('org-basic-save').addEventListener('click', function () {
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'profile'); fd.set('service', 'org'); fd.set('method', 'save');
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            form.querySelectorAll('.is-invalid').forEach(function (x) { x.classList.remove('is-invalid'); });
            if (res && res.result === 1) {
                if (res.data && res.data.slug) { document.getElementById('org-slug').value = res.data.slug; }
                TigerDOM.notify(fb, (res.messages && res.messages[0]) ? res.messages[0].message : Tiger.t('orgSaved'), { type: 'success' });
                return;
            }
            if (res && res.form) { Object.keys(res.form).forEach(function (f) { var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); } }); }
            if (res && res.data && res.data.field) { var el = form.querySelector('[name="' + res.data.field + '"]'); if (el) { el.classList.add('is-invalid'); } }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('netError'), { type: 'error' }); });
    });

    // ----- Logo: pick / drop (TigerUpload) -> crop (Cropper.js) -> upload (org-scoped) -----
    var input   = document.getElementById('logo-file');
    var modalEl = document.getElementById('logo-crop-modal');
    var cropImg = document.getElementById('logo-crop-image');
    var modalObj = null, cropper = null, pendingSrc = null;

    function bust(url) { return url + (url.indexOf('?') < 0 ? '?' : '&') + 't=' + Date.now(); }
    function getModal() { if (!modalObj) { modalObj = bootstrap.Modal.getOrCreateInstance(modalEl); } return modalObj; }
    function destroyCropper() { if (cropper) { cropper.destroy(); cropper = null; } }
    function showLogo(url) {
        var img = document.getElementById('logo-img');
        img.src = bust(url); img.classList.remove('d-none');
        document.getElementById('logo-placeholder').classList.add('d-none');
        document.getElementById('logo-remove').classList.remove('d-none');
    }

    var up = TigerUpload.create({
        url: '/api', field: 'file', auto: false,
        params: { module: 'profile', service: 'OrgLogo', method: 'upload' }
    });
    up.dropOnWindow({ label: Tiger.t('orgLogoDropLabel'), sublabel: Tiger.t('orgLogoDropSub'),
        onDrop: function (files) { openCropper(files && files[0]); } });
    up.subscribe({
        onSuccess: function (item) {
            if (item.response && item.response.url) { showLogo(bust(item.response.url)); }
            getModal().hide();
            TigerDOM.notify(fb, Tiger.t('orgLogoSaved'), { type: 'success' });
        },
        onError: function (item) { TigerDOM.notify(fb, (item && item.error) || Tiger.t('orgLogoErrorUpload'), { type: 'error' }); }
    });

    document.getElementById('logo-change').addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () { openCropper(input.files && input.files[0]); input.value = ''; });

    function openCropper(file) {
        if (!file || !/^image\//.test(file.type)) { return; }
        var reader = new FileReader();
        reader.onload = function (e) { pendingSrc = e.target.result; getModal().show(); };
        reader.readAsDataURL(file);
    }

    // Init Cropper only once the modal is shown AND the image has loaded — both must hold or Cropper
    // measures a zero-size box and renders nothing.
    modalEl.addEventListener('shown.bs.modal', function () {
        destroyCropper();
        cropImg.onload = function () {
            cropImg.onload = null;
            try {
                cropper = new Cropper(cropImg, { viewMode: 1, autoCropArea: 0.9, responsive: true, background: true });
            } catch (err) {
                TigerDOM.notify(fb, Tiger.t('cropperFailed', (err && err.message ? err.message : err)), { type: 'error' });
            }
        };
        cropImg.src = pendingSrc;
    });
    modalEl.addEventListener('hidden.bs.modal', function () { destroyCropper(); });

    document.getElementById('logo-crop-save').addEventListener('click', function () {
        if (!cropper) { return; }
        var canvas = cropper.getCroppedCanvas({ maxWidth: 1024, maxHeight: 1024, imageSmoothingQuality: 'high' });
        if (!canvas) { return; }
        canvas.toBlob(function (blob) {
            var file = new File([blob], 'logo.png', { type: 'image/png' });   // PNG keeps transparency
            up.clear();          // drop any raw file the window-drop queued
            up.add([file]);
            up.start();
        }, 'image/png');
    });

    document.getElementById('logo-remove').addEventListener('click', function () {
        var fd = new URLSearchParams();
        fd.set('module', 'profile'); fd.set('service', 'OrgLogo'); fd.set('method', 'remove');
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) {
                var img = document.getElementById('logo-img');
                img.classList.add('d-none'); img.src = '';
                document.getElementById('logo-placeholder').classList.remove('d-none');
                this.classList.add('d-none');
                TigerDOM.notify(fb, (res.messages && res.messages[0]) ? res.messages[0].message : Tiger.t('orgLogoRemoved'), { type: 'success' });
            }
        }.bind(this)).catch(function () { TigerDOM.notify(fb, Tiger.t('netError'), { type: 'error' }); });
    });
});
