// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * profile.index-basic.js
 *
 * Profile → Basic — the person's own username / language / timezone, plus the avatar (pick or
 * drop via TigerUpload → crop with Cropper.js → upload, and remove). Saves over /api.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var fb = document.getElementById('profile-feedback');

    // ----- Basic info save -----
    document.getElementById('profile-basic-save').addEventListener('click', function () {
        var form = document.getElementById('profile-basic-form');
        var fd   = new URLSearchParams(new FormData(form));
        fd.set('module', 'profile'); fd.set('service', 'user'); fd.set('method', 'save');
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) {
                var dn = document.getElementById('profile-display-name').value.trim();
                window.dispatchEvent(new CustomEvent('tiger:display-name-changed', { detail: { name: dn || null } }));
                TigerDOM.notify(fb, Tiger.t('userSaved'), { type: 'success' });
                return;
            }
            if (res && res.form) { Object.keys(res.form).forEach(function (f) { var el = form.querySelector('[name="' + f + '"]'); if (el) { el.classList.add('is-invalid'); } }); }
            (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        }).catch(function () { TigerDOM.notify(fb, Tiger.t('netError'), { type: 'error' }); });
    });

    // ----- Avatar: pick / drop (TigerUpload) -> crop (Cropper.js) -> upload -----
    var input   = document.getElementById('avatar-file');
    var modalEl = document.getElementById('avatar-crop-modal');
    var cropImg = document.getElementById('avatar-crop-image');
    var cropper = null, modalObj = null, pendingSrc = null;

    function bust(url) { return url + (url.indexOf('?') < 0 ? '?' : '&') + 't=' + Date.now(); }
    function getModal() { if (!modalObj) { modalObj = bootstrap.Modal.getOrCreateInstance(modalEl); } return modalObj; }
    function destroyCropper() { if (cropper) { cropper.destroy(); cropper = null; } }
    function showAvatar(url) {
        var img = document.getElementById('avatar-img');
        img.src = url; img.classList.remove('d-none');
        document.getElementById('avatar-placeholder').classList.add('d-none');
        document.getElementById('avatar-remove').classList.remove('d-none');
    }

    // TigerUpload: the upload engine + the full-page transparent drop pane. auto:false — we upload
    // the CROPPED result on Save, not the raw drop.
    var up = TigerUpload.create({
        url: '/api', field: 'file', auto: false,
        params: { module: 'profile', service: 'avatar', method: 'upload' }
    });
    up.dropOnWindow({ label: Tiger.t('avatarDropLabel'), sublabel: Tiger.t('avatarDropSub'),
        onDrop: function (files) { openCropper(files && files[0]); } });
    up.subscribe({
        onSuccess: function (item) {
            if (item.response && item.response.url) {
                var u = bust(item.response.url);
                showAvatar(u);
                window.dispatchEvent(new CustomEvent('tiger:avatar-changed', { detail: { url: u } }));
            }
            getModal().hide();
            TigerDOM.notify(fb, Tiger.t('avatarSaved'), { type: 'success' });
        },
        onError: function (item) { TigerDOM.notify(fb, (item && item.error) || Tiger.t('avatarErrorUpload'), { type: 'error' }); }
    });

    document.getElementById('avatar-change').addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () { openCropper(input.files && input.files[0]); input.value = ''; });

    // Read the file to a data-URL so the <img> is fully decoded before Cropper measures it (the fix
    // for a blank Cropper inside a Bootstrap modal), then show the modal.
    function openCropper(file) {
        if (!file || !/^image\/(png|jpeg|webp)$/.test(file.type)) {
            TigerDOM.notify(fb, Tiger.t('avatarErrorType'), { type: 'error' });
            return;
        }
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
                cropper = new Cropper(cropImg, { aspectRatio: 1, viewMode: 1, autoCropArea: 0.85, responsive: true, background: true });
            } catch (err) {
                TigerDOM.notify(fb, Tiger.t('cropperFailed', (err && err.message ? err.message : err)), { type: 'error' });
            }
        };
        cropImg.src = pendingSrc;
    });
    modalEl.addEventListener('hidden.bs.modal', function () { destroyCropper(); });

    document.getElementById('avatar-crop-save').addEventListener('click', function () {
        if (!cropper) { return; }
        var canvas = cropper.getCroppedCanvas({ width: 512, height: 512, imageSmoothingQuality: 'high' });
        if (!canvas) { return; }
        canvas.toBlob(function (blob) {
            var file = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
            up.clear();          // drop any raw file the window-drop queued
            up.add([file]);
            up.start();
        }, 'image/jpeg', 0.9);
    });

    document.getElementById('avatar-remove').addEventListener('click', function () {
        var btn = this;
        var fd = new URLSearchParams();
        fd.set('module', 'profile'); fd.set('service', 'avatar'); fd.set('method', 'remove');
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) {
            if (res && res.result === 1) {
                document.getElementById('avatar-img').classList.add('d-none');
                document.getElementById('avatar-placeholder').classList.remove('d-none');
                btn.classList.add('d-none');
                window.dispatchEvent(new CustomEvent('tiger:avatar-changed', { detail: { url: null } }));
                TigerDOM.notify(fb, Tiger.t('avatarRemoved'), { type: 'success' });
            }
        });
    });
});
