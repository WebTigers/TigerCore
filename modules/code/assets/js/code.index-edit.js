// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * code.index-edit.js
 *
 * Snippet editor — a CodeMirror PHP editor + metadata, saving over /api to Code_Service_Code.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('code-form');
    var fb   = document.getElementById('code-feedback');

    var langSel = document.getElementById('code-language');
    var cm = (window.TigerCodeEditor && document.getElementById('code-code'))
        ? TigerCodeEditor.from(document.getElementById('code-code'), { mode: langSel ? langSel.value : 'php' })
        : null;

    var CM_MODE = { php: 'php', phtml: 'phtml', html: 'html', css: 'css', js: 'js' };
    var SERVER  = { php: 1, phtml: 1 };
    function syncLang() {
        if (!langSel) { return; }
        var L = langSel.value;
        if (cm) { TigerCodeEditor.setMode(cm, CM_MODE[L] || 'php'); }
        document.getElementById('code-lang-label').textContent = L.toUpperCase();
        document.getElementById('code-loc-row').style.display = (L === 'php' || L === 'css') ? 'none' : '';
        var w = document.getElementById('code-warning'), t = document.getElementById('code-warning-text');
        if (SERVER[L]) {
            w.className = 'alert alert-warning d-flex align-items-center gap-2 py-2';
            t.innerHTML = 'This ' + (L === 'phtml' ? 'PHTML' : 'PHP') + ' is <strong>server-executed</strong>. Linted on save; auto-deactivates if it fatals.';
        } else {
            w.className = 'alert alert-info d-flex align-items-center gap-2 py-2';
            t.innerHTML = 'This ' + L.toUpperCase() + ' is injected into the page and runs in the browser.';
        }
    }
    if (langSel) { langSel.addEventListener('change', syncLang); syncLang(); }

    function post(extra, onOk, btn) {
        if (window.TigerCodeEditor) { TigerCodeEditor.syncAll(); }   // flush CodeMirror — synchronous setup, before the busy-state
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'code'); fd.set('service', 'code');
        Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
        var task = function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json(); });
        };
        return (btn ? TigerButton.run(btn, task) : task())
            .then(function (res) {
                if (res.result === 1) { onOk(res); return; }
                var msgs = (res.messages) || [];
                if (msgs.length) { fb.innerHTML = ''; msgs.forEach(function (m) { TigerDOM.notify(fb, '<pre class="mb-0" style="white-space:pre-wrap;font-family:inherit;">' + String(m.message) + '</pre>', { type: m.class, replace: false }); }); }
                else { TigerDOM.notify(fb, Tiger.t('fixForm'), { type: 'error' }); }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    }

    document.getElementById('code-save').addEventListener('click', function () {
        var btn = this;
        fb.innerHTML = '';
        post({ method: 'save' }, function (res) { window.location = res.redirect || '/code'; }, btn);
    });
    document.querySelectorAll('[data-code-restore]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = this;
            var ver = btn.getAttribute('data-code-restore');
            if (!confirm(Tiger.t('confirmRestore', ver))) { return; }
            post({ method: 'restore', version: ver }, function (res) { window.location = res.redirect || window.location.href; }, el);
        });
    });
});
