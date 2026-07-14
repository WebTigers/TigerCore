<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media module bootstrap. First-party module — the admin Media Library on top of the
 * platform media engine (Tiger_Model_Media, Tiger_Media_Storage). The resource
 * autoloader loads Media_Service_* / Media_*Controller by convention; configs/acl.ini
 * and languages/ are picked up by the core globs.
 */
class Media_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Register the Media settings screen into the shared admin Settings tree (ACL-filtered). */
    protected function _initAdminSettings()
    {
        Tiger_Admin_Settings::register([
            'key'      => 'media',
            'label'    => 'Media',
            'icon'     => 'fa-photo-film',
            'href'     => '/media/admin/settings',
            'resource' => 'Media_AdminController',
            'order'    => 40,
        ]);
    }
}
