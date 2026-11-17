<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_MenuItemDelete — remove one menu item and its subtree (TIGER-528).
 *
 * `menu_id` is required (as before); `menu_key` + `org_id` are the context the builder sends. The
 * org-scope and ownership checks stay in the service.
 *
 * @api
 */
class Cms_Form_MenuItemDelete extends Cms_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_required('menu_id'),
            $this->_optional('menu_key'),
            $this->_optional('org_id'),
        ];
    }
}
