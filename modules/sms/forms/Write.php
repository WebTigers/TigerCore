<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Sms_Form_Write — the shared base for the SMS settings screen's writes (TIGER-528).
 *
 * The screen renders one token and drives save and the test send from the same page, so both forms
 * share one CSRF salt — `Tiger_Form::csrfSalt()` defaults to the class, which would make the second
 * call fail "your security token expired" (see `Desk_Form_TicketWrite`).
 *
 * @api
 */
abstract class Sms_Form_Write extends Tiger_Form
{
    /**
     * One token for every SMS settings write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Sms_Form_Write';
    }

    /**
     * An optional, filter-free field. The service trims and validates what it reads (the provider
     * allow-list, the blank-keeps-the-secret rule, the required `to`) with its own error keys.
     *
     * @param  string $name the field name
     * @param  string $type the element type
     * @return array  [type, name, options]
     */
    protected function _field(string $name, string $type = 'text'): array
    {
        return [$type, $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
