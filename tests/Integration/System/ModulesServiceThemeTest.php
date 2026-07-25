<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Service_Modules;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Config;

/**
 * System_Service_Modules — the THEME-toggle path ModulesServiceExtraTest flagged as unreachable ("needs
 * a type:theme module on disk"). We plant a throwaway `theme-<x>` module (a bare `theme.json` — no
 * Bootstrap, no assets/, no migrations/) under the harness app modules dir so live discovery sees a
 * `type:theme` row. Activating a theme is NOT the `module.active` flag: it writes `tiger.theme = <key>`
 * to the config tier (global scope) and — because the fixture ships no assets/ — the asset-symlink step
 * short-circuits. Deactivating clears the config back to the base theme.
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

    #[Test]
    public function activating_a_theme_writes_the_tiger_theme_config_not_a_module_flag(): void
    {
        $this->loginAs('superadmin');
        $res = $this->dispatch(['action' => 'activate', 'slug' => self::SLUG]);

        $this->assertSame(1, (int) $res->result, $this->messages($res));
        $this->assertStringContainsString('activated', $this->messages($res));
        $this->assertTrue((bool) $res->data['theme'], 'the response marks this as a theme activation');
        $this->assertTrue((bool) $res->data['active']);
        $this->assertSame('/system/modules', $res->redirect);
        // The single config write: the active theme KEY at global scope.
        $this->assertSame(self::KEY, $this->activeTheme(), 'tiger.theme now holds the theme key');
    }

    #[Test]
    public function deactivating_the_active_theme_clears_it_back_to_the_base(): void
    {
        $this->loginAs('superadmin');
        // Activate first, then deactivate — the deactivate arm only clears when the key currently matches.
        $this->dispatch(['action' => 'activate', 'slug' => self::SLUG]);
        $this->assertSame(self::KEY, $this->activeTheme());

        $res = $this->dispatch(['action' => 'deactivate', 'slug' => self::SLUG]);
        $this->assertSame(1, (int) $res->result, $this->messages($res));
        $this->assertStringContainsString('deactivated', $this->messages($res));
        $this->assertTrue((bool) $res->data['theme']);
        $this->assertFalse((bool) $res->data['active']);
        $this->assertSame('', $this->activeTheme(), 'the config reverts to the base theme');
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
