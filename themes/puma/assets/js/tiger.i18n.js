// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.i18n.js — the client half of Tiger's localization. Reads the per-page string carrier the layout
 * emitted (Tiger_View_Helper_I18n) and exposes `Tiger.t(alias, ...args)`.
 *
 *   const carrier = <div id="tiger-i18n" data-strings='{"saved":"Página guardada.","confirmDel":"…"}' hidden>
 *   Tiger.t('saved')                 → "Página guardada."
 *   Tiger.t('greet', 'Ada')          → uses the value's %s placeholders: "Hola, Ada."
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
     * Resolve a page string by alias, filling %s placeholders with any trailing args.
     * @param {string} alias  the alias the view registered via $this->i18n([...])
     * @returns {string}      the localized string, or the alias itself when not present
     */
    w.Tiger.t = function (alias) {
        var args = arguments;
        var s = Object.prototype.hasOwnProperty.call(dict, alias) ? dict[alias] : alias;
        var i = 1;
        return String(s).replace(/%s/g, function () {
            return i < args.length ? args[i++] : '%s';
        });
    };

    /** Whether an alias was delivered to this page (for optional guards). */
    w.Tiger.has = function (alias) {
        return Object.prototype.hasOwnProperty.call(dict, alias);
    };
})(window, document);
