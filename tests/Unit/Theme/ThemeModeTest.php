<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Theme;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_Theme::defaultMode() / modePreference() — the site's default colour mode (config `tiger.ui.mode`) and
 * the visitor's effective preference (their `tiger_theme` cookie, else that default). Every layout, the theme
 * switcher and the no-FOUC script resolve the mode through these, so they must agree and must never return
 * anything but browser|light|dark.
 */
#[CoversClass(Tiger_Theme::class)]
final class ThemeModeTest extends UnitTestCase
{
    private $savedCookie = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedCookie = $_COOKIE['tiger_theme'] ?? null;
        unset($_COOKIE['tiger_theme']);
    }

    protected function tearDown(): void
    {
        if ($this->savedCookie === null) { unset($_COOKIE['tiger_theme']); } else { $_COOKIE['tiger_theme'] = $this->savedCookie; }
        parent::tearDown();
    }

    private function config(array $tiger): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger], true));
    }

    #[Test]
    public function the_default_is_browser_when_unset(): void
    {
        $this->config([]);
        $this->assertSame('browser', Tiger_Theme::defaultMode());
    }

    #[Test]
    public function the_configured_default_is_used(): void
    {
        $this->config(['ui' => ['mode' => 'dark']]);
        $this->assertSame('dark', Tiger_Theme::defaultMode());
        $this->config(['ui' => ['mode' => ' Light ']]);
        $this->assertSame('light', Tiger_Theme::defaultMode(), 'trimmed + case-insensitive');
    }

    #[Test]
    public function an_unknown_default_falls_back_to_browser(): void
    {
        $this->config(['ui' => ['mode' => 'neon']]);
        $this->assertSame('browser', Tiger_Theme::defaultMode());
    }

    #[Test]
    public function the_visitors_choice_wins_over_the_site_default(): void
    {
        $this->config(['ui' => ['mode' => 'dark']]);
        $_COOKIE['tiger_theme'] = 'light';
        $this->assertSame('light', Tiger_Theme::modePreference());
    }

    #[Test]
    public function no_or_bad_cookie_means_the_site_default(): void
    {
        $this->config(['ui' => ['mode' => 'dark']]);
        $this->assertSame('dark', Tiger_Theme::modePreference(), 'no cookie');
        $_COOKIE['tiger_theme'] = 'tigermarketing';   // the same cookie name carries a theme-preview key
        $this->assertSame('dark', Tiger_Theme::modePreference(), 'a non-mode value is not a mode');
    }
}
