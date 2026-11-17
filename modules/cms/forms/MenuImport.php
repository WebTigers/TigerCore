<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_MenuImport — materialize the active theme's menus into editable rows (TIGER-528).
 *
 * Both fields are optional: an empty `menu_key` imports every menu the theme declares.
 *
 * @api
 */
class Cms_Form_MenuImport extends Cms_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_optional('menu_key'),
            $this->_optional('org_id'),
        ];
    }
}
