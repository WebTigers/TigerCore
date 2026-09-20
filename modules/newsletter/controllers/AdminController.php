<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Newsletter_AdminController — the subscriber list.
 *
 * Thin, per ADMIN.md: it renders the screen and the grid loads from `/api`
 * (Newsletter_Service_Subscribe::datatable). Admin-only (modules/newsletter/configs/acl.ini).
 */
class Newsletter_AdminController extends Tiger_Controller_Admin_Action
{
    /** Admin shell comes from the base; keep the explicit cascade hook. */
    public function init()
    {
        parent::init();
    }

    /** The subscriber list. */
    public function indexAction()
    {
        $this->view->title    = 'Newsletter — Tiger Admin';
        $this->view->statuses = Newsletter_Model_Subscriber::STATUSES;
    }
}
