<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Controller;

use AccountController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;
use Zend_Registry;

/**
 * AccountController — the authenticated NON-admin home ("My Account", /account). It reuses the admin
 * dashboard's ACL-filtered widget grid (inherited from AdminController) under an account header, so a
 * signed-in customer/member gets a real home instead of being bounced to /admin. Dispatched rendering-off;
 * asserts the account header vars + the widget model, and that the shipped ACL scopes it to any signed-in
 * user (allowed) while a guest is denied and the admin dashboard stays admin-only.
 */
#[CoversClass(AccountController::class)]
final class AccountControllerTest extends ControllerTestCase
{
    #[Test]
    public function it_renders_the_account_home_for_a_signed_in_user(): void
    {
        $this->loginAs('user');
        $this->dispatchAction(AccountController::class, 'index', [], 'GET');

        $view = $this->controller()->view;
        $this->assertSame('My Account', (string) $view->title);
        $this->assertSame('My Account', (string) $view->dashTitle, 'the shared dashboard grid renders under an account header');
        $this->assertIsArray($view->widgets, 'reuses the ACL-filtered dashboard widget model');
    }

    #[Test]
    public function the_shipped_acl_allows_any_signed_in_user_but_not_a_guest(): void
    {
        $this->loginAs('user');
        $acl = Zend_Registry::get('Zend_Acl');
        $this->assertTrue($acl->has('AccountController'), 'the acl.ini resource loaded');
        $this->assertTrue($acl->isAllowed('user', 'AccountController'));
        $this->assertTrue($acl->isAllowed('admin', 'AccountController'), 'roles above user inherit');
        $this->assertFalse($acl->isAllowed('guest', 'AccountController'), 'a guest is bounced to login');
        // The admin dashboard stays admin+; a plain user cannot reach it.
        $this->assertFalse($acl->isAllowed('user', 'AdminController'), 'the back office is not opened up by this');
    }
}
