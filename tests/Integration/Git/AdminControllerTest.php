<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Git_AdminController;
use Git_Form_Connect;
use Git_Form_Submit;
use Tiger\Tests\Support\ModuleControllerTestCase;
use Zend_Controller_Action_Exception;

/**
 * Git_AdminController — the TigerGit admin screen (TIGER-367). Thin: it renders the shell and hands the
 * two forms (for their fields + CSRF token) to the view; every real action is an /api call. The harness
 * dispatches `index` with rendering off and asserts the assembled view model.
 *
 * Git is install-level (TIGER-511): the screen opens for the install org's admin and is a 404 for any
 * other org's admin.
 */
#[CoversClass(Git_AdminController::class)]
final class AdminControllerTest extends ModuleControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asInstallOrg('org-test');   // loginAs() signs in to org-test — make it the install org
    }

    #[Test]
    public function index_renders_the_dashboard_shell(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Git_AdminController::class, 'index', [], 'GET');

        $view = $this->controller()->view;
        $this->assertStringContainsString('Git', (string) $view->title);
        $this->assertInstanceOf(Git_Form_Connect::class, $view->connectForm);
        $this->assertInstanceOf(Git_Form_Submit::class, $view->submitForm);
    }

    #[Test]
    public function another_orgs_admin_gets_a_404(): void
    {
        $this->login('u-other', 'org-not-install', 'admin');
        $this->expectException(Zend_Controller_Action_Exception::class);
        $this->expectExceptionCode(404);
        $this->dispatchAction(Git_AdminController::class, 'index', [], 'GET');
    }
}
