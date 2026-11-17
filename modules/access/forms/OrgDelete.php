<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access_Form_OrgDelete — soft-delete an organization (Access_Service_Org::delete, TIGER-528).
 *
 * The org id plus the Organizations list's `_csrf` token: a delete-by-id has no prose, but it is a
 * write a cross-site page must not be able to make a signed-in admin's browser perform. `org_id`
 * stays optional so the service keeps its own refusals (`core.api.error.general` for a missing id,
 * `access.org.no_self_delete`).
 *
 * @api
 */
class Access_Form_OrgDelete extends Tiger_Form
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'org_id', ['required' => false, 'filters' => [], 'validators' => []]],
        ];
    }
}
