/*! SPDX-License-Identifier: BSD-3-Clause · © 2026 WebTigers · Tiger™/WebTigers™ are trademarks */
/**
 * tiger.modal.js — in-app confirm / prompt / alert dialogs (TigerModal).
 *
 * The house replacement for the browser's native confirm()/prompt()/alert() — which are unstyled,
 * un-themeable, and freeze the whole tab. TigerModal returns a Promise and renders inside the theme,
 * so a control's "are you sure?" or "name this thing" moment looks like the rest of the product.
 *
 *   TigerModal.confirm({ title, body, confirmLabel, cancelLabel, variant }) -> Promise<boolean>
 *   TigerModal.prompt({ title, label, value, placeholder, help, confirmLabel, variant }) -> Promise<string|null>
 *   TigerModal.alert({ title, body, confirmLabel, variant }) -> Promise<void>
 *
 * confirm resolves true (confirmed) or false (Cancel / ✕ / Esc / backdrop). prompt resolves the entered
 * string (raw — the caller trims) or null when cancelled, so a call site's `if (v === null) return;`
 * mirrors window.prompt exactly. Enter in the field submits; the field focuses (and selects) on open.
 *
 *   TigerModal.confirm({ title: 'Remove card', body: 'Remove this card?', confirmLabel: 'Remove', variant: 'danger' })
 *       .then(function (ok) { if (!ok) { return; } ... });
 *   TigerModal.prompt({ title: 'Name this card', label: 'Card name', value: current })
 *       .then(function (name) { if (name === null) { return; } ... });
 *
 * One reusable Bootstrap modal is built lazily on first use and injected into the DOM (the same
 * build-the-element-on-demand move as TigerDOM.notify), so a view needs no modal markup. Depends only
 * on Bootstrap's bundle (bootstrap.Modal) — loaded ahead of this in every layout — never on jQuery/TigerDOM.
 *
 * @api
 */
(function () {
    'use strict';
    if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) { return; }

    var el = null, modal = null, resolver = null, mode = 'confirm';

    function q(sel) { return el.querySelector('[data-tm="' + sel + '"]'); }

    // Resolve the pending promise exactly once. A confirm/OK settles its value BEFORE hiding, so the
    // subsequent hidden.bs.modal (which would settle the cancel value) finds no resolver and no-ops.
    function settle(value) {
        if (!resolver) { return; }
        var r = resolver; resolver = null; r(value);
    }

    function build() {
        if (el) { return; }
        el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
          +   '<div class="modal-header"><h5 class="modal-title" data-tm="title"></h5>'
          +     '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>'
          +   '<div class="modal-body">'
          +     '<p class="mb-3" data-tm="body"></p>'
          +     '<div class="d-none" data-tm="field">'
          +       '<label class="form-label" data-tm="label"></label>'
          +       '<input type="text" class="form-control" data-tm="input" autocomplete="off">'
          +       '<div class="form-text" data-tm="help"></div>'
          +     '</div>'
          +   '</div>'
          +   '<div class="modal-footer">'
          +     '<button type="button" class="btn btn-light" data-tm="cancel" data-bs-dismiss="modal"></button>'
          +     '<button type="button" class="btn" data-tm="confirm"></button>'
          +   '</div>'
          + '</div></div>';
        document.body.appendChild(el);
        modal = window.bootstrap.Modal.getOrCreateInstance(el);

        q('confirm').addEventListener('click', function () {
            settle(mode === 'prompt' ? q('input').value : true);
            modal.hide();
        });
        q('input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); q('confirm').click(); }
        });
        // Any dismissal that isn't a confirm (Cancel, ✕, Esc, backdrop) resolves the cancel value.
        el.addEventListener('hidden.bs.modal', function () {
            settle(mode === 'prompt' ? null : (mode === 'alert' ? undefined : false));
        });
        el.addEventListener('shown.bs.modal', function () {
            if (mode === 'prompt') { var i = q('input'); i.focus(); i.select(); }
            else { q('confirm').focus(); }
        });
    }

    function open(opts, kind) {
        build();
        settle(kind === 'prompt' ? null : (kind === 'alert' ? undefined : false));   // cancel any dialog still open
        mode = kind;

        q('title').textContent = opts.title || (kind === 'prompt' ? 'Enter a value' : (kind === 'alert' ? 'Notice' : 'Are you sure?'));
        var body = q('body');
        body.textContent = opts.body || '';
        body.classList.toggle('d-none', !opts.body);

        var cancel = q('cancel');
        cancel.textContent = opts.cancelLabel || 'Cancel';
        cancel.classList.toggle('d-none', kind === 'alert');   // an alert is a single-button acknowledgement

        var conf = q('confirm');
        conf.textContent = opts.confirmLabel || (kind === 'prompt' ? 'Save' : (kind === 'alert' ? 'OK' : 'Confirm'));
        conf.className = 'btn btn-' + (opts.variant || 'primary');

        var field = q('field');
        if (kind === 'prompt') {
            field.classList.remove('d-none');
            var label = q('label');
            label.textContent = opts.label || '';
            label.classList.toggle('d-none', !opts.label);
            var input = q('input');
            input.value = opts.value != null ? String(opts.value) : '';
            input.placeholder = opts.placeholder || '';
            var help = q('help');
            help.textContent = opts.help || '';
            help.classList.toggle('d-none', !opts.help);
        } else {
            field.classList.add('d-none');
        }

        return new Promise(function (resolve) { resolver = resolve; modal.show(); });
    }

    window.TigerModal = {
        confirm: function (opts) { return open(opts || {}, 'confirm'); },
        prompt:  function (opts) { return open(opts || {}, 'prompt'); },
        alert:   function (opts) { return open(opts || {}, 'alert'); }
    };
})();
