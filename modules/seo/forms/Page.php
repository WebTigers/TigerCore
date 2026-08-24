<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_Form_Page — the per-page social-card editor: the title, description, and share image an
 * operator authors for ONE public view page (a shipped `.phtml` marketing page with no CMS row).
 *
 * Every field except `page_key` is OPTIONAL by design: blank does not mean "empty card", it means
 * *"use the fallback"* — the page's own `<title>`, then `tiger.site.description`, then
 * `tiger.seo.og_image` (see Seo_Service_Head::site). So the form never requires content it can
 * inherit, and Seo_Service_Social turns a blank field into a REMOVED config override rather than a
 * stored empty string that would mask the fallback.
 *
 * The share image has two inputs for the two shapes Head accepts: `image_media_id` (preferred —
 * a Media Library id, which resolves to a real absolute URL plus true pixel dimensions, so the card
 * lays out correctly) and `image_url` (the escape hatch for an image that isn't in the library).
 * The media id is DECLARED here even though the view never renders it — the media-picker field owns
 * that hidden input — because this form is also the `/api` **input contract**: `Seo_Service_Social::save`
 * names it with an `@apiRequest` tag, so `tools/list` (MCP) and `/api/openapi` hand an AI agent a typed
 * inputSchema derived from these very elements. Leaving the media id out would hide the *preferred*
 * way to set an image from every non-browser caller.
 *
 * @api
 * @see Seo_Service_Social  the /api service that validates against this form
 * @see Seo_Service_Pages   the discovery that decides which page_key values are legal
 */
class Seo_Form_Page extends Tiger_Form
{
    /**
     * Declare the form's elements.
     *
     * @return array the element schema
     */
    protected function elements(): array
    {
        return [
            // The page being edited. Shaped like a config segment ([a-z0-9-]) because it becomes one:
            // tiger.seo.page.<page_key>.*. The service additionally checks it against the DISCOVERED
            // pages — this validator only rejects a value that could never be a page key at all.
            ['hidden', 'page_key', [
                'required'   => true,
                'filters'    => ['StringTrim', 'StringToLower'],
                'validators' => [
                    ['StringLength', false, [1, 64]],
                    ['Regex', false, ['pattern' => '/^[a-z0-9-]+$/']],
                ],
            ]],
            ['text', 'title', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [0, 191]]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => $this->_t('seo.page.field.title')],
            ]],
            ['textarea', 'description', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [0, 300]]],
                'attribs'    => [
                    'class'       => 'form-control',
                    'rows'        => 3,
                    'placeholder' => $this->_t('seo.page.field.description'),
                ],
            ]],
            // The preferred share image: a Media Library id. Rendered by the media-picker field in the
            // view (which owns an input of this same name), declared here so a non-browser caller — the
            // AI agent, MCP — sees it in the typed inputSchema and gets a real error for a bad id
            // instead of having it silently ignored.
            ['hidden', 'image_media_id', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [
                    ['Regex', false, ['pattern' => '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i']],
                ],
            ]],
            // The share-image escape hatch. ZF1 ships no URI validator, so a lenient http(s) regex —
            // and because the field isn't required, it only runs when a value is actually present.
            ['text', 'image_url', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '#^https?://.+#i']]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => 'https://…'],
            ]],
        ];
    }
}
