<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Application;

/**
 * Tiger_Application — the update-maintenance gate Wave 5's ApplicationTest only pinned in its
 * false (no-flag) state. Here the flag file exists:
 *   - a FRESH `var/update/.maintenance` → `_updateInProgress()` serves the 503 "Updating…" flash
 *     and returns true (run() then skips dispatch);
 *   - a STALE flag (older than the 120s guard) → it's ignored (returns false) so a crashed update
 *     can never wedge the site.
 *
 * The flag lives under APPLICATION_ROOT (as a real boot reads it); it's created + removed per test.
 * Non-isolated so pcov attributes the branch. run()/boot()/fail() remain the live-boot boundary —
 * see WAVE5-FINDINGS-app.md.
 */
#[CoversClass(Tiger_Application::class)]
final class ApplicationMoreTest extends UnitTestCase
{
    private string $flagDir;
    private string $flag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flagDir = rtrim(APPLICATION_ROOT, '/') . '/var/update';
        $this->flag    = $this->flagDir . '/.maintenance';
    }

    protected function tearDown(): void
    {
        @unlink($this->flag);
        @rmdir($this->flagDir);
        @rmdir(dirname($this->flagDir));   // var/ — only if now empty
        parent::tearDown();
    }

    private function app(): Tiger_Application
    {
        return new Tiger_Application(APPLICATION_ROOT);
    }

    private function call(Tiger_Application $app, string $method)
    {
        return (new ReflectionMethod(Tiger_Application::class, $method))->invoke($app);
    }

    #[Test]
    public function a_fresh_maintenance_flag_serves_the_updating_flash_and_returns_true(): void
    {
        if (!is_dir($this->flagDir) && !@mkdir($this->flagDir, 0777, true)) {
            $this->markTestSkipped('cannot create the maintenance flag dir under APPLICATION_ROOT.');
        }
        file_put_contents($this->flag, '');

        $this->expectOutputRegex('/Updating/');
        $this->assertTrue($this->call($this->app(), '_updateInProgress'));
    }

    #[Test]
    public function a_stale_maintenance_flag_is_ignored(): void
    {
        if (!is_dir($this->flagDir) && !@mkdir($this->flagDir, 0777, true)) {
            $this->markTestSkipped('cannot create the maintenance flag dir under APPLICATION_ROOT.');
        }
        file_put_contents($this->flag, '');
        touch($this->flag, time() - 3600);   // an hour old → past the 120s guard

        // No output, and the gate does not engage.
        $this->assertFalse($this->call($this->app(), '_updateInProgress'));
    }
}
