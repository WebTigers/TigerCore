<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_AdminController — the Social Cards screen: the Open Graph title, description, and share image
 * for each PUBLIC VIEW PAGE (the shipped `.phtml` marketing pages, which have no CMS row to carry
 * their own `meta.seo`).
 *
 * Thin per ADMIN.md: it renders the screen shell + the editor form. The page list itself is FETCHED
 * from `/api` (`Seo_Service_Social::pages`) rather than server-rendered — a grid is data, and data
 * comes from a service (AGENTS.md, the client/server section) — and the save is the matching
 * `Seo_Service_Social::save` call. Its own ACL resource, so a multi-tenant install can hand an org's
 * admin the keys to its own social cards independently of the rest of the back office.
 */
class Seo_AdminController extends Tiger_Controller_Admin_Action
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
     * Render the Social Cards screen: the page-list shell plus the per-page editor form (one form,
     * reused by the modal for whichever row is being edited — the values are loaded over `/api`).
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->title = 'Social Cards — Tiger Admin';
        $this->view->form  = new Seo_Form_Page();
    }
}
