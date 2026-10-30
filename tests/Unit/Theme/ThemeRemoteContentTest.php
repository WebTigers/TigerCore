<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Theme;
use Tiger_Theme_Remote;
use Zend_Config;
use Zend_Registry;

/**
 * The remote content tier (TIGER-409): when tiger.theme.content_url is configured and a local
 * mirror holds content, Tiger_Theme::contentFile() serves the ACTIVE theme's pages from the mirror
 * (Tiger_Theme_Remote) instead of the baked content/ dir — so a theme content edit lands via an S3
 * fetch, not a theme release or an AMI re-bake. Falls through to the baked floor when the mirror is
 * empty or lacks a page, and never applies to a named-theme lookup.
 */
#[CoversClass(Tiger_Theme::class)]
#[CoversClass(Tiger_Theme_Remote::class)]
final class ThemeRemoteContentTest extends UnitTestCase
{
    private string $dir = '';       // the active theme dir (baked content/)
    private string $mirror = '';    // the remote mirror dir

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . '/tigremote-' . uniqid('', true);
        $this->dir    = $base . '/theme';
        $this->mirror = $base . '/mirror';
        mkdir($this->dir . '/content', 0777, true);
        mkdir($this->mirror, 0777, true);
        Zend_Registry::set('Tiger_ThemeDir', $this->dir);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir . '/content', $this->mirror] as $d) {
            foreach (glob($d . '/*') ?: [] as $f) { is_dir($f) ? @rmdir($f) : @unlink($f); }
        }
        @unlink($this->mirror . '/.manifest.json');
        @rmdir($this->dir . '/content'); @rmdir($this->dir);
        @rmdir($this->mirror);
        parent::tearDown();
    }

    /** Point tiger.theme.* at the mirror (https base — never fetched; the mirror is pre-populated). */
    private function configureRemote(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['theme' => [
                'content_url'    => 'https://cdn.webtigers.com/marketing',
                'content_mirror' => $this->mirror,
            ]],
        ]));
    }

    private function baked(string $name): void
    {
        file_put_contents($this->dir . '/content/' . $name, 'baked:' . $name);
    }

    private function mirrored(string $name): void
    {
        file_put_contents($this->mirror . '/' . $name, 'mirror:' . $name);
    }

    /** A non-empty files manifest is what makes contentRoot() treat the mirror as holding content. */
    private function manifest(array $names): void
    {
        $files = [];
        foreach ($names as $n) { $files[$n] = ['sha256' => str_repeat('a', 64), 'size' => 1]; }
        file_put_contents($this->mirror . '/.manifest.json', json_encode(['files' => $files]));
    }

    // -- the resolver's remote tier ---------------------------------------------------------------

    #[Test]
    public function mirror_base_page_wins_over_the_baked_one(): void
    {
        $this->baked('get-tiger.phtml');
        $this->mirrored('get-tiger.phtml');
        $this->manifest(['get-tiger.phtml']);
        $this->configureRemote();

        $this->assertSame($this->mirror . '/get-tiger.phtml', Tiger_Theme::contentFile('get-tiger', null, ''));
    }

    #[Test]
    public function mirror_locale_variant_resolves(): void
    {
        $this->baked('get-tiger.phtml');
        $this->mirrored('get-tiger.phtml');
        $this->mirrored('get-tiger.es.phtml');
        $this->manifest(['get-tiger.phtml', 'get-tiger.es.phtml']);
        $this->configureRemote();

        $this->assertSame($this->mirror . '/get-tiger.es.phtml', Tiger_Theme::contentFile('get-tiger', null, 'es'));
    }

    #[Test]
    public function falls_through_to_baked_when_the_page_is_absent_from_the_mirror(): void
    {
        $this->baked('how-it-works.phtml');     // only baked ships this page
        $this->mirrored('get-tiger.phtml');     // mirror holds a DIFFERENT page
        $this->manifest(['get-tiger.phtml']);
        $this->configureRemote();

        $this->assertSame($this->dir . '/content/how-it-works.phtml', Tiger_Theme::contentFile('how-it-works', null, ''));
    }

    #[Test]
    public function empty_mirror_lets_the_baked_floor_serve(): void
    {
        $this->baked('get-tiger.phtml');
        // a configured remote but no manifest/content yet (a cold node that never fetched)
        $this->configureRemote();

        $this->assertSame($this->dir . '/content/get-tiger.phtml', Tiger_Theme::contentFile('get-tiger', null, ''));
    }

    #[Test]
    public function a_named_theme_dir_never_uses_the_active_theme_mirror(): void
    {
        // The mirror holds get-tiger, but an explicit $dir (home-page selector pointing at another
        // theme) must resolve from THAT theme's baked files, not the active theme's mirror.
        $this->mirrored('get-tiger.phtml');
        $this->manifest(['get-tiger.phtml']);
        $this->configureRemote();

        $other = sys_get_temp_dir() . '/tigremote-other-' . uniqid('', true);
        mkdir($other . '/content', 0777, true);
        file_put_contents($other . '/content/get-tiger.phtml', 'other-baked');

        $this->assertSame($other . '/content/get-tiger.phtml', Tiger_Theme::contentFile('get-tiger', $other, ''));

        @unlink($other . '/content/get-tiger.phtml');
        @rmdir($other . '/content'); @rmdir($other);
    }

    #[Test]
    public function no_remote_configured_means_baked_as_before(): void
    {
        $this->baked('get-tiger.phtml');
        $this->mirrored('get-tiger.phtml');
        $this->manifest(['get-tiger.phtml']);
        // NOTE: configureRemote() NOT called — tiger.theme.content_url is unset.
        $this->assertSame($this->dir . '/content/get-tiger.phtml', Tiger_Theme::contentFile('get-tiger', null, ''));
    }

    // -- Tiger_Theme_Remote's own surface ---------------------------------------------------------

    #[Test]
    public function base_url_requires_https(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['theme' => ['content_url' => 'http://cdn.webtigers.com/marketing']],
        ]));
        $r = new Tiger_Theme_Remote();
        $this->assertSame('', $r->baseUrl());        // plain http is refused
        $this->assertFalse($r->isEnabled());
    }

    #[Test]
    public function https_base_url_is_trimmed_and_enabled(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['theme' => ['content_url' => 'https://cdn.webtigers.com/marketing/']],
        ]));
        $r = new Tiger_Theme_Remote();
        $this->assertSame('https://cdn.webtigers.com/marketing', $r->baseUrl());
        $this->assertTrue($r->isEnabled());
    }

    #[Test]
    public function content_root_is_empty_without_a_manifest(): void
    {
        $this->configureRemote();                    // configured, but the mirror holds no manifest
        $this->assertSame('', (new Tiger_Theme_Remote())->contentRoot());
    }

    #[Test]
    public function phtml_is_an_allowed_content_extension_php_is_not(): void
    {
        // The deliberate difference from Docs_Model_Remote: a theme .phtml is echoed, not executed.
        $this->assertContains('phtml', Tiger_Theme_Remote::ALLOWED_EXT);
        $this->assertContains('html', Tiger_Theme_Remote::ALLOWED_EXT);
        $this->assertNotContains('php', Tiger_Theme_Remote::ALLOWED_EXT);
    }
}
