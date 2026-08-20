<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * AccountController — the authenticated NON-admin home ("My Account"), at /account.
 *
 * The counterpart to the admin dashboard for everyone who isn't an admin. It renders in the SAME app
 * shell (the 'admin' layout, inherited) and reuses the SAME module-registered, ACL-filtered dashboard
 * widget grid (Tiger_Dashboard): a customer/member simply sees the widgets their role is allowed (their
 * subscription, licenses, profile), an admin sees theirs. Nothing here is role-specific — the ACL does
 * the scoping. Allowed to any authenticated user (core/configs/acl.ini); the admin dashboard stays gated
 * to admin+. The header/nav "Dashboard" control routes non-admins here (see the theme), so a customer is
 * never bounced to /admin.
 *
 * Naming: the domain still calls the org the "tenant"; "Account" is only the user-facing label for this
 * surface (the near-universal SaaS term for a logged-in user's home).
 */
// ZF1 dispatch-loads controllers by PATH, not the class autoloader — so a request to /account includes
// only this file; require the parent controller explicitly (require_once dedups if the dispatcher already
// loaded it for an /admin request in the same process).
require_once __DIR__ . '/AdminController.php';

class AccountController extends AdminController
{
    /**
     * Init the account home in the shared app shell, but with the ACCOUNT menu (Tiger_Account_Nav),
     * not the admin one — the same swap Tiger_Controller_Account_Action does for module account
     * screens. (This controller extends AdminController to reuse the dashboard widget grid, so it
     * sets the menu here rather than via that base.)
     *
     * @return void
     */
    public function init()
    {
        parent::init();                               // shared app shell (the 'admin' layout)
        $this->view->nav = Tiger_Account_Nav::items();
    }

    /**
     * The account home — the same ACL-filtered dashboard as admin, under an account header.
     *
     * @return void
     */
    public function indexAction()
    {
        parent::indexAction();                        // widgets + per-user layout, ACL-filtered for this role
        $this->view->title     = 'My Account';
        // dashTitle/dashLead/dashEmptyLead are translation KEYS — the shared dashboard view (admin/index.phtml)
        // resolves them through $this->t() (they render in the visitor's locale).
        $this->view->dashTitle = 'core.account.title';
        $this->view->dashLead  = 'core.account.lead';
        $this->view->dashEmptyLead = 'core.account.empty_lead';
    }
}
