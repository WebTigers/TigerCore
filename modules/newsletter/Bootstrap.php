<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Newsletter_Bootstrap — wires up newsletter subscriber collection.
 *
 * Three seams, each guarded so the module is inert wherever the host registry is absent:
 *   - the `[newsletter_form]` shortcode, so a page or theme can drop the embeddable form in with no code;
 *   - the admin nav item (the subscriber list);
 *   - the Tiger_Audience provider, so confirmed subscribers become a pickable email segment for a
 *     consumer such as TigerList — which then applies its OWN consent/suppression before sending.
 *
 * The module carries no separate enabled flag: it is opt-in at the module-activation layer (type
 * "app"), and the form is inert until an author actually places the shortcode.
 */
class Newsletter_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** The `[newsletter_form]` shortcode — the embeddable subscribe form, no code required. */
    protected function _initNewsletterShortcode()
    {
        if (!class_exists('Tiger_Cms_Renderer')) { return; }

        Tiger_Cms_Renderer::registerShortcode('newsletter_form', static function ($attrs) {
            return (new Newsletter_Service_Render())->form(is_array($attrs) ? $attrs : []);
        });
    }

    /** The subscriber list, under Marketing in the admin sidebar. */
    protected function _initNewsletterNav()
    {
        if (!class_exists('Tiger_Admin_Nav')) { return; }

        Tiger_Admin_Nav::register([
            'key'      => 'newsletter',
            'label'    => 'newsletter.nav.label',
            'icon'     => 'fa-envelope-open-text',
            'href'     => '/newsletter/admin',
            'resource' => 'Newsletter_AdminController',
            'order'    => 40,
        ]);
    }

    /**
     * Offer confirmed subscribers as an audience segment.
     *
     * Tiger_Audience is CORE, so this always registers; a consumer (TigerList) reads the registry when
     * present. The provider conveys OPT-IN membership only — the consumer owns consent at send time.
     */
    protected function _initNewsletterAudience()
    {
        if (!class_exists('Tiger_Audience')) { return; }

        Tiger_Audience::register('newsletter', [
            'label'    => 'Newsletter',
            'segments' => [Newsletter_Service_Audience::class, 'listSegments'],
            'resolve'  => [Newsletter_Service_Audience::class, 'resolveMembers'],
        ]);
    }
}
