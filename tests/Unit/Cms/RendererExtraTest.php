<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use stdClass;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Cms_Renderer;
use Tiger_Model_Page;
use Zend_Registry;
use Zend_View;

/**
 * Tiger_Cms_Renderer — the render()/_view branches the RendererTest (renderBody-focused) doesn't reach:
 *   - render() of a page with NO layout_key (the body-only path + the returned string);
 *   - the phtml view seam using the registered themed `Tiger_View` (clone + clearVars) when present;
 *   - the [shortcode] pass hitting an UNREGISTERED name while OTHER handlers ARE registered (the
 *     per-match "not registered → leave the literal" branch, which the empty-registry fast-path hides).
 *
 * The layout-wrapping path (render() → fetchByKey → renderBody(layout)) needs the DB and lives in the
 * integration RendererLayoutTest. The shortcode registry is a process-global static → cleared via
 * reflection around each test.
 */
#[CoversClass(Tiger_Cms_Renderer::class)]
final class RendererExtraTest extends UnitTestCase
{
    private Tiger_Cms_Renderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearShortcodes();
        $this->renderer = new Tiger_Cms_Renderer();
    }

    protected function tearDown(): void
    {
        $this->clearShortcodes();
        parent::tearDown();
    }

    private function clearShortcodes(): void
    {
        (new ReflectionProperty(Tiger_Cms_Renderer::class, '_shortcodes'))->setValue(null, []);
    }

    #[Test]
    public function render_of_a_page_without_a_layout_returns_the_body(): void
    {
        Tiger_Cms_Renderer::registerShortcode('year', static fn () => '2026');

        $page = new stdClass();
        $page->body       = '<h1>Home</h1> © [year]';
        $page->format     = Tiger_Model_Page::FORMAT_HTML;
        $page->layout_key = null;   // no layout → the body path returns directly

        $out = $this->renderer->render($page);
        $this->assertSame('<h1>Home</h1> © 2026', $out);
    }

    #[Test]
    public function render_exposes_the_page_row_to_a_phtml_body_as_context(): void
    {
        // render() folds the page row into the context under `page`, so a phtml body can read it.
        $page = new stdClass();
        $page->body       = 'title=<?= $this->page->title ?>';
        $page->format     = Tiger_Model_Page::FORMAT_PHTML;
        $page->layout_key = null;
        $page->title      = 'Welcome';

        $this->assertSame('title=Welcome', $this->renderer->render($page));
    }

    #[Test]
    public function a_phtml_body_renders_through_the_registered_theme_view_when_present(): void
    {
        // With a themed Tiger_View in the registry, _view() clones it (helpers + paths) and clears its vars
        // before assigning the render context.
        Zend_Registry::set('Tiger_View', new Zend_View());

        $out = $this->renderer->renderBody(
            'hi <?= $this->who ?>',
            Tiger_Model_Page::FORMAT_PHTML,
            ['who' => 'Ariel']
        );
        $this->assertSame('hi Ariel', $out);
    }

    #[Test]
    public function an_unregistered_shortcode_is_left_literal_even_with_other_handlers_registered(): void
    {
        // A registered handler makes the registry non-empty (so the fast-path guard passes) — the regex
        // then still matches the UNknown token, and the per-match branch returns it verbatim.
        Tiger_Cms_Renderer::registerShortcode('known', static fn () => 'K');

        $out = $this->renderer->renderBody('[known] and [unknown]', Tiger_Model_Page::FORMAT_HTML);
        $this->assertSame('K and [unknown]', $out, 'the unregistered token is preserved literally');
    }
}
