<?php
/**
 * SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
 *
 * Tiger_Theme::activate()/deactivate() — the ONE authority for making a theme active (THEMES.md §5a),
 * shared by the Modules admin service and the headless installer (TIGER-124). Before this seam the
 * logic lived only inside System_Service_Modules, so a non-interactive caller had to re-implement it.
 */

namespace Tiger\Tests\Integration\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Config;
use Tiger_Theme;

#[CoversClass(Tiger_Theme::class)]
final class ThemeActivateTest extends IntegrationTestCase
{
    private const SLUG = 'theme-q9z';
    private const KEY  = 'q9z';

    private string $moduleDir;
    private string $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moduleDir = \APPLICATION_PATH . '/modules/' . self::SLUG;
        @mkdir($this->moduleDir . '/assets/css', 0777, true);
        file_put_contents($this->moduleDir . '/theme.json', json_encode(['key' => self::KEY, 'name' => 'Q9 Theme', 'version' => '0.1.0']));
        file_put_contents($this->moduleDir . '/assets/css/q9z.css', 'body{}');
        $this->link = (\defined('PUBLIC_PATH') ? \PUBLIC_PATH : \APPLICATION_ROOT . '/public') . '/_' . self::KEY;
        $this->cleanLink();
    }

    protected function tearDown(): void
    {
        $this->cleanLink();
        @unlink($this->moduleDir . '/assets/css/q9z.css');
        @rmdir($this->moduleDir . '/assets/css');
        @rmdir($this->moduleDir . '/assets');
        @unlink($this->moduleDir . '/theme.json');
        @rmdir($this->moduleDir);
        parent::tearDown();
    }

    private function cleanLink(): void
    {
        if (is_link($this->link)) { @unlink($this->link); }
        elseif (is_dir($this->link)) { @unlink($this->link . '/css/q9z.css'); @rmdir($this->link . '/css'); @rmdir($this->link); }
    }

    private function activeTheme(): string
    {
        return (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.theme');
    }

    #[Test]
    public function activate_writes_the_config_key_and_links_the_assets(): void
    {
        $out = Tiger_Theme::activate(self::SLUG);
        $this->assertSame(['slug' => self::SLUG, 'key' => self::KEY, 'asset_base' => '/_' . self::KEY, 'default' => true], $out);
        $this->assertSame(self::KEY, $this->activeTheme(), 'activate() defaults to making it the default site theme');
        $this->assertTrue(is_dir($this->link), 'assets reachable under public/_<key>');
        $this->assertFileExists($this->link . '/css/q9z.css');
    }

    #[Test]
    public function activate_is_idempotent_and_refreshes_a_stale_link(): void
    {
        Tiger_Theme::activate(self::SLUG);
        // Point the link somewhere wrong, as a moved install would; activate() must re-point it.
        if (is_link($this->link)) { @unlink($this->link); @symlink(sys_get_temp_dir(), $this->link); }
        Tiger_Theme::activate(self::SLUG);
        $this->assertSame(self::KEY, $this->activeTheme());
        $this->assertFileExists($this->link . '/css/q9z.css');
    }

    #[Test]
    public function deactivate_clears_the_key_only_when_this_theme_is_active(): void
    {
        Tiger_Theme::activate(self::SLUG);
        $this->assertTrue(Tiger_Theme::deactivate(self::SLUG));
        $this->assertSame('', $this->activeTheme(), 'back to the platform base theme');

        (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.theme', 'someother');
        $this->assertFalse(Tiger_Theme::deactivate(self::SLUG), 'not active → nothing to clear');
        $this->assertSame('someother', $this->activeTheme(), 'another theme\'s activation is left alone');
    }

    #[Test]
    public function an_unknown_or_non_theme_slug_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        Tiger_Theme::activate('system');   // a module, not a theme
    }
}
