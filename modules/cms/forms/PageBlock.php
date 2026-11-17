<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_PageBlock — save a builder selection as a reusable block (TIGER-528).
 *
 * `name` and `html` stay checked by the service, which keeps its specific keys
 * (`cms.block.name_required`, `cms.block.empty` — the latter after <script> is stripped).
 *
 * @api
 */
class Cms_Form_PageBlock extends Cms_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_optional('name'),
            $this->_raw('html'),
            $this->_raw('css'),
        ];
    }
}
