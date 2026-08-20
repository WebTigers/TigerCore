<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_I18n — hand the page's JS ONLY the localized strings it needs, WITHOUT an inline
 * `<script>` blob. The house rule (AGENTS.md / Tiger_View_Helper_PageScript): a view owns markup, never
 * behavior — server→JS data rides on a `data-*` attribute read by an asset file, never an inline script.
 *
 * Two modes, one helper:
 *
 *   // In a view (or a partial) — REGISTER the keys this page's JS references, aliased:
 *   <?php $this->i18n(['saved' => 'cms.page.saved', 'confirmDel' => 'cms.page.confirm_delete']); ?>
 *
 *   // In the LAYOUT (once, before the scripts) — EMIT the carrier the reader parses:
 *   <?= $this->i18n() ?>
 *   // → <div id="tiger-i18n" data-strings="{&quot;saved&quot;:&quot;Página guardada.&quot;,...}" hidden></div>
 *
 * The shared `tiger.i18n.js` asset reads that carrier once and exposes `Tiger.t('alias', ...args)`. This is
 * the deliberate ANTI-pattern to WordPress's global `wp_localize_script` dump:
 *
 *   - **Per-page, necessary keys only** — the page ships the handful of strings its own JS uses, not the
 *     app-wide catalog. Nothing to enumerate.
 *   - **Values, not keys** — the payload is translated user-visible TEXT under generic aliases, so viewing
 *     source never exposes the semantic `<module>.<area>.<type>` key structure or unshipped strings.
 *   - **No inline script** — data on a `data-*` attribute, logic in an asset (`tiger.i18n.js`). CSP-clean.
 *   - **Injection-safe** — the JSON is attribute-escaped (ENT_QUOTES), so a value can't break the attribute
 *     or the element even for an admin-authored DB translation override.
 *
 * Registration accumulates in a STATIC bag (survives ZF1's per-partial view clone), deduped by alias, so a
 * view and its partials contribute to one carrier the layout emits.
 *
 * @api
 * @see Tiger_View_Helper_T           the scalar `$this->t('key')` twin for server-rendered markup
 * @see Tiger_View_Helper_PageScript  the same view-registers / layout-emits seam for script files
 */
class Tiger_View_Helper_I18n extends Zend_View_Helper_Abstract
{
    /** @var array<string,string> alias => localized value, accumulated across the request (view + partials). */
    protected static $_bag = [];

    /**
     * Register keys (array) or emit the carrier (no args).
     *
     * @param  array|null $map alias => translation-key to register; a list uses each key's last dot-segment
     *                         as the alias. Pass null (the layout) to render the data carrier.
     * @return string          '' when registering; the carrier `<div>` (or '' if nothing was registered) when emitting
     */
    public function i18n($map = null)
    {
        if ($map === null) {
            return $this->_carrier();
        }
        foreach ((array) $map as $alias => $key) {
            $key = (string) $key;
            if (is_int($alias)) {                          // list form: derive the alias from the key's tail
                $parts = explode('.', $key);
                $alias = end($parts);
            }
            self::$_bag[(string) $alias] = $this->view->t($key);   // translate now, in the active locale
        }
        return '';
    }

    /** The hidden data carrier the reader parses — or '' when no page registered any strings. */
    protected function _carrier()
    {
        if (!self::$_bag) {
            return '';
        }
        $json = json_encode(self::$_bag, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<div id="tiger-i18n" data-strings="' . htmlspecialchars($json, ENT_QUOTES) . '" hidden></div>';
    }

    /** Reset the bag — for tests (a web request is one process/one page, so it self-clears). */
    public static function reset()
    {
        self::$_bag = [];
    }
}
