<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_AdminController — the TigerGit admin screen (TIGER-334 / TIGER-367).
 *
 * Thin per ADMIN.md: it renders the shell (the connect form + the connections dashboard + the
 * releases/activity modals) and gets out of the way. Every action — connect, status, check/apply
 * updates, tag/submit/decide a release, disconnect — is an `/api` call to the Git_Service_* services
 * the screen already exposes (so an operator here and an agent over `/api`/MCP drive the SAME surface).
 * Its own ACL resource (admin+), granted in configs/acl.ini.
 *
 * @since 1.38.0
 */
class Git_AdminController extends Tiger_Controller_Admin_Action
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
     * The git dashboard: connect a module, see connections, drive releases + updates. The forms are
     * rendered for their fields + CSRF token; the data (connections, releases, log) arrives over /api.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->title       = 'Git — Tiger Admin';
        $this->view->connectForm = new Git_Form_Connect();
        $this->view->submitForm  = new Git_Form_Submit();
    }
}
