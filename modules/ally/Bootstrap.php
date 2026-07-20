<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerAlly module bootstrap.
 *
 * A first-party accessibility INSPECTOR: it scans rendered content (CMS pages / pasted HTML) for
 * nominal ADA / WCAG-A gaps and reports them. It changes nothing — no auto-fix, no content edits.
 * The scan engine is the platform's Tiger_Ally; this module is the admin surface + the /api around it.
 */
class Ally_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Top-level "Accessibility" sidebar item (ACL-gated to admin+ in the menu). */
    protected function _initAdminNav()
    {
        Tiger_Admin_Nav::register([
            'key'      => 'ally',
            'label'    => 'Accessibility',
            'icon'     => 'fa-universal-access',
            'href'     => '/ally',
            'resource' => 'Ally_IndexController',
            'order'    => 18,
        ]);
    }
}
