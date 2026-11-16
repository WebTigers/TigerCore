<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo_AdminController — the Matomo settings screen. Since 1.61 Matomo is set up in ONE place: the
 * Analytics screen's "Matomo" provider panel (`/analytics/admin?provider=matomo`), which renders this
 * module's own form and posts to this module's own service — so there are never two screens that
 * disagree. This action redirects there; only when the analytics module is not active does it render
 * its own copy of the same form (same Matomo_Form_Settings, same Matomo_Service_Matomo::save).
 * Its own ACL resource (admin+), so access is grantable independently.
 */
class Matomo_AdminController extends Tiger_Controller_Admin_Action
{
    /** Where Matomo is set up when the analytics module is active. */
    const SETUP_URL = '/analytics/admin?provider=matomo';

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
     * Send the admin to the one Matomo setup screen; render the fallback form only without analytics.
     *
     * @return void
     */
    public function indexAction()
    {
        if (class_exists('Analytics_Form_Provider')) {
            $this->_redirect(self::SETUP_URL);
            return;
        }

        // The caller's org tier (its own value, else the install-wide one) — the tier the save writes
        // (TIGER-511), so the screen shows exactly what it will save.
        $v = static function ($key, $default = '') {
            return (string) Tiger_Model_Config::currentValue($key, $default);
        };

        $form = new Matomo_Form_Settings();
        $form->populate([
            'matomo_url'                    => $v('tiger.matomo.url'),
            'matomo_site_id'                => $v('tiger.matomo.site_id'),
            'matomo_affiliate_dimension_id' => $v('tiger.matomo.affiliate.dimension_id', '1'),
        ]);

        $b = static function ($key, $default = false) {
            $raw = Tiger_Model_Config::currentValue($key);
            return $raw !== null ? filter_var((string) $raw, FILTER_VALIDATE_BOOLEAN) : $default;
        };

        $this->view->title           = 'Matomo — Tiger Admin';
        $this->view->form            = $form;
        $this->view->enabled         = $b('tiger.matomo.enabled', false);
        $this->view->excludeSignedIn = $b('tiger.matomo.exclude_signed_in', true);
        $this->view->hasToken        = (new Tiger_Analytics_Adapter_Matomo())->hasToken((string) Tiger_Model_Config::currentOrgId());
    }
}
