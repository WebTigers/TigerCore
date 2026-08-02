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
 * @api
 * @see Tiger_View_Helper_PageScript  the JS counterpart (the `pageScripts` slot)
 */
class Tiger_View_Helper_PageStyle extends Zend_View_Helper_Abstract
{
    /**
     * Register a page-specific stylesheet into the layout's `pageHead` slot.
     *
     * @param  string $href root-relative asset path (cache-busted) or an absolute URL (passed through)
     * @return string       always '' (the tag goes to the slot, not the caller) — safe to echo
     */
    public function pageStyle($href = null)
    {
        $href = (string) $href;
        if ($href === '') { return ''; }

        $url      = htmlspecialchars($this->view->asset($href), ENT_QUOTES);
        $existing = (string) ($this->view->pageHead ?? '');
        if (strpos($existing, 'href="' . $url . '"') !== false) { return ''; }   // dedup by URL

        $this->view->pageHead = $existing . '<link rel="stylesheet" href="' . $url . "\">\n";
        return '';
    }
}
