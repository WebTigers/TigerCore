<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Controller_Admin_Action — the base for every ADMIN-shell controller.
 *
 * Any controller whose screens render inside the admin experience (the sidebar + top bar +
 * content well of the PUMA `admin` layout) extends this instead of Tiger_Controller_Action.
 * It sets the admin layout ONCE, in init(), so no admin controller hand-rolls
 * `$this->_helper->layout()->setLayout('admin')` — the layout is a property of "this is an
 * admin controller," not a line each author must remember. Core and every module's admin
 * controllers share it, so reskinning the admin layout/views reskins them all at once.
 *
 * A specific action that must escape the shell (e.g. a full-screen builder) still calls
 * `$this->_helper->layout()->disableLayout()` in that action — init sets the default, the
 * action overrides. Authorization is NOT here — it's the unbypassable Authorization plugin
 * (admin controllers are ACL-gated admin+ in their module's acl.ini). See ADMIN.md for the
 * screen template that goes on top of this.
 *
 * @api
 */
abstract class Tiger_Controller_Admin_Action extends Tiger_Controller_Action
{
    /**
     * Initialize the controller and set the admin layout for every admin screen. The admin chrome reads in
     * the CALLER's org translation tier (the tier the Translations screen edits, TIGER-511): the bootstrap
     * folded the site org's, so re-fold when they differ — a no-op on a single-site install.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->_helper->layout()->setLayout('admin');
        Tiger_I18n_Overrides::refold(Tiger_I18n_Overrides::adminOrgId());
    }
}
