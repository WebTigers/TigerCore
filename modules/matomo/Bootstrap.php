<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo module bootstrap.
 *
 * First-party self-hosted analytics — the "paste your Matomo URL + site id" tracker, done the Tiger
 * way: config-backed (no deploy), consent-aware (Tiger_Consent), provider-extensible via the
 * Tiger_Tracking registry, and intelligent about affiliate attribution (it reads the /a/<name> cookie
 * — the contract TigerAffiliates writes — into a Matomo visit-scoped custom dimension). This module
 * owns only the client-side tracker + its settings screen; the cookie-consent banner is a separate
 * feature, and the Matomo server install is a separate app (GPLv3) reached only over HTTP.
 *
 * It is a SIBLING of the analytics (GA4) module — both register as `analytics`-category trackers and
 * both can be active at once; a site can run GA4 and Matomo together, each consent-gated.
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Matomo_Service_* / Matomo_Form_* / Matomo_Plugin_* load by convention; controllers load via the
 * registered module dir; configs/acl.ini + languages/ are picked up by the core globs.
 */
class Matomo_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * Contribute the Matomo tag to the head (config-driven, consent-gated). No explicit stackIndex —
     * the analytics (GA4) sibling already holds 92, and the Zend plugin broker throws (fatally, during
     * the module scan) on a duplicate index; order is irrelevant here since the plugin only appends to a
     * head placeholder, so we let the broker auto-assign a free slot.
     */
    protected function _initMatomoTag()
    {
        Zend_Controller_Front::getInstance()->registerPlugin(new Matomo_Plugin_Tag());
    }

    /**
     * Declare Matomo to the tracking registry so the cookie-consent feature's "auto" mode can tell that
     * tracking is present (and pop the banner). Active = enabled AND a valid Matomo URL + site id are set.
     * Guarded so the module still loads on a Core that predates Tiger_Tracking.
     */
    protected function _initMatomoTracker()
    {
        if (!class_exists('Tiger_Tracking')) {
            return;
        }
        Tiger_Tracking::register('matomo', [
            'label'    => 'Matomo',
            'category' => 'analytics',
            'active'   => Matomo_Plugin_Tag::isConfigured(),
        ]);
    }

    /** List Matomo under the admin Settings tree (ACL-gated to Matomo_AdminController). */
    protected function _initAdminSettings()
    {
        if (!class_exists('Tiger_Admin_Settings')) {
            return;
        }
        Tiger_Admin_Settings::register([
            'key'      => 'matomo',
            'label'    => 'matomo.nav.label',
            'icon'     => 'fa-chart-simple',
            'href'     => '/matomo/admin',
            'resource' => 'Matomo_AdminController',
            'order'    => 41,
        ]);
    }
}
