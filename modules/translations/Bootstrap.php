<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translations module bootstrap — the admin screen for searching, editing, and overriding
 * translation strings per locale (the UI over the `translation` DB override tier).
 *
 * First-party, additive: it surfaces `/translations` in the admin sidebar and nothing else.
 * The resource autoloader (from Zend_Application_Module_Bootstrap) loads Translations_Service_*
 * by convention; configs/acl.ini + languages/ are picked up by the core globs.
 */
class Translations_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Top-level "Translations" item in the admin sidebar (ACL-gated to admin+ in the menu). */
    protected function _initAdminNav()
    {
        if (!class_exists('Tiger_Admin_Nav')) {
            return;
        }
        Tiger_Admin_Nav::register([
            'key'      => 'translations',
            'label'    => 'Translations',
            'icon'     => 'fa-language',
            'href'     => '/translations',
            'resource' => 'Translations_IndexController',
            'order'    => 70,
        ]);
    }
}
