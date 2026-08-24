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
        $tiger  = Zend_Registry::get('Zend_Config')->get('tiger');
        $site   = $tiger ? $tiger->get('site') : null;
        $seo    = $tiger ? $tiger->get('seo') : null;
        $social = $seo ? $seo->get('social') : null;

        $g = static function ($node, $key, $default = '') {
            return ($node && (string) $node->get($key) !== '') ? (string) $node->get($key) : $default;
        };

        $ogImage   = $g($seo, 'og_image');
        $isMediaId = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ogImage);

        $form = new Identity_Form_Identity();
        $form->populate([
            'site_name'        => $g($site, 'name', 'Tiger'),
            'tagline'          => $g($site, 'tagline'),
            'site_description' => $g($site, 'description'),
            'og_image_url'     => $isMediaId ? '' : $ogImage,
            'social_twitter'   => $g($social, 'twitter'),
            'social_facebook'  => $g($social, 'facebook'),
            'social_instagram' => $g($social, 'instagram'),
            'social_linkedin'  => $g($social, 'linkedin'),
            'social_youtube'   => $g($social, 'youtube'),
            'social_github'    => $g($social, 'github'),
        ]);

        $this->view->title    = 'Site Identity — Tiger Admin';
        $this->view->form     = $form;
        $this->view->logoId   = $g($site, 'logo');
        $this->view->faviconId = $g($site, 'favicon');
        $this->view->ogImageId = $isMediaId ? $ogImage : '';
    }
}
