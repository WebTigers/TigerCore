<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Form_ContactDelete — delete one contact link, the user's or the org's (TIGER-528).
 *
 * Replaces the hand-rolled `_validCsrf()` check. Shares Profile_Form_Contact's salt, because the
 * Contacts card renders that form's token and posts its deletes with it. Ownership of `link_id` stays
 * the service's check.
 *
 * @api
 */
class Profile_Form_ContactDelete extends Tiger_Form
{
    /**
     * The Contacts card's token — the one Profile_Form_Contact renders and rotates.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return Profile_Form_Contact::class;
    }

    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'link_id', ['required' => false, 'filters' => ['StringTrim']]],
        ];
    }
}
