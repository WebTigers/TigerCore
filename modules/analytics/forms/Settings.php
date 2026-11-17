<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics_Form_Settings — the Google Analytics settings form. Only the GA4 Measurement ID is a
 * validated field; the two booleans (enabled, exclude signed-in staff) are simple switches the view
 * renders and the service reads from $params. The ID is optional but, when present, must look like a
 * GA4 id (`G-XXXXXXX`) — validated here and again in the plugin before it's ever emitted.
 *
 * @api
 */
class Analytics_Form_Settings extends Tiger_Form
{
    /**
     * Declare the form's elements.
     *
     * @return array the element schema
     */
    protected function elements(): array
    {
        return [
            ['text', 'ga4_measurement_id', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '/^G-[A-Z0-9]{4,}$/i']]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => 'G-XXXXXXXXXX', 'autocomplete' => 'off'],
            ]],

            // The rest of what Analytics_Service_Analytics::save reads (TIGER-528), so the form is the
            // complete input contract. The view writes these inputs by hand; the writers
            // (Tiger_Google_Analytics::saveMode / saveOauthConfig, and the `!empty()` switches) already
            // normalize every value, so each is optional, unfiltered and unvalidated — declaring them
            // must not refuse an input the save accepted before.
            $this->_open('enabled'),
            $this->_open('exclude_signed_in'),
            $this->_open('oauth_mode'),
            $this->_open('oauth_client_id'),
            $this->_open('oauth_client_secret'),
            $this->_open('property_id'),
            $this->_open('mp_api_secret'),   // GA4 Measurement Protocol API secret (server-side events, TIGER-525)
        ];
    }

    /**
     * An optional, unfiltered, unvalidated element — its writer owns normalization.
     *
     * @param  string $name the element/param name
     * @return array        [type, name, options]
     */
    protected function _open(string $name): array
    {
        return ['hidden', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }

    /**
     * The salt every analytics settings form shares (Analytics_Form_Provider rides this same token).
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Analytics';
    }
}
