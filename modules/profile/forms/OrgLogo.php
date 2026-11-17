<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Form_OrgLogo — upload or remove the current org's logo (TIGER-528).
 *
 * No declared fields: the image rides in `$_FILES['file']` and is checked by the service. The form
 * exists for the CSRF token, which the multipart upload carries as a FormData field. It shares
 * Profile_Form_OrgProfile's salt — the org Basic tab renders that form's token.
 *
 * @api
 */
class Profile_Form_OrgLogo extends Tiger_Form
{
    /**
     * The org Basic tab's token — the one Profile_Form_OrgProfile renders.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return Profile_Form_OrgProfile::class;
    }
}
