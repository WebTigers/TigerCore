<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_SiteWrite — the shared base for the Sites screen's writes (save, check seats, delete)
 * (TIGER-528).
 *
 * The Sites screen renders ONE `_csrf` token (from System_Form_Site) and posts it to save, checkSeats
 * and delete. The salt is pinned to `System_Form_Site` — the value that form's token always carried —
 * so every write on the page validates the one rendered token.
 */
abstract class System_Form_SiteWrite extends Tiger_Form
{
    /**
     * One token for every Sites write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'System_Form_Site';
    }
}
