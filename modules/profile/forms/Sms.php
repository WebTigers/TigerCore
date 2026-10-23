<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Form_Sms — the Phone & SMS card form on the profile Security tab.
 *
 * Carries the CSRF token the AJAX-reused card rotates (Profile_Service_Base::_freshToken), plus the
 * fields the two-step opt-in posts: `phone` (E.164, written by intl-tel-input), the `consent` tick
 * (the TCPA opt-in, required before a code is sent), and the verification `code`. The service does the
 * real validation (E.164 normalization, consent presence, code redemption), so these stay lightweight.
 */
class Profile_Form_Sms extends Tiger_Form
{
    /**
     * @return array the Tiger_Form element definitions
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'phone', ['filters' => ['StringTrim']]],          // canonical E.164 from intl-tel-input
            ['checkbox', 'consent', []],                                 // the opt-in tick (required by the service)
            ['text', 'code', [
                'filters'    => ['StringTrim', 'Digits'],
                'validators' => [['StringLength', true, ['min' => 0, 'max' => 10]]],
                'attribs'    => ['class' => 'form-control', 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code'],
            ]],
        ];
    }
}
