// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * cms.page-edit.js
 *
 * CMS page editor — create/edit a page, layout, or partial. The form elements are
 * ViewHelper-only (Tiger_Form strips chrome), so this view owns all markup. Saving is an /api
 * call to Cms_Service_Page (no controller post-back); the response drives the redirect / inline
 * errors. Version history (edit mode) restores via the same service.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('cms-page-form');
    var fb   = document.getElementById('cms-feedback');

    // Colored, foldable code editing for the body — mode follows the Format select.
    var fmt = document.getElementById('cms-format');
    var cm  = (window.TigerCodeEditor && document.getElementById('cms-body'))
        ? TigerCodeEditor.from(document.getElementById('cms-body'), { mode: fmt ? fmt.value : 'html' })
        : null;
    if (cm && fmt) { fmt.addEventListener('change', function () { TigerCodeEditor.setMode(cm, fmt.value); }); }
    var foldBtn = document.getElementById('cms-fold'), unfoldBtn = document.getElementById('cms-unfold');
    if (foldBtn)   { foldBtn.addEventListener('click', function () { TigerCodeEditor.foldAll(cm); }); }
    if (unfoldBtn) { unfoldBtn.addEventListener('click', function () { TigerCodeEditor.unfoldAll(cm); }); }

    // Reformat — pretty-print into nested, indented lines (js-beautify), by the current Format.
    var reformatBtn = document.getElementById('cms-reformat');
    if (reformatBtn) {
        reformatBtn.addEventListener('click', function () {
            if (!cm) { return; }
            if (typeof window.html_beautify !== 'function') {
                TigerDOM.toast(Tiger.t('formatterNotLoaded'), { type: 'alert' });
                return;
            }
            var f = fmt ? fmt.value : 'html', src = cm.getValue(), out;
            try {
                if (f === 'css') {
                    out = window.css_beautify(src, { indent_size: 2 });
                } else if (f === 'js') {
                    out = window.js_beautify(src, { indent_size: 2 });
                } else if (f === 'markdown') {
                    TigerDOM.toast(Tiger.t('reformatNotMarkdown'), { type: 'info' });
                    return;
                } else {
                    // html, phtml, builder (GrapesJS export) — all HTML at heart.
                    out = window.html_beautify(src, { indent_size: 2, preserve_newlines: true, max_preserve_newlines: 2, wrap_line_length: 0, unformatted: ['pre', 'textarea', 'code'] });
                }
            } catch (e) {
                TigerDOM.toast(Tiger.t('reformatFailed'), { type: 'error' });
                return;
            }
            var pos = cm.getScrollInfo();
            cm.setValue(out);
            cm.scrollTo(pos.left, pos.top);
        });
    }

    // Find bar — highlight all matches, show n/total, jump prev/next (auto-scroll), Word-style.
    (function () {
        var input = document.getElementById('cms-find'),
            countEl = document.getElementById('cms-find-count'),
            prevBtn = document.getElementById('cms-find-prev'),
            nextBtn = document.getElementById('cms-find-next');
        if (!input || !countEl || !cm) { return; }
        var marks = [], hits = [], cur = -1, q = '';

        function clear() { marks.forEach(function (m) { m.clear(); }); marks = []; hits = []; cur = -1; }
        function count() { countEl.textContent = q ? (hits.length ? (cur + 1) + '/' + hits.length : '0/0') : ''; }
        function run() {
            clear(); q = input.value || '';
            if (!q) { count(); return; }
            var text = cm.getValue(), lt = text.toLowerCase(), lq = q.toLowerCase(), i = 0, n = q.length;
            while ((i = lt.indexOf(lq, i)) !== -1) {
                var from = cm.posFromIndex(i), to = cm.posFromIndex(i + n);
                hits.push({ from: from, to: to });
                marks.push(cm.markText(from, to, { className: 'cm-find-hit' }));
                i += n;
            }
            if (hits.length) { go(0); } else { count(); }
        }
        function go(idx) {
            if (!hits.length) { return; }
            cur = (idx % hits.length + hits.length) % hits.length;
            var h = hits[cur];
            cm.setSelection(h.from, h.to);              // the current match = the editor's selection
            cm.scrollIntoView({ from: h.from, to: h.to }, 80);
            count();
        }
        var t;
        input.addEventListener('input', function () { clearTimeout(t); t = setTimeout(run, 150); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); go(cur + (e.shiftKey ? -1 : 1)); }
            else if (e.key === 'Escape') { input.value = ''; clear(); count(); cm.focus(); }
        });
        prevBtn.addEventListener('click', function () { go(cur - 1); input.focus(); });
        nextBtn.addEventListener('click', function () { go(cur + 1); input.focus(); });
    })();

    function clearErrors() {
        fb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
    }
    // UTF-8-safe base64 (chunked so large page bodies don't blow the call stack).
    function b64utf8(s) {
        var bytes = new TextEncoder().encode(String(s == null ? '' : s)), bin = '', chunk = 0x8000;
        for (var i = 0; i < bytes.length; i += chunk) { bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk)); }
        return btoa(bin);
    }

    function post(btn, extra, onOk) {
        if (window.TigerCodeEditor) { TigerCodeEditor.syncAll(); }   // flush CodeMirror -> textarea
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'cms'); fd.set('service', 'page');
        Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
        // WAF shim: send the code-bearing fields as *_b64 so the shared ALB WAF doesn't 403 on the
        // raw <script>/HTML/PHP. Tiger_Service_Service decodes them back transparently before save.
        ['body', 'head_html', 'body_scripts'].forEach(function (f) {
            if (fd.has(f)) { fd.set(f + '_b64', b64utf8(fd.get(f))); fd.delete(f); }
        });
        return TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json(); });
        })
            .then(function (res) {
                if (res.result === 1) { onOk(res); return; }
                clearErrors();
                if (res.form) {
                    Object.keys(res.form).forEach(function (field) {
                        var input = form.querySelector('[name="' + field + '"]');
                        if (input) { input.classList.add('is-invalid'); }
                    });
                }
                var msgs = (res.messages) || [];
                if (msgs.length) { fb.innerHTML = ''; msgs.forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class, replace: false }); }); }
                else { TigerDOM.notify(fb, Tiger.t('fixFieldsRetry'), { type: 'error' }); }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    }

    document.getElementById('cms-save').addEventListener('click', function () {
        clearErrors();
        post(this, { method: 'save' }, function (res) {
            // Stay in the editor — never navigate away on save. For a brand-new page, adopt the
            // returned id (hidden field + URL) so the next save UPDATES rather than inserting a dupe.
            var pid = res && res.data && res.data.page_id;
            var idInput = form.querySelector('[name="page_id"]');
            if (pid && idInput && !idInput.value) {
                idInput.value = pid;
                if (window.history && history.replaceState) { history.replaceState(null, '', '/cms/page/edit/id/' + encodeURIComponent(pid)); }
            }
            var msg = (res.messages && res.messages[0] && res.messages[0].message) || Tiger.t('pageSaved');
            TigerDOM.notify(fb, msg, { type: 'success' });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    document.querySelectorAll('[data-cms-restore]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var ver = btn.getAttribute('data-cms-restore');
            if (!confirm(Tiger.t('confirmRestore', ver))) { return; }
            post(btn, { method: 'restore', version: ver }, function (res) { window.location = res.redirect || window.location.href; });
        });
    });
});
