<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity_AdminController — the Site Identity screen (site name, tagline, description, logo,
 * favicon, share image, social links). Its own controller (and ACL resource), so access is grantable independently of the rest
 * of the admin — the seam that lets a multi-tenant install hand each org's admin the keys to its
 * own site identity. Thin per ADMIN.md: it renders the form pre-filled from the live config; the
 * save is an /api call (Identity_Service_Identity).
 */
class Identity_AdminController extends Tiger_Controller_Admin_Action
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
     * Render the Site Identity form, pre-filled from the live config. The media references
     * (logo, favicon, share image) are passed to the view separately — the picker field renders
     * their inputs. `tiger.seo.og_image` holds EITHER a media id or an absolute URL (both are
     * resolvable by Seo_Service_Head), so it's split here: a UUID pre-fills the picker, anything
     * else pre-fills the URL element.
     *
     * @return void
     */
    public function indexAction()
    {
        // The caller's org tier (its own value, else the install-wide one) — the tier save() writes,
        // so the screen shows exactly what it will save (TIGER-511).
        $g = static function ($key, $default = '') {
            $v = (string) Tiger_Model_Config::currentValue($key, '');
            return $v !== '' ? $v : $default;
        };

        $ogImage   = $g('tiger.seo.og_image');
        $isMediaId = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ogImage);

        $values = [];
        foreach (Identity_Service_Identity::KEYS as $field => $key) {
            $values[$field] = $g($key);
        }
        if ($values['site_name'] === '') { $values['site_name'] = 'Tiger'; }
        $values['og_image_url'] = $isMediaId ? '' : $ogImage;

        $form = new Identity_Form_Identity();
        $form->populate($values);

        $this->view->title    = 'Site Identity — Tiger Admin';
        $this->view->form     = $form;
        $this->view->logoId   = $g('tiger.site.logo');
        $this->view->faviconId = $g('tiger.site.favicon');
        $this->view->ogImageId = $isMediaId ? $ogImage : '';
    }
}
