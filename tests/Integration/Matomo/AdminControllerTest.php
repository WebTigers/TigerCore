<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Matomo;

use Matomo_AdminController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ModuleControllerTestCase;

/**
 * Matomo_AdminController — the Matomo settings screen. index prefills the settings form + the two
 * switch states from the live config. With nothing configured, the form is present, enabled is false,
 * and exclude-signed-in defaults on.
 */
#[CoversClass(Matomo_AdminController::class)]
final class AdminControllerTest extends ModuleControllerTestCase
{
    #[Test]
    public function index_prefills_the_settings_form_and_switch_state(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Matomo_AdminController::class, 'index', [], 'GET');

        $view = $this->controller()->view;
        $this->assertStringContainsString('Matomo', (string) $view->title);
        $this->assertInstanceOf(\Matomo_Form_Settings::class, $view->form);
        $this->assertFalse($view->enabled, 'unconfigured → disabled');
        $this->assertTrue($view->excludeSignedIn, 'exclude signed-in staff defaults on');
    }
}
