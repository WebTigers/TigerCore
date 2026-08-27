<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_SettingsController — the site/CMS Settings screen (in the admin shell).
 *
 * Thin: renders the settings form pre-filled from the live config; saving is an /api
 * call (Cms_Service_Settings). ACL-gated admin+ (modules/cms/configs/acl.ini).
 */
class Cms_SettingsController extends Tiger_Controller_Admin_Action
{
    /**
     * Set up the admin shell — the layout comes from the base; keep the explicit init cascade.
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
        $cfg   = Zend_Registry::get('Zend_Config');
        $tiger = $cfg->get('tiger');
        $site  = $tiger ? $tiger->get('site') : null;

        $home = $site ? (string) $site->get('home_page') : '';

        // A stored PATH that isn't one of the offered module pages (an ad-hoc route, or a module
        // since deactivated) must still round-trip: show it in the custom field with "custom path"
        // selected, rather than silently resetting the site's home page to the built-in landing.
        $custom = '';
        if ($home !== '' && $home[0] === '/' && !array_key_exists($home, Cms_Form_Settings::modulePaths())) {
            $custom = $home;
            $home   = Cms_Form_Settings::CUSTOM;
        }

        $form = new Cms_Form_Settings();
        $form->populate([
            'site_name'        => ($site && (string) $site->get('name') !== '') ? (string) $site->name : 'Tiger',
            'home_page'        => $home,
            'home_page_custom' => $custom,
        ]);

        $this->view->title = 'Settings — Tiger Admin';
        $this->view->form  = $form;
    }
}
