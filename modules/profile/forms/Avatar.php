<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Form_Avatar — upload or remove the signed-in user's avatar (TIGER-528).
 *
 * No declared fields: the image rides in `$_FILES['file']` and is checked by the service (size, real
 * image type). The form exists for the CSRF token, which the multipart upload carries as a FormData
 * field. It shares Profile_Form_UserProfile's salt — the Basic tab renders that form's token.
 *
 * @api
 */
class Profile_Form_Avatar extends Tiger_Form
{
    /**
     * The Basic tab's token — the one Profile_Form_UserProfile renders.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return Profile_Form_UserProfile::class;
    }
}
