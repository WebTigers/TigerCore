<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access_Form_UserDelete — soft-delete a user (Access_Service_User::delete, TIGER-528).
 *
 * The user id plus the Users list's `_csrf` token. `user_id` stays optional so the service keeps its
 * own refusals (`core.api.error.general` for a missing id, `access.user.no_self_delete`,
 * `access.user.role_not_manageable`).
 *
 * @api
 */
class Access_Form_UserDelete extends Tiger_Form
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'user_id', ['required' => false, 'filters' => [], 'validators' => []]],
        ];
    }
}
