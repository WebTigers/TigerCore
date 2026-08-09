<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Theme_Menus — a theme's declared public menus, read from `configs/menus.ini`.
 *
 * This is the **base tier** of the menu live-override (AGENTS.md "the live-override pattern"):
 * files = base, the `menu` DB table = the override tier. A theme ships the menus its chrome
 * needs as a static `configs/menus.ini`; `Tiger_Menu` renders them live when the DB has no rows
 * for a key, and the CMS Menus admin can **import** (clone) them into editable DB rows — after
 * which the DB copy wins. Nothing is written on install/activate (THEMES.md §4/§5 — import is
 * explicit); the `.ini` simply renders as the fallback.
 *
 * The reader returns each menu as the **same node shape** `Tiger_Model_Menu::tree()` produces —
 * DB column names (`label`, `page_key`, `url`, `icon`, `css_class`, `dom_id`, `link_target`,
 * `link_rel`, `resource`, `privilege`) plus `sort_order` + a nested `children` array — so the
 * existing `Tiger_Menu` render pipeline and the importer both consume it unchanged.
 *
 * The `.ini` schema — one `[section]` per menu key, an ordered `items` map, author-friendly
 * field names (mapped to the DB columns), nesting via `children`:
 *
 *   [primary]
 *   items.home.label     = "Home"
 *   items.home.url       = "/"
 *   items.services.label = "Services"
 *   items.services.children.residential.label = "Residential"
 *   items.services.children.residential.url   = "/services/residential"
 *
 *   [footer-social]
 *   items.tw.label  = "Twitter"
 *   items.tw.url    = "https://twitter.com/acme"
 *   items.tw.target = "_blank"
 *
 * @api
 * @see Tiger_Menu
 */
class Tiger_Theme_Menus
{
    /** The theme-relative path of the menus manifest. */
    const FILE = 'configs/menus.ini';

    /** Author-facing `.ini` field => the `menu` DB column it maps to. */
    protected static $_map = [
        'label'     => 'label',
        'url'       => 'url',
        'page_key'  => 'page_key',
        'icon'      => 'icon',
        'class'     => 'css_class',
        'id'        => 'dom_id',
        'target'    => 'link_target',
        'rel'       => 'link_rel',
        'resource'  => 'resource',
        'privilege' => 'privilege',
    ];

    /**
     * Every menu the theme declares, as `menu_key => tree` (each tree a node array).
     *
     * @param  string|null $dir the theme dir to read (null = the active theme)
     * @return array<string,array<int,array<string,mixed>>> the menus, empty when none/unreadable
     */
    public static function all($dir = null)
    {
        $file = self::_file($dir);
        if ($file === '') {
            return [];
        }
        try {
            $cfg = (new Zend_Config_Ini($file))->toArray();
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($cfg as $menuKey => $section) {
            if (!is_array($section)) {
                continue;
            }
            $items = (isset($section['items']) && is_array($section['items'])) ? $section['items'] : [];
            $out[(string) $menuKey] = self::_nodes($items);
        }
        return $out;
    }

    /**
     * The menu keys the theme declares.
     *
     * @param  string|null $dir the theme dir (null = the active theme)
     * @return array<int,string> the declared menu keys
     */
    public static function keys($dir = null)
    {
        return array_keys(self::all($dir));
    }

    /**
     * One declared menu's tree, or [] when the theme doesn't declare it.
     *
     * @param  string      $menuKey the menu key
     * @param  string|null $dir     the theme dir (null = the active theme)
     * @return array<int,array<string,mixed>> the node tree
     */
    public static function tree($menuKey, $dir = null)
    {
        $all = self::all($dir);
        return $all[(string) $menuKey] ?? [];
    }

    /** Build ordered nodes (DB-column keyed) from an ordered `.ini` items map, recursing children. */
    protected static function _nodes(array $items)
    {
        $out  = [];
        $sort = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $node = ['sort_order' => $sort, 'children' => []];
            foreach (self::$_map as $from => $col) {
                if (isset($item[$from]) && (string) $item[$from] !== '') {
                    $node[$col] = (string) $item[$from];
                }
            }
            // A menu item must at least be labelable — skip a malformed entry rather than emit a blank.
            if (!isset($node['label'])) {
                continue;
            }
            if (!empty($item['children']) && is_array($item['children'])) {
                $node['children'] = self::_nodes($item['children']);
            }
            $out[] = $node;
            $sort++;
        }
        return $out;
    }

    /** The absolute menus.ini path for a theme dir (active theme when null), or '' when absent. */
    protected static function _file($dir)
    {
        $base = ($dir !== null && $dir !== '') ? (string) $dir : Tiger_Theme::dir();
        if ($base === '') {
            return '';
        }
        $file = rtrim($base, '/') . '/' . self::FILE;
        return is_file($file) ? $file : '';
    }
}
