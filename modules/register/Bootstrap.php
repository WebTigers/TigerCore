<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register module bootstrap — the optional site-registration prompt, done the Tiger way: a **dashboard
 * widget** (the user places, collapses, or switches it off like any other), plus a Settings screen. It
 * **disables nothing** and touches no other module. The registration authority is TigerRegistry
 * (registry.webtigers.com); this is the install-side client. Deactivating this module removes the widget —
 * the opt-out. Bundled in tiger-core, active by default.
 */
class Register_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Contribute the registration widget to the admin dashboard (ACL- + activation-gated for free). */
    protected function _initRegisterWidget()
    {
        if (method_exists($this, 'getResourceLoader') && $this->getResourceLoader()) {
            $this->getResourceLoader()->addResourceType('widget', 'widgets', 'Widget');
        }
        if (class_exists('Tiger_Dashboard')) {
            Tiger_Dashboard::registerWidget([
                'id'       => 'register.site',
                'module'   => 'register',
                'title'    => 'register.widget.title',
                'icon'     => 'fa-globe',
                'widget'   => 'Register_Widget_Registration',
                'resource' => 'Register_AdminController',
                'width'    => 1,
                'order'    => 10,
            ]);
        }
    }

    /** List Registration under the admin Settings tree (a full-page view of the same thing). */
    protected function _initRegisterSettings()
    {
        if (class_exists('Tiger_Admin_Settings')) {
            Tiger_Admin_Settings::register([
                'key'      => 'register',
                'label'    => 'register.nav.label',
                'icon'     => 'fa-globe',
                'href'     => '/register/admin/registration',
                'resource' => 'Register_AdminController',
                'order'    => 90,
            ]);
        }
    }
}
