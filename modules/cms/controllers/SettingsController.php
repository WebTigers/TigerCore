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
        // The caller's org tier (its own value, else the install-wide one, else the ini) — the tier
        // Cms_Service_Settings writes (TIGER-511), so the screen shows exactly what it will save.
        $home = (string) Tiger_Model_Config::currentValue('tiger.site.home_page', '');
        $name = (string) Tiger_Model_Config::currentValue('tiger.site.name', '');
        $gen  = (string) Tiger_Model_Config::currentValue('tiger.site.generator_meta', '');

        $form = new Cms_Form_Settings();
        $form->populate([
            'site_name' => $name !== '' ? $name : 'Tiger',
            'home_page' => $home,
            // Config holds the flag positively; the checkbox asks "hide?" — invert on the way in.
            'hide_generator' => ($gen !== '' && !filter_var($gen, FILTER_VALIDATE_BOOLEAN)) ? 1 : 0,
        ]);

        $this->view->title = 'Settings — Tiger Admin';
        $this->view->form  = $form;
        // The combobox shows the CURRENT value's friendly label (a page title, "Grey Mist — Home",
        // a path…); the hidden field holds the raw value. Resolved by the same authority the search uses.
        $this->view->homeValue = $home;
        $this->view->homeLabel = Cms_Service_Paths::labelFor($home);
    }
}
