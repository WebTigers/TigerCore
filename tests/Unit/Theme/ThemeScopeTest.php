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
 * Tiger_Theme::scope() — the 'site' (default) vs 'content' theme-scope flag read from theme.json,
 * which Bootstrap::_initTheme uses to decide whether the active theme provides the whole site's
 * chrome or only styles its own shipped pages.
 */
#[CoversClass(Tiger_Theme::class)]
final class ThemeScopeTest extends UnitTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tigscope-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
        Zend_Registry::set('Tiger_ThemeDir', $this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/theme.json');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function manifest(array $data): void
    {
        file_put_contents($this->dir . '/theme.json', json_encode($data));
    }

    #[Test]
    public function defaults_to_site_when_unset_or_unknown(): void
    {
        $this->manifest(['key' => 't', 'name' => 'T']);              // no scope
        $this->assertSame('site', Tiger_Theme::scope());

        $this->manifest(['key' => 't', 'scope' => 'whatever']);       // unknown value
        $this->assertSame('site', Tiger_Theme::scope());
    }

    #[Test]
    public function reads_content_scope(): void
    {
        $this->manifest(['key' => 't', 'scope' => 'content']);
        $this->assertSame('content', Tiger_Theme::scope());

        $this->manifest(['key' => 't', 'scope' => 'CONTENT']);        // case-insensitive
        $this->assertSame('content', Tiger_Theme::scope());
    }

    #[Test]
    public function no_theme_dir_is_site(): void
    {
        Zend_Registry::getInstance()->offsetUnset('Tiger_ThemeDir');
        $this->assertSame('site', Tiger_Theme::scope());
    }
}
