// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * blog.post-edit.js
 *
 * Medium-style article editor. A distraction-free writing canvas (Kicker · Title · Subtitle ·
 * Preamble · Body) plus a "Post settings" offcanvas for the metadata. The canvas fields are
 * plain named inputs (styled borderless); the body is a contentEditable surface mirrored into a
 * hidden <textarea name="body"> on save. Everything posts to the /api service Blog_Service_Post
 * (no controller post-back) — one row in `page` (type=article) with its scalars in page.meta,
 * per the posts-on-page design.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form  = document.getElementById('ed-form');
    var fb    = document.getElementById('ed-feedback');
    var bodyEl = document.getElementById('ed-body');
    var bodySrc = document.getElementById('ed-body-src');
    var featInput = document.getElementById('post-feature');

    // Visual ↔ HTML-source toggle — same body, two ways to edit it (colored, foldable code).
    var codeMode = false, srcCM = null;
    var codeBtn = document.querySelector('[data-ed-code]');
    function toCode() {
        if (!window.TigerCodeEditor) { return; }
        var ta = document.getElementById('ed-body-code');
        ta.value = bodyEl.innerHTML.trim();
        if (!srcCM) { srcCM = TigerCodeEditor.from(ta, { mode: 'html' }); }
        else { srcCM.setValue(ta.value); srcCM.getWrapperElement().style.display = ''; }
        srcCM.refresh();
        bodyEl.classList.add('d-none');
        codeMode = true; if (codeBtn) { codeBtn.classList.add('active'); }
    }
    function toVisual() {
        if (srcCM) { bodyEl.innerHTML = srcCM.getValue(); srcCM.getWrapperElement().style.display = 'none'; }
        bodyEl.classList.remove('d-none');
        codeMode = false; if (codeBtn) { codeBtn.classList.remove('active'); }
    }
    if (codeBtn) { codeBtn.addEventListener('click', function () { codeMode ? toVisual() : toCode(); }); }
    function syncBody() { bodySrc.value = (codeMode && srcCM) ? srcCM.getValue() : bodyEl.innerHTML.trim(); }

    function clearErrors() {
        fb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
    }

    // Formatting toolbar (contentEditable). execCommand is deprecated but universal + zero-dep.
    document.querySelectorAll('.ed-toolbar [data-cmd]').forEach(function (btn) {
        btn.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep selection
        btn.addEventListener('click', function () {
            var cmd = btn.getAttribute('data-cmd'), val = btn.getAttribute('data-val') || null;
            bodyEl.focus();
            if (cmd === 'formatBlock' && val) { document.execCommand('formatBlock', false, val); }
            else { document.execCommand(cmd, false, val); }
        });
    });
    var linkBtn = document.querySelector('[data-ed-link]');
    if (linkBtn) { linkBtn.addEventListener('mousedown', function (e){ e.preventDefault(); });
        linkBtn.addEventListener('click', function () {
            var url = prompt(Tiger.t('linkUrl')); if (url) { bodyEl.focus(); document.execCommand('createLink', false, url); }
        });
    }

    // Media picker: feature image + inline image insert (uses the platform TigerMediaPicker).
    function pickImage(cb) {
        if (typeof TigerMediaPicker === 'undefined') { alert(Tiger.t('mediaPickerUnavailable')); return; }
        TigerMediaPicker.open({ kind: 'image', multiple: false, onSelect: function (items) {
            var m = items && items[0]; if (m) { cb(m); }
        }});
    }
    function mediaUrl(m) { return m.url || m.thumb || ('/media/file/serve/id/' + m.media_id); }

    document.querySelectorAll('[data-ed-feature]').forEach(function (b) {
        b.addEventListener('click', function () { pickImage(function (m) {
            featInput.value = m.media_id;
            document.getElementById('ed-feature-img').src = mediaUrl(m);
            document.getElementById('ed-feature-empty').style.display = 'none';
            document.getElementById('ed-feature-has').style.display = '';
        }); });
    });
    var clearFeat = document.querySelector('[data-ed-feature-clear]');
    if (clearFeat) { clearFeat.addEventListener('click', function () {
        featInput.value = '';
        document.getElementById('ed-feature-empty').style.display = '';
        document.getElementById('ed-feature-has').style.display = 'none';
    }); }
    var imgBtn = document.querySelector('[data-ed-image]');
    if (imgBtn) { imgBtn.addEventListener('mousedown', function (e){ e.preventDefault(); });
        imgBtn.addEventListener('click', function () {
            bodyEl.focus();
            pickImage(function (m) { document.execCommand('insertHTML', false, '<img src="' + mediaUrl(m) + '" alt="">'); });
        });
    }

    function post(extra, onOk, btn) {
        syncBody();   // mirror the active surface (visual or code) into the form field — synchronous setup, before the busy-state
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'blog'); fd.set('service', 'post');
        Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
        var task = function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json(); });
        };
        return (btn ? TigerButton.run(btn, task) : task())
            .then(function (res) {
                if (res.result === 1) { onOk(res); return; }
                clearErrors();
                if (res.form) { Object.keys(res.form).forEach(function (f) {
                    var i = form.querySelector('[name="' + f + '"]'); if (i) { i.classList.add('is-invalid'); }
                }); }
                var msgs = (res.messages) || [];
                if (msgs.length) { fb.innerHTML = ''; msgs.forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class, replace: false }); }); }
                else { TigerDOM.notify(fb, Tiger.t('fixFields'), { type: 'error' }); }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    }

    document.getElementById('ed-save').addEventListener('click', function () {
        var btn = this;
        clearErrors();
        document.getElementById('ed-savestate').textContent = 'Saving…';
        post({ method: 'save' }, function (res) { window.location = res.redirect || '/blog/post'; }, btn);
    });

    document.querySelectorAll('[data-ed-restore]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = this;
            var ver = btn.getAttribute('data-ed-restore');
            if (!confirm(Tiger.t('confirmRestore', ver))) { return; }
            post({ method: 'restore', version: ver }, function (res) { window.location = res.redirect || window.location.href; }, el);
        });
    });
});
