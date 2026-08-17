<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Routing_ModuleRoutes;

/**
 * The module-routes ingester's pure core: discover every ACTIVE module's configs/routes.ini, namespace
 * each route <slug>__<name> (collision-proof), skip inactive modules, and let an app-dir module override a
 * same-slug core-dir one. Network-/DB-free — fixture module dirs written to a temp tree.
 */
#[CoversClass(Tiger_Routing_ModuleRoutes::class)]
final class ModuleRoutesTest extends UnitTestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/tiger-mroutes-' . getmypid();
        $this->_seed('core/alpha', 'alphaHome', 'alpha', ['route' => 'alpha', 'controller' => 'index', 'action' => 'index']);
        $this->_seed('core/beta',  'betaThing', 'beta',  ['route' => 'beta/:id', 'controller' => 'index', 'action' => 'view']);
        @mkdir($this->root . '/core/nocfg/configs', 0775, true);   // a module with NO routes.ini → skipped
    }

    protected function tearDown(): void
    {
        $this->_rrmdir($this->root);
        parent::tearDown();
    }

    /** Write modules/<slug>/configs/routes.ini with one namespaced route under $reldir. */
    private function _seed(string $reldir, string $name, string $slugForModule, array $def): void
    {
        $dir = $this->root . '/' . $reldir . '/configs';
        @mkdir($dir, 0775, true);
        $ini  = "[production]\n";
        $ini .= "resources.router.routes.$name.route = \"{$def['route']}\"\n";
        $ini .= "resources.router.routes.$name.defaults.module = \"$slugForModule\"\n";
        $ini .= "resources.router.routes.$name.defaults.controller = \"{$def['controller']}\"\n";
        $ini .= "resources.router.routes.$name.defaults.action = \"{$def['action']}\"\n";
        $ini .= "[testing : production]\n[development : production]\n[staging : production]\n";
        file_put_contents($dir . '/routes.ini', $ini);
    }

    #[Test]
    public function collects_and_namespaces_by_slug(): void
    {
        $routes = Tiger_Routing_ModuleRoutes::collect([$this->root . '/core'], [], 'production');
        $this->assertArrayHasKey('alpha__alphaHome', $routes, 'route is namespaced <slug>__<name>');
        $this->assertArrayHasKey('beta__betaThing', $routes);
        $this->assertSame('alpha', $routes['alpha__alphaHome']['route']);
        $this->assertSame('view', $routes['beta__betaThing']['defaults']['action'], 'the full route def survives');
    }

    #[Test]
    public function a_module_without_routes_ini_is_skipped(): void
    {
        $routes = Tiger_Routing_ModuleRoutes::collect([$this->root . '/core'], [], 'production');
        $this->assertArrayNotHasKey('nocfg__x', $routes);
        $this->assertCount(2, $routes, 'only the two modules that ship routes.ini contribute');
    }

    #[Test]
    public function inactive_modules_are_skipped(): void
    {
        $routes = Tiger_Routing_ModuleRoutes::collect([$this->root . '/core'], ['beta'], 'production');
        $this->assertArrayHasKey('alpha__alphaHome', $routes);
        $this->assertArrayNotHasKey('beta__betaThing', $routes, 'a deactivated module contributes no routes');
    }

    #[Test]
    public function app_dir_overrides_a_same_slug_core_module(): void
    {
        // A second dir (the "app" tree) with the SAME slug 'alpha' but a different route.
        $appDir = $this->root . '/app/alpha/configs';
        @mkdir($appDir, 0775, true);
        file_put_contents($appDir . '/routes.ini',
            "[production]\nresources.router.routes.alphaHome.route = \"alpha-app\"\n"
            . "resources.router.routes.alphaHome.defaults.module = \"alpha\"\n"
            . "resources.router.routes.alphaHome.defaults.controller = \"index\"\n"
            . "resources.router.routes.alphaHome.defaults.action = \"index\"\n"
            . "[testing : production]\n[development : production]\n[staging : production]\n");

        // core scanned first, app last → app wins the same namespaced key.
        $routes = Tiger_Routing_ModuleRoutes::collect([$this->root . '/core', $this->root . '/app'], [], 'production');
        $this->assertSame('alpha-app', $routes['alpha__alphaHome']['route'], 'app-dir module overrides the core-dir one');
    }

    private function _rrmdir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->_rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
