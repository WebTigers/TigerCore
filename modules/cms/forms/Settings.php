<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_Settings — site/CMS settings (site name + the home page served at "/").
 *
 * Values are stored in the `config` table (scope=global) by Cms_Service_Settings — NOT a settings table
 * (config-discipline: config store + registry, no option landfill). The home page is **any valid path**,
 * so `home_page` is a free value the combobox fills in from `Cms_Service_Paths` (a CMS `page_id`, a PATH
 * like "/marketplace", a theme page "@theme:<key>[:<slug>]", or '' for the built-in landing) — and a dev
 * can type any route the discovery list never saw. The validator just refuses obvious junk.
 *
 * @api
 */
class Cms_Form_Settings extends Tiger_Form
{
    /** Accepted home_page shapes: '' (empty passes) · /path · @theme:key[:slug] · a UUID page_id. */
    const HOME_PATTERN = '~^(/[A-Za-z0-9/_\-.]*|@theme:[A-Za-z0-9_\-]+(:[A-Za-z0-9/_\-]+)?|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$~';

    protected function elements(): array
    {
        $control = ['class' => 'form-control'];

        return [
            ['text', 'site_name', [
                'required' => true,
                'filters'  => ['StringTrim'],
                'attribs'  => array_merge($control, ['id' => 'set-site-name', 'maxlength' => 191]),
            ]],
            // The stored home-page value. Rendered hidden; the combobox (a visible search input +
            // Cms_Service_Paths) writes into it, and a free-typed path lands here directly.
            ['text', 'home_page', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => self::HOME_PATTERN]]],
                'attribs'    => ['id' => 'set-home-page'],
            ]],
        ];
    }
}
