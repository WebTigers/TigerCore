/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
 *
 * TigerModal (themes/puma/assets/js/tiger.modal.js) — a dialog chained off another dialog's answer.
 *
 * Bootstrap animates a modal closed, and show() called during that hide is ignored. TigerModal reuses
 * ONE modal element, so `confirm(...).then(() => confirm(...))` opened the second dialog while the first
 * was still closing: it never appeared, and the first dialog's hidden.bs.modal settled the SECOND
 * promise as a cancel. The Module Manager's install consent → "trust this vendor?" chain died silently
 * this way (2026-11-16). The second dialog must wait for the first to finish closing, then show.
 *
 *   node tests/js/tiger.modal.test.js
 */
'use strict';

const assert = require('assert');
const fs     = require('fs');
const path   = require('path');
const vm     = require('vm');

/** A DOM element with the bits tiger.modal.js touches: attributes, classList, events, querySelector by data-tm. */
function makeEl() {
    const listeners = {};
    const fields = {};
    const field = (k) => (fields[k] = fields[k] || {
        textContent: '', value: '', placeholder: '', className: '', checked: false,
        classList: { toggle() {}, add() {}, remove() {} },
        addEventListener(t, f) { (this._l = this._l || {})[t] = f; },
        click() { this._l && this._l.click && this._l.click(); },
        focus() {}, select() {},
    });
    return {
        className: '', tabIndex: 0, innerHTML: '',
        setAttribute() {},
        addEventListener(t, f) { (listeners[t] = listeners[t] || []).push(f); },
        dispatch(t) { (listeners[t] || []).forEach((f) => f()); },
        querySelector(sel) { const m = /data-tm="([^"]+)"/.exec(sel); return field(m[1]); },
    };
}

/** A fake bootstrap.Modal: hide() fires hide.bs.modal now and hidden.bs.modal after the "animation"; show() mid-hide is ignored, as in Bootstrap. */
function load() {
    let el = null, inst = null, state = 'hidden', shows = 0;
    const flush = [];
    const Modal = {
        getOrCreateInstance(e) {
            el = e;
            return inst = {
                show() { if (state === 'hiding') { return; } state = 'shown'; shows++; },
                hide() {
                    if (state !== 'shown') { return; }
                    state = 'hiding'; el.dispatch('hide.bs.modal');
                    flush.push(() => { state = 'hidden'; el.dispatch('hidden.bs.modal'); });
                },
            };
        },
    };
    const window = { bootstrap: { Modal } };
    const document = { createElement: makeEl, body: { appendChild() {} } };
    const src = fs.readFileSync(path.join(__dirname, '../../themes/puma/assets/js/tiger.modal.js'), 'utf8');
    vm.runInNewContext(src, { window, document });
    return {
        TigerModal: window.TigerModal,
        confirmClick() { el.querySelector('[data-tm="confirm"]').click(); },
        dismiss() { inst.hide(); },       // Cancel / ✕ / Esc / backdrop: a hide with no confirm
        finishAnimation() { while (flush.length) { flush.shift()(); } },
        shows: () => shows, state: () => state,
    };
}

const tick = () => new Promise((r) => setImmediate(r));

(async () => {
    // 1. A dialog opened from another's .then() waits for the hide, then shows, and resolves on its own answer.
    {
        const t = load();
        let second = 'pending';
        const first = t.TigerModal.confirm({ title: 'Install?' });
        first.then((ok) => { assert.strictEqual(ok, true); t.TigerModal.confirm({ title: 'Trust?' }).then((v) => { second = v; }); });
        t.confirmClick();                 // confirm the first: settles true, starts the hide
        await tick();                     // the .then runs mid-hide and asks for the second dialog
        assert.strictEqual(t.shows(), 1, 'the second dialog must not try to show while the first is closing');
        t.finishAnimation();              // first dialog gone → the queued second opens
        await tick();
        assert.strictEqual(t.shows(), 2, 'the second dialog opens once the first has closed');
        assert.strictEqual(second, 'pending', "the first dialog's close must not answer the second");
        t.confirmClick(); t.finishAnimation(); await tick();
        assert.strictEqual(second, true, 'the second dialog resolves on its own confirm');
    }

    // 2. A single dialog is unchanged: dismissing it resolves false; confirming resolves true.
    {
        const t = load();
        let v = 'pending';
        t.TigerModal.confirm({ title: 'Remove?' }).then((x) => { v = x; });
        assert.strictEqual(t.shows(), 1);
        await tick();
        assert.strictEqual(v, 'pending', 'an open dialog stays unanswered until the user acts');
        t.dismiss(); t.finishAnimation(); await tick();
        assert.strictEqual(v, false, 'a dismissal resolves the cancel value');
        let w = 'pending';
        t.TigerModal.confirm({ title: 'Again?' }).then((x) => { w = x; });
        assert.strictEqual(t.shows(), 2, 'a dialog opened after the last one closed shows immediately');
        t.confirmClick(); t.finishAnimation(); await tick();
        assert.strictEqual(w, true);
    }

    console.log('tiger.modal: all assertions passed');
})().catch((e) => { console.error(e); process.exit(1); });
