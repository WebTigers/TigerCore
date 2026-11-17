<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_PageDesign — the visual builder's design save (TIGER-528).
 *
 * The full-screen GrapesJS builder posts the page's rendered `html` + `css` (+ `layout_key` for a
 * partial). The markup fields are raw source — no filters — and the service strips <script> as before.
 * Whether `layout_key` was SENT is a presence question the service asks of the raw params.
 *
 * @api
 */
class Cms_Form_PageDesign extends Cms_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_required('page_id'),
            $this->_raw('html'),
            $this->_raw('css'),
            $this->_optional('layout_key'),
        ];
    }
}
