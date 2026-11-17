<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Mcp_Form_Write — the shared base for the MCP Connect screen's writes (TIGER-528).
 *
 * The Connect screen renders one token and drives the enable toggle, token minting and revocation from
 * the same page, so the three forms share one CSRF salt (`Tiger_Form::csrfSalt()` defaults to the class,
 * which would make the second call fail "your security token expired"; see `Desk_Form_TicketWrite`).
 *
 * Fields are optional and filter-free: the service already reads each flag leniently (`_flag`), turns
 * `scopes` (an ARRAY or a csv) into sanitized offer keys and refuses an empty set itself, and checks the
 * credential's ownership — so no input it used to accept is refused here.
 */
abstract class Mcp_Form_Write extends Tiger_Form
{
    /**
     * One token for every MCP Connect write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Mcp_Form_Write';
    }

    /**
     * An optional, filter-free field.
     *
     * @param  string $name the field name
     * @return array  [type, name, options]
     */
    protected function _field(string $name): array
    {
        return ['text', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
