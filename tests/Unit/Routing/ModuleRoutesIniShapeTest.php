<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Routing;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Zend_Config_Ini;

/**
 * Every bundled module's `configs/routes.ini` must be in the shape the ingester reads.
 *
 * Tiger_Routing_ModuleRoutes navigates to `resources.router.routes.*`. A file written as bare
 * `routes.*` parses fine, matches nothing, and errors nowhere — the URLs it declares simply never
 * exist. The messages module shipped that way for about a minute (TIGER-114); TigerImage's routes.ini
 * has been that way since it was written, and its URLs work only because they happen to coincide with
 * default module/controller/action routing. Content without the contract proves nothing.
 */
#[CoversNothing]
final class ModuleRoutesIniShapeTest extends UnitTestCase
{
    #[Test]
    public function every_module_routes_ini_declares_routes_where_the_ingester_looks(): void
    {
        $files = glob(TIGER_CORE_PATH . '/modules/*/configs/routes.ini') ?: [];
        $this->assertNotEmpty($files, 'no module routes.ini found — the glob is wrong, not the rule');

        foreach ($files as $f) {
            $raw = file_get_contents($f);
            $this->assertDoesNotMatchRegularExpression('~^\s*routes\.~m', $raw,
                basename(dirname(dirname($f))) . "/configs/routes.ini declares bare `routes.*` — the ingester reads `resources.router.routes.*`, so these URLs would never exist");

            $cfg    = new Zend_Config_Ini($f, 'production');
            $res    = $cfg->get('resources');
            $router = $res ? $res->get('router') : null;
            $routes = $router ? $router->get('routes') : null;
            $this->assertNotNull($routes, basename(dirname(dirname($f))) . ': no resources.router.routes node');
            $this->assertGreaterThan(0, count($routes->toArray()), basename(dirname(dirname($f))) . ': routes node is empty');

            foreach ($routes->toArray() as $name => $r) {
                $this->assertArrayHasKey('route', $r, "$name has no route pattern");
                $this->assertArrayHasKey('defaults', $r, "$name has no defaults");
                foreach (['module', 'controller', 'action'] as $k) {
                    $this->assertArrayHasKey($k, $r['defaults'], "$name defaults lack $k");
                }
            }
        }
    }
}
