<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Email_Form_Reset — drop an org's template override so the shipped default renders again (TIGER-528).
 *
 * `key` is optional and filter-free here: the service checks it against the registered templates and
 * answers anything else with its own `email.error.unknown_template`. The form adds the CSRF token.
 *
 * @api
 */
class Email_Form_Reset extends Email_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [['hidden', 'key', ['required' => false, 'filters' => [], 'validators' => []]]];
    }
}
