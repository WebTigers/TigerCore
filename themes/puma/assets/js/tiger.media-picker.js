/*! SPDX-License-Identifier: BSD-3-Clause · © 2026 WebTigers · Tiger™/WebTigers™ are trademarks */
/**
 * TigerMediaPicker — a reusable "choose media" modal for any Tiger module.
 *
 * Browses the Media Library (Media_Service_Media over /api), lets the user select one or
 * many items (or upload new ones), and hands them back. Vanilla JS; uses the Bootstrap
 * modal that's already on the admin page. Two ways to use it:
 *
 *   JS:   TigerMediaPicker.open({ multiple:false, kind:'image', onSelect: function (items) { … } });
 *         (single -> onSelect(item); multiple -> onSelect([items])). Each item is the media
 *         row {media_id, url, thumb, kind, filename, …}.
 *
 *   Markup (auto-wired): a button [data-media-choose] paired with a hidden input drives a
 *   form field — see Tiger_View_Helper_MediaField. data-kind / data-multiple set the mode;
 *   the picker writes the chosen media_id(s) into the input and updates the preview.
 */
(function (global, document) {
    'use strict';

    var modalEl = null, bs = null, opts = null;
    var rows = [], picked = {}, offset = 0, total = 0, busy = false, GLEN = 60;

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    function icon(k) { var m = { image:'fa-image', pdf:'fa-file-pdf', document:'fa-file-lines', video:'fa-film', audio:'fa-music', archive:'fa-file-zipper', other:'fa-file' }; return '<i class="fa-solid ' + (m[k] || 'fa-file') + '"></i>'; }

    function style() {
        if (document.getElementById('tmp-style')) { return; }
        var s = document.createElement('style'); s.id = 'tmp-style';
        s.textContent =
            '.tmp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.5rem;}' +
            '.tmp-tile{position:relative;border:2px solid transparent;border-radius:8px;overflow:hidden;cursor:pointer;aspect-ratio:1;background:var(--bs-secondary-bg);}' +
            '.tmp-tile img{width:100%;height:100%;object-fit:cover;display:block;}' +
            '.tmp-ico{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:.3rem;color:var(--bs-secondary-color);}' +
            '.tmp-tile.tmp-sel{border-color:var(--bs-primary);}' +
            '.tmp-tile.tmp-sel::after{content:"\\f00c";font-family:"Font Awesome 6 Free";font-weight:900;position:absolute;top:.25rem;right:.35rem;color:#fff;background:var(--bs-primary);border-radius:50%;width:20px;height:20px;line-height:20px;text-align:center;font-size:.7rem;}' +
            // Static (module/theme-shipped) media: an origin badge + a hover Copy-to-library button.
            '.tmp-tile.tmp-static::before{content:attr(data-badge);position:absolute;top:.25rem;left:.3rem;background:rgba(0,0,0,.62);color:#fff;font-size:.58rem;padding:.1rem .32rem;border-radius:.3rem;z-index:1;pointer-events:none;max-width:calc(100% - .6rem);overflow:hidden;white-space:nowrap;text-overflow:ellipsis;}' +
            '.tmp-copy{position:absolute;bottom:.25rem;right:.3rem;width:24px;height:24px;border:0;border-radius:50%;background:rgba(var(--bs-primary-rgb),.92);color:#fff;font-size:.7rem;cursor:pointer;z-index:2;display:none;align-items:center;justify-content:center;}' +
            '.tmp-tile:hover .tmp-copy{display:flex;}' +
            '.tmp-copy[disabled]{display:flex;background:rgba(var(--bs-success-rgb),.92);cursor:default;}';
        document.head.appendChild(s);
    }

    function build() {
        if (modalEl) { return; }
        style();
        modalEl = document.createElement('div');
        modalEl.className = 'modal fade'; modalEl.tabIndex = -1; modalEl.setAttribute('aria-hidden', 'true');
        modalEl.innerHTML =
            '<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">' +
            '<div class="modal-header"><h5 class="modal-title">Select media</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>' +
            '<div class="modal-body">' +
              '<div class="d-flex gap-2 mb-3 align-items-center">' +
                '<input type="search" class="form-control form-control-sm" data-tmp-search placeholder="Search…">' +
                '<select class="form-select form-select-sm" data-tmp-kind style="width:auto;">' +
                  '<option value="">All types</option><option value="image">Images</option><option value="pdf">PDFs</option>' +
                  '<option value="document">Documents</option><option value="video">Video</option><option value="audio">Audio</option><option value="archive">Archives</option>' +
                '</select>' +
                '<label class="btn btn-sm btn-outline-primary ms-auto mb-0"><input type="file" multiple hidden data-tmp-file><i class="fa-solid fa-arrow-up-from-bracket me-1"></i>Upload</label>' +
              '</div>' +
              '<div data-tmp-note class="small text-body-secondary mb-2"></div>' +
              '<div class="tmp-grid" data-tmp-grid></div>' +
              '<div class="text-center mt-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-tmp-more hidden>Load more</button></div>' +
            '</div>' +
            '<div class="modal-footer"><span class="me-auto small text-body-secondary" data-tmp-count></span>' +
              '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>' +
              '<button type="button" class="btn btn-primary" data-tmp-ok disabled>Select</button></div>' +
            '</div></div>';
        document.body.appendChild(modalEl);
        bs = bootstrap.Modal.getOrCreateInstance(modalEl);

        var q = function (sel) { return modalEl.querySelector(sel); };
        var t;
        q('[data-tmp-search]').addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { load(true); }, 250); });
        q('[data-tmp-kind]').addEventListener('change', function () { load(true); });
        q('[data-tmp-more]').addEventListener('click', function () { load(false); });
        q('[data-tmp-file]').addEventListener('change', function () { uploadFiles(this.files); this.value = ''; });
        q('[data-tmp-grid]').addEventListener('click', function (e) {
            var cp = e.target.closest('[data-tmp-copy]');
            if (cp) { e.stopPropagation(); copyToLibrary(cp); return; }   // Copy button: don't toggle selection
            var tile = e.target.closest('[data-tmp-id]'); if (tile) { toggle(tile.getAttribute('data-tmp-id')); }
        });
        q('[data-tmp-ok]').addEventListener('click', confirm);
    }

    function load(reset) {
        if (busy) { return; }
        busy = true;
        if (reset) { offset = 0; rows = []; }
        var body = new URLSearchParams({ module:'media', service:'media', method:'datatable', draw:1, start:offset, length:GLEN });
        body.set('search[value]', modalEl.querySelector('[data-tmp-search]').value);
        body.set('kind', opts.kind || modalEl.querySelector('[data-tmp-kind]').value);
        fetch('/api', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                busy = false;
                if (!res || res.result !== 1 || !res.data) { return; }
                total = res.data.recordsFiltered || 0;
                rows = rows.concat(res.data.data || []);
                offset = rows.length;
                render();
            }).catch(function () { busy = false; });
    }

    function render() {
        var grid = modalEl.querySelector('[data-tmp-grid]');
        grid.innerHTML = rows.map(function (row) {
            var inner = (row.kind === 'image' && row.thumb)
                ? '<img src="' + esc(row.thumb) + '" alt="' + esc(row.filename || '') + '">'
                : '<div class="tmp-ico">' + icon(row.kind) + '<span class="small text-uppercase">' + esc(row.extension || row.kind) + '</span></div>';
            // Static (module-shipped) tiles: an origin badge + a Copy-to-library button (deliberate, never auto).
            var copy = row.is_static
                ? '<button type="button" class="tmp-copy" data-tmp-copy="' + esc(row.media_id) + '"' + (row.in_library ? ' disabled title="Already in your library"' : ' title="Copy to media library"') + '><i class="fa-solid ' + (row.in_library ? 'fa-check' : 'fa-download') + '"></i></button>'
                : '';
            var cls   = 'tmp-tile' + (picked[row.media_id] ? ' tmp-sel' : '') + (row.is_static ? ' tmp-static' : '');
            var badge = row.is_static ? ' data-badge="' + esc(row.source_name || 'Theme') + '"' : '';
            return '<div class="' + cls + '" data-tmp-id="' + esc(row.media_id) + '"' + badge + ' title="' + esc(row.filename || '') + '">' + inner + copy + '</div>';
        }).join('');
        modalEl.querySelector('[data-tmp-more]').hidden = rows.length >= total;
        modalEl.querySelector('[data-tmp-note]').hidden = rows.length > 0;
        modalEl.querySelector('[data-tmp-note]').textContent = 'No media yet — use Upload.';
        updateFooter();
    }

    function toggle(id) {
        var row = rows.filter(function (r) { return r.media_id === id; })[0];
        if (!row) { return; }
        if (opts.multiple) { if (picked[id]) { delete picked[id]; } else { picked[id] = row; } }
        else { picked = {}; picked[id] = row; }
        render();
    }

    function updateFooter() {
        var n = Object.keys(picked).length;
        modalEl.querySelector('[data-tmp-ok]').disabled = n === 0;
        modalEl.querySelector('[data-tmp-count]').textContent = n ? (n + ' selected') : '';
    }

    function confirm() {
        var items = Object.keys(picked).map(function (k) { return picked[k]; });
        if (!items.length) { return; }
        bs.hide();
        if (opts.onSelect) { opts.onSelect(opts.multiple ? items : items[0]); }
    }

    // Copy a static (module/theme-shipped) image into managed media, then reload so the durable copy
    // appears alongside it. Deliberate (a click) — the picker never auto-copies on select.
    function copyToLibrary(btn) {
        if (btn.disabled) { return; }
        btn.disabled = true;
        fetch('/api', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'},
            body:new URLSearchParams({ module:'media', service:'media', method:'copyToLibrary', media_id:btn.getAttribute('data-tmp-copy') }) })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.result === 1) {
                    if (global.TigerDOM) { TigerDOM.toast((res.data && res.data.existing) ? 'Already in your library.' : 'Copied to your media library.', { type:'success' }); }
                    load(true);
                } else {
                    btn.disabled = false;
                    if (global.TigerDOM) { TigerDOM.toast('Could not copy that file.', { type:'error' }); }
                }
            }).catch(function () { btn.disabled = false; });
    }

    function uploadFiles(files) {
        var note = modalEl.querySelector('[data-tmp-note]');
        note.hidden = false; note.textContent = 'Uploading…';
        var pending = files.length;
        Array.prototype.forEach.call(files, function (file) {
            var fd = new FormData();
            fd.append('module','media'); fd.append('service','media'); fd.append('method','upload'); fd.append('file', file);
            fetch('/api', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd })
                .then(function () {}).catch(function () {})
                .then(function () { if (--pending === 0) { note.textContent = ''; load(true); } });
        });
    }

    function open(o) {
        build();
        opts = o || {};
        picked = {}; rows = []; offset = 0;
        modalEl.querySelector('[data-tmp-search]').value = '';
        var kindSel = modalEl.querySelector('[data-tmp-kind]');
        kindSel.value = opts.kind || ''; kindSel.disabled = !!opts.kind;
        modalEl.querySelector('.modal-title').textContent = opts.title || 'Select media';
        updateFooter();
        bs.show();
        load(true);
    }

    // Auto-wire form fields: a [data-media-choose] button + a hidden input sibling.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-media-choose]');
        if (!btn) { return; }
        var field = btn.closest('[data-media-field]');
        if (!field) { return; }
        open({
            kind: btn.getAttribute('data-kind') || '',
            multiple: btn.getAttribute('data-multiple') === '1',
            onSelect: function (sel) {
                var items = Array.isArray(sel) ? sel : [sel];
                var input = field.querySelector('input[type="hidden"]');
                if (input) { input.value = items.map(function (i) { return i.media_id; }).join(','); input.dispatchEvent(new Event('change', { bubbles:true })); }
                var prev = field.querySelector('[data-media-preview]');
                if (prev) {
                    prev.innerHTML = items.map(function (i) {
                        return (i.kind === 'image' && i.thumb)
                            ? '<img src="' + esc(i.thumb) + '" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:6px;">'
                            : '<span class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded" style="width:56px;height:56px;">' + icon(i.kind) + '</span>';
                    }).join(' ');
                }
                var clr = field.querySelector('[data-media-clear]'); if (clr) { clr.hidden = false; }
            }
        });
    });
    document.addEventListener('click', function (e) {
        var clr = e.target.closest('[data-media-clear]');
        if (!clr) { return; }
        var field = clr.closest('[data-media-field]'); if (!field) { return; }
        var input = field.querySelector('input[type="hidden"]'); if (input) { input.value = ''; input.dispatchEvent(new Event('change', { bubbles:true })); }
        var prev = field.querySelector('[data-media-preview]'); if (prev) { prev.innerHTML = ''; }
        clr.hidden = true;
    });

    global.TigerMediaPicker = { open: open };
})(window, document);
