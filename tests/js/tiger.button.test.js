/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
 *
 * TigerButton busy state (themes/puma/assets/js/tiger.button.js) — where the temporary spinner lands.
 *
 * A button with no FontAwesome icon gets a spinner injected. Prepending it to the button breaks any
 * button with its own internal layout (the Google sign-in button: an absolutely positioned state
 * layer plus a flex content wrapper — the spinner became a third child and the label wrapped onto a
 * second line). data-tg-busy-host names the element the spinner goes into instead.
 *
 *   node tests/js/tiger.button.test.js
 */
'use strict';

const assert = require('assert');
const fs     = require('fs');
const path   = require('path');
const vm     = require('vm');

/** Minimal element: enough of the DOM for busy()/done() — children, querySelector by attribute/tag. */
function el(tag, attrs, children) {
    const node = {
        tagName: tag, className: '', attrs: Object.assign({}, attrs || {}), children: children || [],
        dataset: {}, disabled: false, textContent: (attrs && attrs.text) || '',
        setAttribute(k, v) { this.attrs[k] = String(v); }, removeAttribute(k) { delete this.attrs[k]; },
        appendChild(c) { c.parent = this; this.children.push(c); return c; },
        insertBefore(c, ref) { c.parent = this; const i = this.children.indexOf(ref); this.children.splice(i < 0 ? this.children.length : i, 0, c); return c; },
        remove() { const i = this.parent.children.indexOf(this); this.parent.children.splice(i, 1); },
        querySelector(sel) {
            const walk = (n) => { for (const c of n.children) { if (match(c, sel)) { return c; } const d = walk(c); if (d) { return d; } } return null; };
            return walk(this);
        },
    };
    node.children.forEach((c) => { c.parent = node; });
    return node;
}
function match(n, sel) {
    return sel.split(',').map((s) => s.trim()).some((s) => {
        if (s[0] === '[') { return n.attrs[s.slice(1, -1)] != null; }
        if (s.indexOf('.') === 0) { return (' ' + n.className + ' ').indexOf(' ' + s.slice(1) + ' ') >= 0; }
        return n.tagName === s.split('.')[0] && (s.indexOf('.') < 0 || match(n, '.' + s.split('.').slice(1).join('.')));
    });
}

function boot() {
    const document = { createElement: (t) => el(t) };
    const window = { performance: { now: () => 0 } };
    const src = fs.readFileSync(path.join(__dirname, '../../themes/puma/assets/js/tiger.button.js'), 'utf8');
    vm.runInNewContext(src, { window, document, Promise, setTimeout });
    return window.TigerButton;
}

let passed = 0;
function test(name, fn) {
    try { fn(); passed++; console.log('  ok   ' + name); }
    catch (e) { console.error('  FAIL ' + name + '\n       ' + e.message); process.exitCode = 1; }
}

test('a plain text button gets the spinner prepended to the button itself (unchanged behaviour)', () => {
    const TB = boot();
    const btn = el('button', { text: 'Save' });
    TB.busy(btn);
    assert.strictEqual(btn.disabled, true);
    assert.strictEqual(btn.children.length, 1);
    assert.strictEqual(btn.children[0].attrs['data-tg-injected'], '1');
    assert.ok(/fa-spinner/.test(btn.children[0].className));
    assert.ok(/\bme-2\b/.test(btn.children[0].className), 'leading spinner spaces to the right');
});

test('with data-tg-busy-host the spinner is appended INSIDE the host, not prepended to the button', () => {
    const TB = boot();
    const label = el('span', { 'data-tg-busy-host': '', text: 'Continue with Google' });
    const wrapper = el('span', {}, [el('span', { class: 'icon' }), label]);
    const btn = el('button', { text: 'Continue with Google' }, [el('span', { class: 'state' }), wrapper]);
    TB.busy(btn);
    assert.strictEqual(btn.children.length, 2, 'button children untouched — the layout is intact');
    assert.strictEqual(label.children.length, 1);
    const spin = label.children[0];
    assert.strictEqual(spin.attrs['data-tg-injected'], '1');
    assert.ok(/fa-spinner/.test(spin.className));
    assert.ok(/\bms-2\b/.test(spin.className), 'trailing spinner spaces to the left');
});

test('done() removes the hosted spinner and re-enables the button', () => {
    const TB = boot();
    const label = el('span', { 'data-tg-busy-host': '', text: 'Continue with Google' });
    const btn = el('button', { text: 'Continue with Google' }, [label]);
    TB.busy(btn); TB.done(btn);
    assert.strictEqual(label.children.length, 0);
    assert.strictEqual(btn.disabled, false);
    assert.strictEqual(btn.attrs['aria-busy'], undefined);
});

test('a button with a FontAwesome icon still swaps the icon and injects nothing', () => {
    const TB = boot();
    const ic = el('i', {}); ic.className = 'fa-solid fa-floppy-disk me-2';
    const btn = el('button', { text: 'Save' }, [ic]);
    TB.busy(btn);
    assert.strictEqual(btn.children.length, 1);
    assert.strictEqual(ic.className, 'me-2 fa-solid fa-spinner fa-spin');
    TB.done(btn);
    assert.strictEqual(ic.className, 'fa-solid fa-floppy-disk me-2');
});

console.log(process.exitCode ? 'FAILED' : 'ALL PASSED (' + passed + ')');
