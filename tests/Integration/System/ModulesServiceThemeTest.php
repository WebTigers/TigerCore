<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Service_Modules;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Config;
use Tiger_Model_Module;

/**
 * System_Service_Modules — the THEME-toggle path ModulesServiceExtraTest flagged as unreachable ("needs
 * a type:theme module on disk"). We plant a throwaway `theme-<x>` module (a bare `theme.json` — no
 * Bootstrap, no assets/, no migrations/) under the harness app modules dir so live discovery sees a
 * `type:theme` row. Multiple themes can be active at once: activating sets the module active FLAG (and,
 * because the fixture ships no assets/, the asset-symlink step short-circuits). Which theme is the
 * DEFAULT site theme is the separate `tiger.theme` config, written only when `make_default` is set.
 * Deactivating clears the flag and, if this was the default, clears the config back to the base theme.
 *
 * All config writes are the DB tier (rolled back per test); the fixture dir is removed in tearDown.
 */
#[CoversClass(System_Service_Modules::class)]
final class ModulesServiceThemeTest extends IntegrationTestCase
{
    private const SLUG = 'theme-w7t';
    private const KEY  = 'w7t';

    private string $moduleDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->moduleDir = APPLICATION_PATH . '/modules/' . self::SLUG;
        @mkdir($this->moduleDir, 0777, true);
        // theme.json is the theme manifest — its presence is what discovery reads as `type:theme`.
        file_put_contents(
            $this->moduleDir . '/theme.json',
            json_encode(['key' => self::KEY, 'name' => 'W7 Test Theme', 'version' => '0.1.0-beta'])
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->moduleDir . '/theme.json');
        @rmdir($this->moduleDir);
        parent::tearDown();
    }

    private function dispatch(array $msg): object
    {
        return (new System_Service_Modules($msg))->getResponse();
    }

    private function messages(object $res): string
    {
        return json_encode($res->messages ?? []);
    }

    private function activeTheme(): string
    {
        return (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.theme');
    }

    private function moduleActive(string $slug): bool
    {
        $row = (new Tiger_Model_Module())->bySlug($slug);
        return $row ? ((int) $row->active === 1) : false;
    }

    #[Test]
    public function activating_a_theme_sets_the_flag_and_makes_it_default_only_when_asked(): void
    {
        $this->loginAs('superadmin');

        // Plain activate: the module active FLAG goes on (multiple themes can be active), but the
        // DEFAULT site theme is NOT changed — activating a theme never silently hijacks the site.
        $res = $this->dispatch(['action' => 'activate', 'slug' => self::SLUG]);
        $this->assertSame(1, (int) $res->result, $this->messages($res));
        $this->assertStringContainsString('activated', $this->messages($res));
        $this->assertTrue((bool) $res->data['theme'], 'the response marks this as a theme activation');
        $this->assertTrue((bool) $res->data['active']);
        $this->assertSame('/system/modules', $res->redirect);
        $this->assertTrue($this->moduleActive(self::SLUG), 'the module active flag is set');
        $this->assertSame('', $this->activeTheme(), 'activating alone does not change the default site theme');

        // Activate WITH make_default -> it becomes the default site theme (tiger.theme).
        $this->dispatch(['action' => 'activate', 'slug' => self::SLUG, 'make_default' => '1']);
        $this->assertSame(self::KEY, $this->activeTheme(), 'make_default writes tiger.theme');
    }

    #[Test]
    public function deactivating_the_default_theme_clears_the_flag_and_the_default(): void
    {
        $this->loginAs('superadmin');
        // Activate as the default, then deactivate — the flag clears and, because it was the default,
        // tiger.theme reverts to the base theme.
        $this->dispatch(['action' => 'activate', 'slug' => self::SLUG, 'make_default' => '1']);
        $this->assertSame(self::KEY, $this->activeTheme());

        $res = $this->dispatch(['action' => 'deactivate', 'slug' => self::SLUG]);
        $this->assertSame(1, (int) $res->result, $this->messages($res));
        $this->assertStringContainsString('deactivated', $this->messages($res));
        $this->assertTrue((bool) $res->data['theme']);
        $this->assertFalse((bool) $res->data['active']);
        $this->assertFalse($this->moduleActive(self::SLUG), 'the module flag is cleared');
        $this->assertSame('', $this->activeTheme(), 'the default reverts to the base theme');
    }

    #[Test]
    public function deactivating_a_theme_that_is_not_active_leaves_a_different_active_theme_alone(): void
    {
        $this->loginAs('superadmin');
        // Some OTHER theme is active; deactivating our fixture must not clobber it (the key mismatch arm).
        (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.theme', 'someother');

        $res = $this->dispatch(['action' => 'deactivate', 'slug' => self::SLUG]);
        $this->assertSame(1, (int) $res->result, $this->messages($res));
        $this->assertSame('someother', $this->activeTheme(), 'a non-matching deactivate leaves the active theme untouched');
    }
}
