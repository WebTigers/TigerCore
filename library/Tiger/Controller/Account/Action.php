<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Controller_Account_Action — the base for every "My Account" screen (/account surface).
 *
 * The account-surface twin of Tiger_Controller_Admin_Action. A controller whose screens are a user's
 * OWN stuff — their profile, their subscription, their licenses, their orders — extends this instead
 * of the admin base. It reuses the SAME app shell (the PUMA `admin` layout — the sidebar + top bar +
 * content well are shared chrome), but swaps the sidebar for the ACCOUNT menu (Tiger_Account_Nav)
 * instead of the admin menu. That's the whole point: the menu follows the SURFACE, not the role —
 * an admin on /account gets the account menu (their personal account), not the back-office menu.
 *
 * So building a user-facing screen for a module is two small steps:
 *   1. Contribute the menu item — a `configs/navigation-account.ini` (zero-code) or
 *      `Tiger_Account_Nav::register()` from the Bootstrap (see Tiger_Account_Nav).
 *   2. Extend this base so the screen renders in the account shell + menu:
 *
 *        class Member_IndexController extends Tiger_Controller_Account_Action
 *        {
 *            public function indexAction()
 *            {
 *                // build the "My Membership" screen per ADMIN.md's screen template;
 *                // the shell + the account menu are already set for you.
 *            }
 *        }
 *
 * The `profile` module is the reference (Profile_IndexController / Profile_OrgController). Everything
 * else is inherited from the admin base — the layout, the disable-layout escape hatch for a
 * full-screen action, and the unbypassable Authorization plugin (gate the controller in the module's
 * acl.ini; `user` for a self-service screen). See ADMIN.md for the on-screen template.
 *
 * @api
 * @see Tiger_Account_Nav
 * @see Tiger_Controller_Admin_Action
 */
abstract class Tiger_Controller_Account_Action extends Tiger_Controller_Admin_Action
{
    /**
     * Initialize the controller: the shared app shell (from the admin base) with the ACCOUNT menu.
     *
     * @return void
     */
    public function init()
    {
        parent::init();                              // shared app shell (the 'admin' layout)
        $this->view->nav = Tiger_Account_Nav::items();  // …but the account menu, not the admin one
    }
}
