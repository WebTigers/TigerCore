<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_DashboardLayout — the dashboard's widget layout and visibility
 * (System_Service_Dashboard::saveLayout / saveWidgetPrefs, TIGER-528).
 *
 * `layout` is the JSON {"order":[…],"collapsed":{…}} saveLayout reads; `hidden` is the JSON array of
 * switched-off widget ids saveWidgetPrefs reads. Both are parsed fail-soft by the service.
 *
 * @api
 */
class System_Form_DashboardLayout extends System_Form_UserPrefs
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('layout'),
            $this->_field('hidden'),
        ];
    }
}
