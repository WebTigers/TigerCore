<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Message_Form_Write — the shared base for every messaging write form (TIGER-528).
 *
 * ONE CSRF salt for the module. The inbox renders one token and drives markRead / markUnread /
 * archive / unarchive / delete / block from the same page; compose sends; the blocked list unblocks.
 * `Tiger_Form::csrfSalt()` defaults to the form class, which would mint a token per form and make the
 * second call on a page fail "your security token expired" — so the set shares this salt (see
 * `Desk_Form_TicketWrite`, the reference consumer).
 */
abstract class Message_Form_Write extends Tiger_Form
{
    /**
     * One token for every messaging write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Message_Form_Write';
    }

    /**
     * An optional, filter-free id field. The service trims it and answers a missing or foreign id with
     * its own key (`message.error.not_found`, `message.error.cannot_block`), so the element only carries
     * the field into the input contract.
     *
     * @param  string $name the field name
     * @return array  [type, name, options]
     */
    protected function _id(string $name): array
    {
        return ['hidden', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
