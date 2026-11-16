<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo_Form_Settings — the Matomo settings form. Four validated fields: the Matomo base URL
 * (optional, but when present must be an http(s) URL), the numeric site id (idsite), the Reporting
 * API token (write-only — never rendered back), and the affiliate custom-dimension index. The two booleans (enabled, exclude signed-in staff) are plain switches the
 * view renders and the service reads from $params. The URL + site id are validated here and again in
 * the plugin before anything is ever emitted.
 *
 * @api
 */
class Matomo_Form_Settings extends Tiger_Form
{
    /**
     * Declare the form's elements.
     *
     * @return array the element schema
     */
    protected function elements(): array
    {
        return [
            ['text', 'matomo_url', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '#^https?://[^\s"\'<>]+$#i']]],
                'attribs'    => ['class' => 'form-control', 'placeholder' => 'https://analytics.example.com/', 'autocomplete' => 'off'],
            ]],
            ['text', 'matomo_site_id', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits']],
                'attribs'    => ['class' => 'form-control', 'placeholder' => '1', 'inputmode' => 'numeric', 'autocomplete' => 'off'],
            ]],
            // The Reporting API token — WRITE-ONLY: the screen never renders a stored value (it shows a
            // "token saved" state instead), and a blank submit keeps the stored one. Matomo tokens are
            // 32 hex chars today; allow a conservative alphanumeric range so a future format still fits.
            ['password', 'matomo_token_auth', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '/^[A-Za-z0-9_-]{8,255}$/']]],
                'attribs'    => ['class' => 'form-control font-monospace', 'autocomplete' => 'new-password', 'spellcheck' => 'false'],
            ]],
            ['text', 'matomo_affiliate_dimension_id', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits']],
                'attribs'    => ['class' => 'form-control', 'placeholder' => '1', 'inputmode' => 'numeric', 'autocomplete' => 'off'],
            ]],
        ];
    }
}
