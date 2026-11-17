<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_MenuKey — a whole-menu action by key: delete the menu, or revert it to the theme's
 * version (TIGER-528). `menu_key` is required, as before; the org scope is the service's question.
 *
 * @api
 */
class Cms_Form_MenuKey extends Cms_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_required('menu_key'),
            $this->_optional('org_id'),
        ];
    }
}
