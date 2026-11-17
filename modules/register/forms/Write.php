<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register_Form_Write — the shared base for the registration writes (TIGER-528).
 *
 * The Settings → Registration screen and the dashboard widget each render ONE token and drive register,
 * verifyDomain and resendEmail from it, so the forms share one CSRF salt — `Tiger_Form::csrfSalt()`
 * defaults to the class, which would make the second call fail "your security token expired" (see
 * `Desk_Form_TicketWrite`).
 */
abstract class Register_Form_Write extends Tiger_Form
{
    /**
     * One token for every registration write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Register_Form_Write';
    }
}
