<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Cms;

use Cms_Form_Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Routing_Overrides;

/**
 * The home-page selector — choosing what "/" serves.
 *
 * It used to offer published CMS pages and nothing else, so an install whose front door should be a
 * MODULE page (`/marketplace`, `/docs`) had no way to say so. The stored value now holds a CMS
 * `page_id`, a PATH, or '' for the built-in landing, and these pin the properties that keeps safe:
 * the module list is generated from the routing registry (so it can't drift from what's actually
 * routable), non-page endpoints stay out of it, and a typed path is validated before it can become
 * the site's front door.
 */
#[CoversClass(Cms_Form_Settings::class)]
final class HomePageSelectorTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Routing_Overrides::clear();
    }

    protected function tearDown(): void
    {
        Tiger_Routing_Overrides::clear();
        parent::tearDown();
    }

    #[Test]
    public function module_pages_come_from_the_routing_registry(): void
    {
        Tiger_Routing_Overrides::register('marketplace', ['pattern' => 'marketplace', 'target' => 'marketplace/index/index']);
        Tiger_Routing_Overrides::register('docs', ['pattern' => 'docs', 'target' => 'docs/index/docs']);

        $paths = Cms_Form_Settings::modulePaths();

        $this->assertArrayHasKey('/marketplace', $paths, 'a registered module page is offered');
        $this->assertArrayHasKey('/docs', $paths, 'and so is every other one');
        $this->assertSame('/marketplace', $paths['/marketplace'], 'the value IS the path — that is what gets stored');
    }

    #[Test]
    public function non_page_endpoints_are_never_offered(): void
    {
        // These serve text/xml, not a page. Offering one as a home page could only ever be a mistake.
        Tiger_Routing_Overrides::register('robots', ['pattern' => 'robots.txt', 'target' => 'seo/robots/txt']);
        Tiger_Routing_Overrides::register('sitemap', ['pattern' => 'sitemap.xml', 'target' => 'seo/sitemap/xml']);
        Tiger_Routing_Overrides::register('llms', ['pattern' => 'llms.txt', 'target' => 'seo/llms/txt']);
        Tiger_Routing_Overrides::register('marketplace', ['pattern' => 'marketplace', 'target' => 'marketplace/index/index']);

        $paths = Cms_Form_Settings::modulePaths();

        $this->assertSame(['/marketplace'], array_keys($paths),
            'file-like prefixes are filtered out; only real pages are offered');
    }

    #[Test]
    public function the_selector_offers_the_builtin_landing_and_a_custom_escape_hatch(): void
    {
        Tiger_Routing_Overrides::register('marketplace', ['pattern' => 'marketplace', 'target' => 'marketplace/index/index']);

        $options = (new Cms_Form_Settings())->getElement('home_page')->getMultiOptions();

        $this->assertArrayHasKey('', $options, 'the built-in landing stays the default');
        $this->assertArrayHasKey(Cms_Form_Settings::CUSTOM, $options, 'an ad-hoc path can always be typed');
    }

    #[Test]
    public function a_typed_path_must_be_a_rooted_path(): void
    {
        $el = (new Cms_Form_Settings())->getElement('home_page_custom');

        $this->assertTrue($el->isValid('/marketplace'), 'a normal module path passes');
        $this->assertTrue($el->isValid('/shop/index/cart'), 'so does a deeper route');
        $this->assertTrue($el->isValid(''), 'and blank is fine — the field only applies to "custom"');

        $this->assertFalse($el->isValid('marketplace'), 'a path must be rooted, or "/" resolution is ambiguous');
        $this->assertFalse($el->isValid('https://evil.test/x'), 'an absolute URL is refused — this forwards internally, it does not redirect offsite');
        $this->assertFalse($el->isValid('/x?y=1'), 'no query string: the home page takes no caller-supplied params');
    }

    #[Test]
    public function the_custom_sentinel_is_never_itself_stored(): void
    {
        // The sentinel only means "read the other field". If it reached the config table it would
        // resolve to nothing and the site would silently lose its home page.
        $this->assertStringStartsNotWith('/', Cms_Form_Settings::CUSTOM,
            'the sentinel is not a path, so it can never be mistaken for one at dispatch');
        $this->assertNotSame('', Cms_Form_Settings::CUSTOM,
            'nor for the built-in landing');
    }
}
