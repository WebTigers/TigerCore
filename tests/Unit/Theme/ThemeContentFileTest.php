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
 * Tiger_Theme::contentFile() — locale-aware resolution of a theme's content/<slug>.phtml page:
 * the request locale's sibling (content/<slug>.<lang>.phtml) when it ships, else the base English
 * file. This is what lets a translated marketing page render in-locale (TIGER-325); regression of
 * the per-locale marketing pages lost in the TIGER-230 extraction.
 */
#[CoversClass(Tiger_Theme::class)]
final class ThemeContentFileTest extends UnitTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tigcontent-' . uniqid('', true);
        mkdir($this->dir . '/content', 0777, true);
        Zend_Registry::set('Tiger_ThemeDir', $this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/content/*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir . '/content');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function page(string $name): void
    {
        file_put_contents($this->dir . '/content/' . $name, '<p>' . $name . '</p>');
    }

    #[Test]
    public function returns_the_locale_variant_when_it_ships(): void
    {
        $this->page('index.phtml');
        $this->page('index.es.phtml');
        $this->assertSame($this->dir . '/content/index.es.phtml', Tiger_Theme::contentFile('index', null, 'es'));
    }

    #[Test]
    public function tlh_klingon_variant_resolves(): void
    {
        $this->page('index.phtml');
        $this->page('index.tlh.phtml');                 // 3-letter locale
        $this->assertSame($this->dir . '/content/index.tlh.phtml', Tiger_Theme::contentFile('index', null, 'tlh'));
    }

    #[Test]
    public function falls_back_to_base_when_no_variant(): void
    {
        $this->page('vibe.phtml');                      // only the English base ships
        $this->assertSame($this->dir . '/content/vibe.phtml', Tiger_Theme::contentFile('vibe', null, 'fr'));
    }

    #[Test]
    public function base_used_when_no_locale(): void
    {
        $this->page('vibe.phtml');
        $this->assertSame($this->dir . '/content/vibe.phtml', Tiger_Theme::contentFile('vibe', null, ''));
    }

    #[Test]
    public function missing_base_returns_empty_even_if_a_variant_exists(): void
    {
        $this->page('ghost.es.phtml');                  // a stray variant with no base
        $this->assertSame('', Tiger_Theme::contentFile('ghost', null, 'es'));
    }

    #[Test]
    public function nested_slug_is_allowed(): void
    {
        mkdir($this->dir . '/content/solutions', 0777, true);
        file_put_contents($this->dir . '/content/solutions/x.phtml', 'x');
        file_put_contents($this->dir . '/content/solutions/x.de.phtml', 'x-de');
        $this->assertSame($this->dir . '/content/solutions/x.de.phtml', Tiger_Theme::contentFile('solutions/x', null, 'de'));
        @unlink($this->dir . '/content/solutions/x.phtml');
        @unlink($this->dir . '/content/solutions/x.de.phtml');
        @rmdir($this->dir . '/content/solutions');
    }

    #[Test]
    public function traversal_and_bad_slugs_are_refused(): void
    {
        $this->page('index.phtml');
        $this->assertSame('', Tiger_Theme::contentFile('../secret', null, 'es'));
        $this->assertSame('', Tiger_Theme::contentFile('a.b', null, 'es'));   // a dot can never be a slug
        $this->assertSame('', Tiger_Theme::contentFile('', null, 'es'));
    }

    #[Test]
    public function a_bogus_locale_is_ignored_and_base_served(): void
    {
        $this->page('index.phtml');
        $this->page('index.es.phtml');
        // not a 2–3 lowercase code → never used to build a variant path
        $this->assertSame($this->dir . '/content/index.phtml', Tiger_Theme::contentFile('index', null, 'EN-us'));
    }

    #[Test]
    public function explicit_dir_overrides_the_active_theme(): void
    {
        $other = sys_get_temp_dir() . '/tigcontent-other-' . uniqid('', true);
        mkdir($other . '/content', 0777, true);
        file_put_contents($other . '/content/index.phtml', 'base');
        file_put_contents($other . '/content/index.pt.phtml', 'pt');
        $this->assertSame($other . '/content/index.pt.phtml', Tiger_Theme::contentFile('index', $other, 'pt'));
        @unlink($other . '/content/index.phtml');
        @unlink($other . '/content/index.pt.phtml');
        @rmdir($other . '/content');
        @rmdir($other);
    }
}
