<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Admin_Nav — the admin sidebar's TOP-LEVEL nav registry (the module hook).
 *
 * Sibling of Tiger_Admin_Settings: where that adds a page under the **Settings** submenu, this adds
 * a **top-level** item to the admin sidebar. A module contributes one from its Bootstrap — no core
 * file to edit, ACL-gated + activation-gated for free (an inactive module never bootstraps, so its
 * item never registers). The PUMA `admin-menu` partial merges these in ahead of Settings:
 *
 *   Tiger_Admin_Nav::register([
 *       'key'      => 'docs_help',                 // unique; dedupes
 *       'label'    => 'Help',
 *       'icon'     => 'fa-circle-question',
 *       'href'     => '/docs/admin/help',
 *       'match'    => '/docs/admin/help',          // path prefix that marks it active (default: href)
 *       'resource' => 'Docs_AdminController',      // ACL resource — the item hides if denied
 *       'order'    => 90,                          // sort weight among registered items (lower first)
 *   ]);
 *
 * @api
 */
class Tiger_Admin_Nav
{
    /** @var array<string,array> key => item definition */
    protected static $_items = [];

    /** Register (or replace, by key) a top-level nav item. Requires key, label, href. */
    public static function register(array $item)
    {
        if (empty($item['key']) || empty($item['label']) || empty($item['href'])) {
            return;
        }
        self::$_items[$item['key']] = $item + [
            'icon'     => 'fa-circle',
            'match'    => $item['href'],
            'resource' => null,
            'order'    => 100,
        ];
    }

    /**
     * The registered items as sidebar nav-item arrays (label/href/match/icon/resource), sorted by
     * order then label. ACL filtering happens in the menu partial, live per role.
     *
     * @return array<int,array>
     */
    public static function items()
    {
        $items = array_values(self::$_items);
        usort($items, static function ($a, $b) {
            return [$a['order'], $a['label']] <=> [$b['order'], $b['label']];
        });
        return array_map(static function ($p) {
            return [
                'label'    => $p['label'],
                'href'     => $p['href'],
                'match'    => $p['match'],
                'icon'     => $p['icon'],
                'resource' => $p['resource'],
            ];
        }, $items);
    }

    /** Reset the registry (tests). */
    public static function clear()
    {
        self::$_items = [];
    }
}
