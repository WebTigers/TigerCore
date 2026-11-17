<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Email_Form_Write — the shared base for the transactional-email template writes (TIGER-528).
 *
 * The template editor drives both save and reset from one page, and the list drives reset, so the forms
 * share one CSRF salt — `Tiger_Form::csrfSalt()` defaults to the class, which would make the second call
 * fail "your security token expired" (see `Desk_Form_TicketWrite`).
 *
 * @api
 */
abstract class Email_Form_Write extends Tiger_Form
{
    /**
     * One token for every email-template write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Email_Form_Write';
    }
}
