// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.i18n.js — the client half of Tiger's localization. Reads the per-page string carrier the layout
 * emitted (Tiger_View_Helper_I18n) and exposes `Tiger.t(alias, ...args)`.
 *
 *   const carrier = <div id="tiger-i18n" data-strings='{"saved":"Página guardada.","confirmDel":"…"}' hidden>
 *   Tiger.t('saved')                 → "Página guardada."
 *   Tiger.t('greet', 'Ada')          → fills the value's placeholders: "Hola, Ada."
 *   Tiger.t('seats', 3, 10)          → numbered `%1$d / %2$d` — reorderable by the translator
 *   Tiger.t('unknownAlias')          → "unknownAlias"  (fail-soft: the alias, never blank/undefined)
 *
 * The design (see the helper docblock): only the strings THIS page uses ship, as translated VALUES under
 * generic aliases — no global dictionary, no key catalog exposed, no enumerable endpoint. Behavior lives
 * here (an asset), data rides a data-* attribute — Tiger's house rule, not an inline <script> blob.
 *
 * Loaded before the per-page scripts in every layout, so a view's DOMContentLoaded handler can call
 * Tiger.t() freely.
 */
(function (w, d) {
    'use strict';

    var dict = {};
    var el = d.getElementById('tiger-i18n');
    if (el) {
        try { dict = JSON.parse(el.getAttribute('data-strings') || '{}') || {}; }
        catch (e) { dict = {}; }   // a malformed carrier degrades to alias-passthrough, never throws
    }

    w.Tiger = w.Tiger || {};

    /**
     * Resolve a page string by alias, filling its placeholders with any trailing args.
     *
     * NUMBERED placeholders (`%1$s`, `%2$d`) are the ones to write whenever a string takes more than
     * one argument, because only those let a translator reorder them — sequential `%s` locks every
     * language into English word order, and that is not a preference, it is a defect that surfaces
     * only in the languages nobody on the team reads. Sequential is still filled, in order, so the
     * strings that take a single argument need no ceremony.
     *
     * `%%` is a literal percent. Matches PHP's sprintf, which is what the server half uses, so a
     * string reads the same whichever side renders it.
     *
     * @param {string} alias  the alias the view registered via $this->i18n([...])
     * @returns {string}      the localized string, or the alias itself when not present
     */
    w.Tiger.t = function (alias) {
        var args = arguments;
        var s    = Object.prototype.hasOwnProperty.call(dict, alias) ? dict[alias] : alias;
        var next = 1;
        return String(s).replace(/%%|%(\d+)\$[sd]|%[sd]/g, function (m, num) {
            if (m === '%%') { return '%'; }
            var i = num ? Number(num) : next++;
            // An argument that was never passed leaves the placeholder visible rather than printing
            // "undefined" — a visible %2$s is a bug report; "undefined" is a mystery.
            return i < args.length ? String(args[i]) : m;
        });
    };

    /** Whether an alias was delivered to this page (for optional guards). */
    w.Tiger.has = function (alias) {
        return Object.prototype.hasOwnProperty.call(dict, alias);
    };
})(window, document);
