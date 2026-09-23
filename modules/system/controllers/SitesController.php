<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_SitesController — the multi-site host → org manager (in the admin shell).
 *
 * Thin: renders the grid shell + the "map a domain" form; the list, create and delete are /api calls
 * (System_Service_Sites). ACL-gated admin+ (modules/system/configs/acl.ini). One Tiger install can serve
 * many public sites (one per org) resolved by the request Host — this is where an admin wires host → org.
 */
class System_SitesController extends Tiger_Controller_Admin_Action
{
    /** Admin shell (layout) comes from the base; keep the explicit init cascade. */
    public function init()
    {
        parent::init();
    }

    /** `/system/sites` — the host → org mappings grid + an add-domain form. */
    public function indexAction()
    {
        $this->view->title   = 'Sites — Tiger Admin';
        $this->view->form    = new System_Form_Site();
        $this->view->orgCount = (int) (new Tiger_Model_Org())->fetchAll(
            (new Tiger_Model_Org())->activeSelect()
        )->count();
    }
}
