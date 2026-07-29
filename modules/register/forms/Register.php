<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register_Form_Register — the one field registration needs: the admin email (identity + recovery +
 * notifications). The domain is auto-detected, not asked. A real browser admin form, so it keeps CSRF.
 */
class Register_Form_Register extends Tiger_Form
{
    protected function elements(): array
    {
        return [
            ['text', 'email', [
                'required'   => true,
                'filters'    => ['StringTrim', 'StringToLower'],
                'validators' => [['EmailAddress']],
                'attribs'    => ['class' => 'form-control', 'id' => 'reg-email', 'placeholder' => 'you@yourdomain.com'],
            ]],
        ];
    }
}
