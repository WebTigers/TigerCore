<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access_UserController — the Users admin (identity), in the PUMA admin shell.
 *
 * Thin: index renders the DataTables shell (rows load from Access_Service_User::
 * datatable), edit renders the identity form (saving is an /api call). ACL-gated to
 * admin+ (modules/access/configs/acl.ini). Users are deliberately thin identities
 * (email / username / status); profile fields live in an Account module, and role is
 * per-membership (managed in the org context), not here.
 */
class Access_UserController extends Tiger_Controller_Admin_Action
{
    /**
     * Admin shell (layout) comes from the base; keep the explicit init cascade.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Users list — shell only; rows arrive over AJAX.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->title         = 'Users — Tiger Admin';
        $this->view->useDataTables = true;
        $this->view->deleteForm    = new Access_Form_UserDelete();   // renders the grid's delete token
        // The Add/Edit user modal: the form owns its fields + CSRF token; the modal JS fills it over /api.
        $this->view->userForm      = new Access_Form_User();
        // /access/user/index/id/<uuid> opens that user's modal on load (the retired edit page redirects here).
        $id = (string) $this->getParam('id', '');
        $this->view->openUserId    = preg_match('/^[0-9a-f-]{36}$/', $id) ? $id : '';
        $this->view->openNew       = (bool) $this->getParam('new', false);
    }

    /**
     * The old standalone editor — Add/Edit now live in a modal on the Users list. Kept as a redirect so
     * bookmarks and links still land on the right user.
     *
     * @return void
     */
    public function editAction()
    {
        $id = (string) $this->getParam('id', '');
        $this->_redirect(preg_match('/^[0-9a-f-]{36}$/', $id) ? '/access/user/index/id/' . $id : '/access/user/index/new/1');
    }
}
