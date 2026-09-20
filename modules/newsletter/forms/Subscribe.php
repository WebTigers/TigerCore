<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Newsletter_Form_Subscribe — the newsletter opt-in input contract.
 *
 * Two fields: a required, validated email and an optional name. The validators here also power
 * convenience (on-blur) validation and are what `/api/openapi` would reflect as the request schema.
 *
 * CSRF is DISABLED for this form on purpose (csrf() => false): the embeddable form is meant to sit on
 * a cached, anonymously-served page where a session-bound token would either be absent (guest) or
 * stale (a page cached longer than the token's lifetime). A newsletter subscribe is a public, low-value
 * write whose real protection is the double opt-in — nothing is confirmed unless the recipient clicks
 * the emailed link — backed by the honeypot and per-IP rate limit the service applies.
 */
class Newsletter_Form_Subscribe extends Tiger_Form
{
    /** Public, cache-served, double-opt-in-protected — a session CSRF token adds friction, not safety. */
    protected function csrf(): bool
    {
        return false;
    }

    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['text', 'email', [
                'required'   => true,
                'filters'    => ['StringTrim', 'StringToLower'],
                'validators' => [['EmailAddress', false, ['allow' => Zend_Validate_Hostname::ALLOW_DNS]]],
                'attribs'    => [
                    'class'        => 'form-control',
                    'type'         => 'email',
                    'autocomplete' => 'email',
                    'placeholder'  => $this->_t('newsletter.form.email'),
                ],
            ]],
            ['text', 'name', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['max' => 191]]],
                'attribs'    => [
                    'class'        => 'form-control',
                    'autocomplete' => 'name',
                    'placeholder'  => $this->_t('newsletter.form.name'),
                ],
            ]],
        ];
    }
}
