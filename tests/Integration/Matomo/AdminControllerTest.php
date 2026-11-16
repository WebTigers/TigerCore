<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Matomo;

use Matomo_AdminController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ModuleControllerTestCase;

/**
 * Matomo_AdminController — since 1.61 Matomo is set up in ONE place, the Analytics screen's Matomo
 * provider panel (which renders this module's own form and posts to its own service). With the
 * analytics module present, /matomo/admin redirects there. (Its fallback render — the same shared
 * partial, only when analytics is inactive — is not reachable here, where every bundled module loads.)
 */
#[CoversClass(Matomo_AdminController::class)]
final class AdminControllerTest extends ModuleControllerTestCase
{
    #[Test]
    public function index_redirects_to_the_one_matomo_setup_screen(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Matomo_AdminController::class, 'index', [], 'GET');

        $this->assertSame(Matomo_AdminController::SETUP_URL, $this->redirectLocation());
        $this->assertStringContainsString('/analytics/admin?provider=matomo', Matomo_AdminController::SETUP_URL);
    }
}
