<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_NavSort — the admin sidebar's per-user sort order (System_Service_Nav::sort, TIGER-528).
 *
 * `group` is the nav group (root or a submenu key); `keys` is the group's item keys in their new order
 * as a JSON array string. The service sanitizes both and treats a malformed payload as a quiet no-op.
 * The token is rendered by the sidebar partial itself, so the sort works on every admin page.
 *
 * @api
 */
class System_Form_NavSort extends System_Form_UserPrefs
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('group'),
            $this->_field('keys'),
        ];
    }
}
