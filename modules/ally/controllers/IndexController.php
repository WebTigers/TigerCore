<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Ally_IndexController — the TigerAlly accessibility inspector screen (/ally).
 *
 * Thin: it renders the tool; every scan is an /api call to Ally_Service_Scan. Read-only inspector —
 * it never changes content. Admin+. See ADMIN.md.
 */
class Ally_IndexController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();
    }

    public function indexAction()
    {
        $this->view->title = 'Accessibility — Tiger Admin';
    }
}
