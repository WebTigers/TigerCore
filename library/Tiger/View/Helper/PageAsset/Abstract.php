<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_PageAsset_Abstract — the shared slot mechanics behind `pageScript()`/`pageStyle()`.
 *
 * Both helpers do the same three things: cache-bust the URL, dedupe it, and append one tag to a string
 * slot the theme LAYOUT echoes (`pageScripts` before `</body>`, `pageHead` inside `<head>`). The part
 * worth a base class is the one that is easy to get wrong:
 *
 * **A partial renders against a CLONE of the view.** `Zend_View_Helper_Partial::cloneView()` does
 * `clone $this->view` + `clearVars()`, and ZF1 only binds a helper to a view the first time that view
 * instantiates it. So a `pageScript()` whose FIRST ever call happens inside a partial is bound to the
 * clone, writes the slot on the clone, and the clone is thrown away — the tag never reaches the layout
 * and the page's JS silently never loads, behind a 200. (Pre-instantiated by the parent it happens to
 * work, which is worse: it depends on call order.) So the slot is written on every DISTINCT view in
 * play — the bound one and the MVC view the layout renders against — and dedupe is STATIC so the pair
 * can't double-emit. Writing a var on a discarded clone is harmless; missing the real view is not.
 *
 * The slot stays a plain view var rather than a placeholder so that every theme layout already echoing
 * `$this->pageScripts` / `$this->pageHead` — including themes shipped outside this repo — keeps working
 * with no change.
 *
 * @internal shared implementation; the public API is pageScript() / pageStyle()
 * @see Tiger_View_Helper_PageScript
 * @see Tiger_View_Helper_PageStyle
 */
abstract class Tiger_View_Helper_PageAsset_Abstract extends Zend_View_Helper_Abstract
{
    /** @var array<string,bool> "<slot>\0<url>" => true — registered this request, across views + partials. */
    protected static $_seen = [];

    /**
     * Cache-bust, dedupe, and append one tag to a layout slot on every view in play.
     *
     * @param  string $slot view var the layout echoes (`pageScripts` / `pageHead`)
     * @param  string $src  root-relative asset path (cache-busted) or an absolute URL (passed through)
     * @param  callable $tag fn(string $escapedUrl): string — builds the tag
     * @return string       always '' (the tag goes to the slot, not the caller) — safe to echo
     */
    protected function _append($slot, $src, callable $tag)
    {
        $src = (string) $src;
        if ($src === '') { return ''; }

        $url = htmlspecialchars($this->view->asset($src), ENT_QUOTES);
        $key = $slot . "\0" . $url;
        if (isset(self::$_seen[$key])) { return ''; }
        self::$_seen[$key] = true;

        $html = $tag($url);
        foreach ($this->_views() as $view) {
            $view->$slot = (string) ($view->$slot ?? '') . $html;
        }
        return '';
    }

    /**
     * Every DISTINCT view whose slot must carry the tag: the one this helper is bound to, plus the MVC
     * view the layout renders against when that is a different object (the partial-clone case above).
     * Outside an MVC request — a unit test, the CLI — only the bound view exists.
     *
     * @return array<int,object>
     */
    protected function _views()
    {
        $views = [$this->view];
        try {
            $layout = class_exists('Zend_Layout', false) ? Zend_Layout::getMvcInstance() : null;
            $mvc    = $layout ? $layout->getView() : null;
            if ($mvc !== null && $mvc !== $this->view) { $views[] = $mvc; }
        } catch (Throwable $e) {
            // No MVC layout in play (CLI, a test, a disabled layout) — the bound view is all there is.
        }
        return $views;
    }

    /** Forget what has been registered — for tests (a web request is one process / one page). @return void */
    public static function reset()
    {
        self::$_seen = [];
    }
}
