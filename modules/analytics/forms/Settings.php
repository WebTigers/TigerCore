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
        ];
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
