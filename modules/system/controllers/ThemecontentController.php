<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_ThemecontentController — the Theme content screen (Settings → Theme content, TIGER-455): what this server
 * holds from the remote theme-content source, and an "Update now" button.
 *
 * Thin: it renders the current status from the LOCAL manifest (a page render never fetches — that is network I/O)
 * and the fetch form's token; the fetch itself is System_Service_Themecontent::fetch over /api. See ADMIN.md.
 */
class System_ThemecontentController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();
    }

    public function indexAction()
    {
        $this->view->title  = 'Theme content — Tiger Admin';
        $this->view->status = System_Service_Themecontent::summary(new Tiger_Theme_Remote());
        $this->view->form   = new System_Form_ThemeContent();   // its `_csrf` token guards fetch
    }
}
