<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_View_Helper_PageAsset_Abstract;
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
 *
 * The last case is the one that cost a silent bug: a `partial()` renders against a CLONE of the view,
 * so a registration made there must still reach the view the LAYOUT renders against, or the page's JS
 * never loads behind a 200.
 */
#[CoversClass(Tiger_View_Helper_PageScript::class)]
#[CoversClass(Tiger_View_Helper_PageStyle::class)]
#[CoversClass(Tiger_View_Helper_PageAsset_Abstract::class)]
final class PageAssetHelpersTest extends UnitTestCase
{
    /** Dedupe is per-REQUEST and static (so a view + its partial can't double-emit); one test = one request. */
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_View_Helper_PageAsset_Abstract::reset();
    }

    protected function tearDown(): void
    {
        Tiger_View_Helper_PageAsset_Abstract::reset();
        parent::tearDown();
    }

    /** A minimal view double: an `asset()` that returns its argument, plus the two string slots. */
    private function view(): object
    {
        return new class {
            public string $pageScripts = '';
            public string $pageHead = '';
            public function asset($p) { return $p; }   // cache-bust is asset()'s job; identity here
        };
    }

    private function script(object $view): Tiger_View_Helper_PageScript
    {
        $h = new Tiger_View_Helper_PageScript();
        $h->view = $view;
        return $h;
    }

    #[Test]
    public function page_script_appends_a_script_tag_to_the_pageScripts_slot(): void
    {
        $view = $this->view();
        $h = $this->script($view);

        $this->assertSame('', $h->pageScript('/_shop/js/shop.subscribe.js'), 'returns nothing — the tag goes to the slot');
        $this->assertStringContainsString('<script src="/_shop/js/shop.subscribe.js"></script>', $view->pageScripts);
    }

    #[Test]
    public function page_script_dedups_by_url_and_passes_a_remote_url_through(): void
    {
        $view = $this->view();
        $h = $this->script($view);

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
        $h = $this->script($view);

        $h->pageScript('/x/b.js', ['type' => 'module', 'defer' => true]);
        $this->assertStringContainsString('type="module" defer', $view->pageScripts);
    }

    #[Test]
    public function registrations_keep_the_order_they_were_made_in(): void
    {
        $view = $this->view();
        $h = $this->script($view);

        $h->pageScript('/x/vendor.js');            // a library the page script depends on
        $h->pageScript('/x/page.js', ['defer' => true]);
        $this->assertLessThan(
            strpos($view->pageScripts, '/x/page.js'),
            strpos($view->pageScripts, '/x/vendor.js'),
            'the vendor library must still be emitted ahead of the script that uses it'
        );
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
    public function the_two_slots_are_independent(): void
    {
        $view = $this->view();
        $js = $this->script($view);
        $css = new Tiger_View_Helper_PageStyle();
        $css->view = $view;

        $js->pageScript('/x/same.js');
        $css->pageStyle('/x/same.js');   // same URL, different slot — both must register

        $this->assertStringContainsString('/x/same.js', $view->pageScripts);
        $this->assertStringContainsString('/x/same.js', $view->pageHead);
    }

    #[Test]
    public function a_registration_from_a_partials_cloned_view_still_reaches_the_layouts_view(): void
    {
        // What `Zend_View_Helper_Partial` does: `clone $this->view` + clearVars(). ZF1 binds a helper to
        // a view only when THAT view first instantiates it, so a helper first used inside a partial is
        // bound to the throwaway clone. Writing only there loses the tag silently.
        $layoutView = $this->view();
        $clone      = $this->view();          // stands in for the partial's cloned view

        $h = new class extends Tiger_View_Helper_PageScript {
            public ?object $mvcView = null;
            protected function _views() { return [$this->view, $this->mvcView]; }
        };
        $h->view = $clone;
        $h->mvcView = $layoutView;

        $h->pageScript('/_profile/js/profile.sms.js');

        $this->assertStringContainsString('/_profile/js/profile.sms.js', $layoutView->pageScripts,
            "the layout's view must carry the tag — otherwise the page JS never loads");
        $this->assertSame(1, substr_count($layoutView->pageScripts, 'profile.sms.js'), 'exactly once');
    }
}
