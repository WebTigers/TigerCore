<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_MenuReorder — persist a drag-drop reorder of one menu (TIGER-528).
 *
 * `tree` is a JSON array of {menu_id, parent_id, sort_order}; it is required, and whether it decodes
 * to an array stays the service's check (as before). No filters on it — it is a payload, not prose.
 *
 * @api
 */
class Cms_Form_MenuReorder extends Cms_Form_Write
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
            ['hidden', 'tree', ['required' => true, 'filters' => []]],
            $this->_optional('org_id'),
        ];
    }
}
