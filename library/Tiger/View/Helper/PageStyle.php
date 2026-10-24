<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_PageStyle — register a page-specific stylesheet from a view WITHOUT a `<style>` tag.
 *
 * The CSS counterpart to {@see Tiger_View_Helper_PageScript}. Tiger's house rule (AGENTS.md) forbids
 * `<style>` blocks and heavy `style="…"` in a `.phtml` view — styling is a skin's job, so a view stays
 * reskinnable. When a screen genuinely needs its own stylesheet (a builder canvas, a vendored widget),
 * ship it as an asset file and register it:
 *
 *   $this->pageStyle('/_shop/css/shop.css');
 *
 * The `<link>` lands in the `pageHead` slot the layout echoes inside `<head>`
 * (`themes/puma/layouts/scripts/*.phtml`). The href is cache-busted via `asset()` (remote URLs pass
 * through) and registrations are deduped by URL. For a one-off color use a Bootstrap utility class,
 * not this.
 *
 * One ordering caveat: the layout echoes `pageHead` inside `<head>`, which it renders BEFORE the body
 * content — so a stylesheet registered from a layout PARTIAL that the layout renders after `<head>`
 * (the public header, the consent banner) arrives too late. Register those from the view, or from the
 * layout above its `pageHead` echo.
 *
 * @api
 * @see Tiger_View_Helper_PageScript  the JS counterpart (the `pageScripts` slot)
 */
class Tiger_View_Helper_PageStyle extends Tiger_View_Helper_PageAsset_Abstract
{
    /**
     * Register a page-specific stylesheet into the layout's `pageHead` slot.
     *
     * @param  string $href root-relative asset path (cache-busted) or an absolute URL (passed through)
     * @return string       always '' (the tag goes to the slot, not the caller) — safe to echo
     */
    public function pageStyle($href = null)
    {
        return $this->_append('pageHead', $href, static function ($url) {
            return '<link rel="stylesheet" href="' . $url . "\">\n";
        });
    }
}
