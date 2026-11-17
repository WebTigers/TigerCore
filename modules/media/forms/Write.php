<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_Form_Write — the shared base for every Media write form (TIGER-528).
 *
 * ONE CSRF salt for the module. The Settings screen renders one token and drives save, saveStorage,
 * testConnection and migrateDisk; the Library screen (and the shared media picker) drive update, delete
 * and copyToLibrary. `Tiger_Form::csrfSalt()` defaults to the form class, which would mint a token per
 * form and make the second call on a page fail "your security token expired" — so the set shares this
 * salt (the documented use of that override; `Desk_Form_TicketWrite` is the reference consumer).
 *
 * Every helper declares an OPTIONAL, filter-free field: the services already own presence and meaning
 * (ownership, the adapter's required fields, the disk check) with their own error keys, and several of
 * these params are trimmed or sanitized there. The form is the input contract plus the CSRF token.
 *
 * @api
 */
abstract class Media_Form_Write extends Tiger_Form
{
    /**
     * One token for every media write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Media_Form_Write';
    }

    /**
     * An optional, filter-free field (presence + meaning are the service's question).
     *
     * @param  string $name the field name
     * @param  string $type the element type (text, password, …)
     * @return array  [type, name, options]
     */
    protected function _field(string $name, string $type = 'text'): array
    {
        return [$type, $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
