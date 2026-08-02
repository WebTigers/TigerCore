<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_View_Helper_PageScript;
use Tiger_View_Helper_PageStyle;

/**
 * Tiger_View_Helper_PageScript / _PageStyle — the seam that lets a `.phtml` view register a JS/CSS
 * asset WITHOUT emitting a `<script>`/`<style>` tag itself (Tiger's house rule; the layout is the one
 * place a tag is emitted). A view calls `$this->pageScript($url)` / `$this->pageStyle($url)` and the
 * tag is appended to the `pageScripts` / `pageHead` slot the layout echoes.
 *
 * Pure helpers: they read `$this->view->asset()` for cache-busting and append to a string view var, so
 * these tests drive a tiny stub view (asset() = identity) and assert the slot contents — the tag shape,
 * cache-bust pass-through of a remote URL, the optional attributes, and dedup by URL.
 */
#[CoversClass(Tiger_View_Helper_PageScript::class)]
#[CoversClass(Tiger_View_Helper_PageStyle::class)]
final class PageAssetHelpersTest extends UnitTestCase
{
    /** A minimal view double: an `asset()` that returns its argument, plus the two string slots. */
    private function view(): object
    {
        return new class {
            public string $pageScripts = '';
            public string $pageHead = '';
            public function asset($p) { return $p; }   // cache-bust is asset()'s job; identity here
        };
    }

    #[Test]
    public function page_script_appends_a_script_tag_to_the_pageScripts_slot(): void
    {
        $view = $this->view();
        $h = new Tiger_View_Helper_PageScript();
        $h->view = $view;

        $this->assertSame('', $h->pageScript('/_shop/js/shop.subscribe.js'), 'returns nothing — the tag goes to the slot');
        $this->assertStringContainsString('<script src="/_shop/js/shop.subscribe.js"></script>', $view->pageScripts);
    }

    #[Test]
    public function page_script_dedups_by_url_and_passes_a_remote_url_through(): void
    {
        $view = $this->view();
        $h = new Tiger_View_Helper_PageScript();
        $h->view = $view;

        $h->pageScript('/_shop/js/shop.cart.js');
        $h->pageScript('/_shop/js/shop.cart.js');   // a second view/partial asks for the same file
        $this->assertSame(1, substr_count($view->pageScripts, '/_shop/js/shop.cart.js'), 'registered once');

        $h->pageScript('https://js.stripe.com/v3/');
        $this->assertStringContainsString('src="https://js.stripe.com/v3/"', $view->pageScripts, 'a remote SDK URL passes through');
    }

    #[Test]
    public function page_script_emits_type_and_defer_attributes(): void
    {
        $view = $this->view();
        $h = new Tiger_View_Helper_PageScript();
        $h->view = $view;

        $h->pageScript('/x/b.js', ['type' => 'module', 'defer' => true]);
        $this->assertStringContainsString('type="module" defer', $view->pageScripts);
    }

    #[Test]
    public function page_style_appends_a_link_tag_to_the_pageHead_slot_and_dedups(): void
    {
        $view = $this->view();
        $h = new Tiger_View_Helper_PageStyle();
        $h->view = $view;

        $h->pageStyle('/_shop/css/shop.css');
        $h->pageStyle('/_shop/css/shop.css');
        $this->assertStringContainsString('<link rel="stylesheet" href="/_shop/css/shop.css">', $view->pageHead);
        $this->assertSame(1, substr_count($view->pageHead, '/_shop/css/shop.css'), 'registered once');
    }

    #[Test]
    public function empty_input_is_a_no_op(): void
    {
        $view = $this->view();
        $ps = new Tiger_View_Helper_PageScript(); $ps->view = $view;
        $py = new Tiger_View_Helper_PageStyle();  $py->view = $view;

        $ps->pageScript('');
        $py->pageStyle('');
        $this->assertSame('', $view->pageScripts);
        $this->assertSame('', $view->pageHead);
    }
}
