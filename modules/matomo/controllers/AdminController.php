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
        $tiger = Zend_Registry::get('Zend_Config')->get('tiger');
        $m     = $tiger ? $tiger->get('matomo') : null;
        $aff   = $m ? $m->get('affiliate') : null;

        $form = new Matomo_Form_Settings();
        $form->populate([
            'matomo_url'                    => $m ? (string) $m->get('url') : '',
            'matomo_site_id'                => $m && $m->get('site_id') !== null ? (string) $m->get('site_id') : '',
            'matomo_affiliate_dimension_id' => $aff && $aff->get('dimension_id') !== null ? (string) $aff->get('dimension_id') : '1',
        ]);

        $b = static function ($node, $key, $default = false) {
            return $node && $node->get($key) !== null
                ? filter_var((string) $node->get($key), FILTER_VALIDATE_BOOLEAN)
                : $default;
        };

        $this->view->title           = 'Matomo — Tiger Admin';
        $this->view->form            = $form;
        $this->view->enabled         = $b($m, 'enabled', false);
        $this->view->excludeSignedIn = $b($m, 'exclude_signed_in', true);
    }
}
