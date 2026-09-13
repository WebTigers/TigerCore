/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
 *
 * Tiger.t() — the client half of localization (themes/puma/assets/js/tiger.i18n.js).
 *
 * Every module's JavaScript goes through this one function, and until TIGER-121 nothing tested it.
 * Plain node + assert, no framework: the file is an IIFE that reads a <div id="tiger-i18n"> carrier,
 * so a fake window/document is enough.
 *
 *   node tests/js/tiger.i18n.test.js
 */
'use strict';

const assert = require('assert');
const fs     = require('fs');
const path   = require('path');
const vm     = require('vm');

/** Load tiger.i18n.js against a carrier holding the given strings; return the Tiger global. */
function boot(strings) {
    const carrier = strings === null ? null : {
        getAttribute: (name) => name === 'data-strings' ? JSON.stringify(strings) : null,
    };
    const window   = {};
    const document = { getElementById: (id) => (id === 'tiger-i18n' ? carrier : null) };
    const src = fs.readFileSync(path.join(__dirname, '../../themes/puma/assets/js/tiger.i18n.js'), 'utf8');
    vm.runInNewContext(src, { window, document });
    return window.Tiger;
}

let passed = 0;
function test(name, fn) {
    try { fn(); passed++; console.log('  ok   ' + name); }
    catch (e) { console.error('  FAIL ' + name + '\n       ' + e.message); process.exitCode = 1; }
}

/* ---- resolution ---------------------------------------------------------------------------- */

test('resolves a registered alias to its translated value', () => {
    const T = boot({ saved: 'Página guardada.' });
    assert.strictEqual(T.t('saved'), 'Página guardada.');
});

test('an unregistered alias comes back AS the alias — visible, never blank or undefined', () => {
    const T = boot({});
    assert.strictEqual(T.t('nope'), 'nope');
});

test('has() reports whether an alias was delivered', () => {
    const T = boot({ yes: 'x' });
    assert.strictEqual(T.has('yes'), true);
    assert.strictEqual(T.has('no'), false);
});

test('no carrier at all degrades to alias passthrough, not a throw', () => {
    const T = boot(null);
    assert.strictEqual(T.t('anything'), 'anything');
});

/* ---- placeholders: the TIGER-121 contract -------------------------------------------------- */

test('sequential %s is filled in order (single-argument strings need no ceremony)', () => {
    const T = boot({ greet: 'Hola, %s.' });
    assert.strictEqual(T.t('greet', 'Ada'), 'Hola, Ada.');
});

test('numbered %1$s / %2$s are filled by position', () => {
    const T = boot({ note: '%1$s steps — this image is #%2$s in the chain.' });
    assert.strictEqual(T.t('note', 3, 2), '3 steps — this image is #2 in the chain.');
});

test('a translator may REORDER numbered placeholders — the reason they exist', () => {
    const T = boot({ note: 'Image #%2$s of %1$s in the chain.' });
    assert.strictEqual(T.t('note', 3, 2), 'Image #2 of 3 in the chain.');
});

test('four positional arguments — the trust dialog', () => {
    const T = boot({ trust: '%1$s is sold by %2$s. Verified against key %3$s. %4$s' });
    assert.strictEqual(T.t('trust', 'TigerShield', 'WebTigers', 'ab12', 'Trusted once.'),
        'TigerShield is sold by WebTigers. Verified against key ab12. Trusted once.');
});

test('%d is filled the same way as %s, numbered or not', () => {
    const T = boot({ seats: '%1$d / %2$d seats', plain: '%d of %d' });
    assert.strictEqual(T.t('seats', 3, 10), '3 / 10 seats');
    assert.strictEqual(T.t('plain', 3, 10), '3 of 10');
});

test('%% is a literal percent and consumes no argument', () => {
    const T = boot({ pct: '100%% of %s' });
    assert.strictEqual(T.t('pct', 'them'), '100% of them');
});

test('a missing argument leaves the placeholder VISIBLE rather than printing "undefined"', () => {
    const T = boot({ two: '%1$s and %2$s', seq: '%s and %s' });
    assert.strictEqual(T.t('two', 'one'), 'one and %2$s');
    assert.strictEqual(T.t('seq', 'one'), 'one and %s');
});

test('arguments are stringified, so 0 and false render rather than vanishing', () => {
    const T = boot({ n: 'count: %1$s, flag: %2$s' });
    assert.strictEqual(T.t('n', 0, false), 'count: 0, flag: false');
});

console.log('\n' + passed + ' passed' + (process.exitCode ? ', with failures' : ''));
