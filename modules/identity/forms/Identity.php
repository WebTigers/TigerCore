<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity_Form_Identity — the Site Identity form: the site's name, tagline + description and
 * its social profile URLs (which feed Organization.sameAs in the JSON-LD). The logo, favicon
 * and share image are NOT declared here as media fields — they're media references rendered in
 * the view via the media-picker field helper (their hidden inputs post with the form and the
 * service reads them from $params). The share image has one declared element, `og_image_url`:
 * the escape hatch for pointing at an already-absolute image URL instead of a library item
 * (Seo_Service_Head accepts either — a media id resolves to true dimensions, so the picker is
 * preferred). Social URLs are optional and lightly validated (a URL when present); nothing is
 * required but the name.
 *
 * @api
 */
class Identity_Form_Identity extends Tiger_Form
{
    /**
     * Declare the form's elements.
     *
     * @return array the element schema
     */
    protected function elements(): array
    {
        // Optional social URLs: ZF1 ships no URI validator, so a lenient http(s) regex — and because
        // the field isn't required, Zend_Form only runs it when a value is actually present.
        $url = static function () {
            return [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '#^https?://.+#i']]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => 'https://…'],
            ];
        };

        return [
            ['text', 'site_name', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [1, 191]]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => $this->_t('identity.field.site_name')],
            ]],
            ['text', 'tagline', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [0, 191]]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => $this->_t('identity.field.tagline')],
            ]],
            ['textarea', 'site_description', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [0, 300]]],
                'attribs'    => [
                    'class'       => 'form-control',
                    'rows'        => 3,
                    'placeholder' => $this->_t('identity.field.site_description'),
                ],
            ]],
            // The share image's URL escape hatch (the picker owns the media-id input in the view).
            ['text', 'og_image_url', $url()],
            ['text', 'social_twitter',   $url()],
            ['text', 'social_facebook',  $url()],
            ['text', 'social_instagram', $url()],
            ['text', 'social_linkedin',  $url()],
            ['text', 'social_youtube',   $url()],
            ['text', 'social_github',    $url()],
        ];
    }
}
