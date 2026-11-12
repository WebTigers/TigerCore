<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Theme;
use Zend_Registry;

/**
 * Tiger_Theme::sitemapPages() — which of the active theme's content pages belong in /sitemap.xml
 * (TIGER-491): exactly the ones Tiger_Controller_Plugin_ThemeContent serves at their own URL, minus
 * any page that opts out.
 */
#[CoversClass(Tiger_Theme::class)]
final class ThemeSitemapPagesTest extends UnitTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tigsitemap-' . uniqid('', true);
        mkdir($this->dir . '/content/guides', 0777, true);
        Zend_Registry::set('Tiger_ThemeDir', $this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/content/{,guides/}*.phtml', GLOB_BRACE) ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir . '/content/guides');
        @rmdir($this->dir . '/content');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function file(string $name, string $hint = ''): void
    {
        file_put_contents($this->dir . '/content/' . $name, $hint . "\n<p>" . $name . '</p>');
    }

    /** @return array<string,array{loc:string,title:string,desc:string}> */
    private function byLoc(): array
    {
        $out = [];
        foreach (Tiger_Theme::sitemapPages() as $row) { $out[$row['loc']] = $row; }
        return $out;
    }

    #[Test]
    public function lists_each_servable_page_with_its_title_and_description(): void
    {
        $this->file('get-tiger.phtml', '<!-- tiger:page title="Get Tiger" description="Plans and pricing." -->');
        $this->file('vibe.phtml');
        $this->file('guides/start.phtml', '<!-- tiger:page title="Start here" -->');

        $pages = $this->byLoc();
        $this->assertSame(['/get-tiger', '/guides/start', '/vibe'], $this->sorted(array_keys($pages)));
        $this->assertSame('Get Tiger', $pages['/get-tiger']['title']);
        $this->assertSame('Plans and pricing.', $pages['/get-tiger']['desc']);
        $this->assertSame('', $pages['/vibe']['desc'], 'no description hint → empty, never a placeholder');
    }

    #[Test]
    public function skips_what_is_not_served_at_its_own_url(): void
    {
        $this->file('index.phtml', '<!-- tiger:page title="Home" -->');          // that is '/', listed by the controller
        $this->file('pricing.es.phtml', '<!-- tiger:page title="Precios" -->');  // a locale variant, served at /pricing
        $this->file('pricing.phtml', '<!-- tiger:page title="Pricing" -->');
        $this->file('full-width.phtml', '<!-- tiger:layout title="Full width" -->');
        $this->file('header.phtml', '<!-- tiger:partial title="Header" -->');

        $this->assertSame(['/pricing'], array_keys($this->byLoc()));
    }

    #[Test]
    public function a_page_opts_out_with_sitemap_no(): void
    {
        $this->file('get-tiger.phtml', '<!-- tiger:page title="Get Tiger" -->');
        $this->file('get-tiger-20261101.phtml', '<!-- tiger:page title="Get Tiger (archived)" sitemap="no" -->');
        $this->file('legacy.phtml', '<!-- tiger:page title="Legacy" sitemap="No " -->');

        $this->assertSame(['/get-tiger'], array_keys($this->byLoc()));
    }

    #[Test]
    public function no_theme_means_no_pages(): void
    {
        Zend_Registry::set('Tiger_ThemeDir', '');
        $this->assertSame([], Tiger_Theme::sitemapPages());
    }

    /** @param array<int,string> $a */
    private function sorted(array $a): array
    {
        sort($a);
        return $a;
    }
}
