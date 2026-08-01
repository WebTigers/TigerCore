<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_PageScript — register a page-specific JS file from a view WITHOUT a `<script>` tag.
 *
 * Tiger's house rule (see AGENTS.md): a `.phtml` view owns MARKUP, never behavior — so no `<script>`
 * blocks and no `<script src>` includes in a view. Behavior lives in an asset file; the view merely
 * *registers* it, and the theme LAYOUT is the one place a `<script>` tag is emitted.
 *
 * This helper is that seam. A view calls:
 *
 *   $this->pageScript('/_shop/js/shop.subscribe.js');       // a module/theme asset (cache-busted)
 *   $this->pageScript('https://js.stripe.com/v3/');         // an external SDK (passed through)
 *   $this->pageScript('/_theme/js/foo.js', ['defer' => true, 'type' => 'module']);
 *
 * and the tag is appended to the `pageScripts` slot the layout already echoes before `</body>`
 * (`themes/puma/layouts/scripts/*.phtml`). The URL is run through `asset()` for cache-busting
 * (remote URLs pass through untouched), and registrations are **deduped** by URL so two views (or a
 * view + its partial) requesting the same file emit one tag. Pass server data to the script via
 * `data-*` attributes on a container element — never an inline `<script>` blob.
 *
 * @api
 * @see Tiger_View_Helper_PageStyle  the CSS counterpart (the `pageHead` slot)
 * @see Tiger_View_Helper_Asset      the cache-bust token
 */
class Tiger_View_Helper_PageScript extends Zend_View_Helper_Abstract
{
    /**
     * Register a page-specific script into the layout's `pageScripts` slot.
     *
     * @param  string $src   root-relative asset path (cache-busted) or an absolute URL (passed through)
     * @param  array  $attrs optional: `defer` / `async` (bool) and `type` (e.g. `module`)
     * @return string        always '' (the tag goes to the slot, not the caller) — safe to echo
     */
    public function pageScript($src = null, array $attrs = [])
    {
        $src = (string) $src;
        if ($src === '') { return ''; }

        $url      = htmlspecialchars($this->view->asset($src), ENT_QUOTES);
        $existing = (string) ($this->view->pageScripts ?? '');
        if (strpos($existing, 'src="' . $url . '"') !== false) { return ''; }   // dedup by URL

        $extra = '';
        if (!empty($attrs['type']))  { $extra .= ' type="' . htmlspecialchars((string) $attrs['type'], ENT_QUOTES) . '"'; }
        if (!empty($attrs['defer'])) { $extra .= ' defer'; }
        if (!empty($attrs['async'])) { $extra .= ' async'; }

        $this->view->pageScripts = $existing . '<script src="' . $url . '"' . $extra . "></script>\n";
        return '';
    }
}
