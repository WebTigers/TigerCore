<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Cms_Renderer;
use Tiger_Model_Page;

/**
 * Tiger_Cms_Renderer::render() — the LAYOUT-wrapping path (the RendererTest/RendererExtraTest units cover
 * everything else). A page whose `layout_key` resolves a `type=layout` row is rendered body-first, then the
 * layout body is rendered with the page's HTML handed in as `$this->content` — the CMS's page-in-layout
 * composition. Needs the DB (fetchByKey), so it's an integration test; the seeded layout row rides the
 * per-test transaction.
 */
#[CoversClass(Tiger_Cms_Renderer::class)]
final class RendererLayoutTest extends IntegrationTestCase
{
    #[Test]
    public function render_wraps_the_body_in_its_resolved_layout(): void
    {
        // A phtml layout that frames whatever `content` it's handed.
        (new Tiger_Model_Page())->insert([
            'type'     => Tiger_Model_Page::TYPE_LAYOUT,
            'locale'   => 'en',
            'org_id'   => '',
            'title'    => 'W7 Layout',
            'slug'     => 'w7-layout',
            'page_key' => 'w7layout',
            'body'     => '<main><?= $this->content ?></main>',
            'format'   => Tiger_Model_Page::FORMAT_PHTML,
            'status'   => Tiger_Model_Page::STATUS_PUBLISHED,
        ]);

        // The page itself only needs the fields render() reads; the layout is what must be in the DB.
        $page = new stdClass();
        $page->body       = '<p>Hello</p>';
        $page->format     = Tiger_Model_Page::FORMAT_HTML;
        $page->layout_key = 'w7layout';
        $page->locale     = 'en';
        $page->org_id     = '';

        $out = (new Tiger_Cms_Renderer())->render($page);
        $this->assertSame('<main><p>Hello</p></main>', $out, 'the body is composed into the layout');
    }

    #[Test]
    public function render_returns_the_bare_body_when_the_layout_key_resolves_nothing(): void
    {
        // layout_key set but no matching layout row → the render falls back to the un-wrapped body.
        $page = new stdClass();
        $page->body       = '<p>Orphan</p>';
        $page->format     = Tiger_Model_Page::FORMAT_HTML;
        $page->layout_key = 'w7-nonexistent-layout';
        $page->locale     = 'en';
        $page->org_id     = '';

        $this->assertSame('<p>Orphan</p>', (new Tiger_Cms_Renderer())->render($page));
    }
}
