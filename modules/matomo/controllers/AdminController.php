<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo_AdminController — the Matomo settings screen. Thin per ADMIN.md: renders the form pre-filled
 * from the live config; saving is an /api call (Matomo_Service_Matomo). Its own ACL resource (admin+),
 * so access is grantable independently.
 */
class Matomo_AdminController extends Tiger_Controller_Admin_Action
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
     * Render the settings form, pre-filled from the live config.
     *
     * @return void
     */
    public function indexAction()
    {
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
    }
}
