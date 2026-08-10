<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Service_Menu — the /api service for the CMS Menus admin.
 *
 * Thin, ACL-gated (admin+, modules/cms/configs/acl.ini), validate-then-write. Powers the
 * menus list (datatable) and the item editor: save/delete an item, delete a whole menu,
 * and reorder (the drag-drop persistence — a batch re-parent + re-sort in one txn). All
 * queries live in Tiger_Model_Menu; this service validates + shapes.
 *
 * @api
 */
class Cms_Service_Menu extends Tiger_Service_Service
{
    /**
     * Return the DataTables source for the menus list: one row per (org, menu_key) with an item count.
     *
     * @param  array $params the DataTables request payload
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $dt        = $this->_dtParams($params);
        $canEdit   = $this->_isAdmin(static::class, 'save');
        $canDelete = $this->_isAdmin(static::class, 'deleteMenu');

        // Theme display names (all installed) so a menu shows its origin theme even after deactivation.
        $active = Tiger_Theme::active();                 // {key, name, dir}
        $names  = Tiger_Theme::names();
        if ($active['key'] !== '') { $names[$active['key']] = $active['name']; }

        // Override tier: the DB menus, keyed "org|key".
        $db = [];
        foreach ((new Tiger_Model_Menu())->groupList() as $r) {
            $db[$r['org_id'] . '|' . $r['menu_key']] = $r;
        }
        // Base tier: the active theme's file menus (global scope, not yet forked to DB).
        $themeCounts = [];
        foreach (Tiger_Theme_Menus::all() as $key => $tree) {
            $themeCounts[$key] = $this->_countNodes($tree);
        }

        $merged = [];
        foreach ($themeCounts as $key => $count) {
            if (isset($db['|' . $key])) { continue; }   // a DB row exists — emitted below
            $merged[] = ['menu_key' => $key, 'org_id' => '', 'items' => $count, 'updated' => '',
                         'type' => 'theme', 'source_key' => $active['key'], 'has_db' => false];
        }
        foreach ($db as $r) {
            $isTheme = ((string) $r['source'] === 'theme');
            $merged[] = [
                'menu_key'   => $r['menu_key'],
                'org_id'     => (string) $r['org_id'],
                'items'      => (int) $r['items'],
                'updated'    => substr((string) $r['updated'], 0, 16),
                'type'       => $isTheme ? 'theme' : 'custom',
                'source_key' => (string) ($r['source_key'] ?? ''),
                'has_db'     => true,
            ];
        }
        foreach ($merged as &$m) {
            $m['source_name'] = ($m['type'] === 'theme')
                ? ($names[$m['source_key']] ?? ($m['source_key'] !== '' ? $m['source_key'] : 'Theme'))
                : '';
        }
        unset($m);

        // Search (key or theme name) + sort + paginate in PHP (menus are few).
        $total  = count($merged);
        $search = trim((string) $dt['search']);
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $merged = array_values(array_filter($merged, static function ($m) use ($needle) {
                return mb_strpos(mb_strtolower($m['menu_key']), $needle) !== false
                    || mb_strpos(mb_strtolower((string) $m['source_name']), $needle) !== false;
            }));
        }
        $filtered = count($merged);

        $cols = [0 => 'menu_key', 1 => 'type', 2 => 'source_name', 3 => 'items', 4 => 'updated'];
        $ck   = $cols[(int) ($dt['order'][0]['column'] ?? 0)] ?? 'menu_key';
        $dir  = (strtoupper((string) ($dt['order'][0]['dir'] ?? 'asc')) === 'DESC') ? -1 : 1;
        usort($merged, static function ($a, $b) use ($ck, $dir) {
            $av = $a[$ck] ?? ''; $bv = $b[$ck] ?? '';
            return (($ck === 'items') ? ($av <=> $bv) : strcasecmp((string) $av, (string) $bv)) * $dir;
        });
        $page = array_slice($merged, (int) $dt['start'], (int) $dt['length']);

        $rows = [];
        foreach ($page as $m) {
            $rows[] = [
                'menu_key'    => $m['menu_key'],
                'org_id'      => (string) $m['org_id'],
                'scope'       => ((string) $m['org_id'] === '') ? 'Global' : 'Org',
                'type'        => $m['type'],          // theme | custom
                'source_name' => $m['source_name'],   // origin theme's name, or '' for custom
                'items'       => (int) $m['items'],
                'updated'     => (string) $m['updated'],
                'can_edit'    => $canEdit,
                'can_delete'  => $canDelete && $m['has_db'] && $m['type'] === 'custom',
                'can_revert'  => $canDelete && $m['has_db'] && $m['type'] === 'theme',
            ];
        }
        $this->_dtResponse($dt['draw'], $total, $filtered, $rows);
    }

    /** Total node count of a menu tree (item + descendants). */
    protected function _countNodes(array $nodes): int
    {
        $n = 0;
        foreach ($nodes as $node) {
            $n += 1 + $this->_countNodes($node['children'] ?? []);
        }
        return $n;
    }

    /**
     * Create or update one menu item (insert when menu_id is empty).
     *
     * @param  array $params the request payload (item fields + menu_key/org_id/parent_id)
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $menuKey = trim((string) ($params['menu_key'] ?? ''));
        if ($menuKey === '') { $this->_error('cms.menu.key_required'); return; }

        $form = new Cms_Form_MenuItem();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $data = [
            'label'       => $v['label'],
            'page_key'    => $this->_nn($v['page_key']),
            'url'         => $this->_nn($v['url']),
            'icon'        => $this->_nn($v['icon']),
            'css_class'   => $this->_nn($v['css_class']),
            'dom_id'      => $this->_nn($v['dom_id']),
            'link_target' => $this->_nn($v['link_target']),
            'link_rel'    => $this->_nn($v['link_rel']),
            'resource'    => $this->_nn($v['resource']),
            'privilege'   => $this->_nn($v['privilege']),
            'status'      => ($v['status'] === 'draft') ? 'draft' : 'published',
        ];

        $model = new Tiger_Model_Menu();
        $orgId = (string) ($params['org_id'] ?? '');

        try {
            $out = $this->_transaction(function () use ($model, $menuKey, $orgId, $params, $data) {
                // First edit of a theme menu materializes it (fork); map any synthetic editor ids.
                $map      = $this->_ensureForked($menuKey, $orgId);
                $menuId   = !empty($params['menu_id']) ? $this->_mapId($params['menu_id'], $map) : null;
                if ($menuId) {
                    $model->update($data, $model->getAdapter()->quoteInto('menu_id = ?', $menuId));
                    $id = $menuId;
                } else {
                    $parentId           = !empty($params['parent_id']) ? $this->_mapId($params['parent_id'], $map) : null;
                    $data['menu_key']   = $menuKey;
                    $data['org_id']     = $orgId;
                    $data['parent_id']  = $parentId;
                    $data['sort_order'] = $model->nextSort($menuKey, $orgId, $parentId);
                    $id = $model->insert($data);
                }
                return ['id' => $id, 'idmap' => $map];
            });
            $this->_success(['menu_id' => $out['id'], 'idmap' => $out['idmap']], 'cms.menu.item_saved',
                '/cms/menu/edit/key/' . rawurlencode($menuKey));
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Soft-delete one item and its subtree.
     *
     * @param  array $params the request payload (`menu_id`)
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $id = (string) ($params['menu_id'] ?? '');
        if ($id === '') { $this->_error('core.api.error.general'); return; }
        $menuKey = (string) ($params['menu_key'] ?? '');
        $orgId   = (string) ($params['org_id'] ?? '');
        try {
            // Deleting an item from a theme menu materializes it first (fork), then drops the item.
            $map    = $this->_ensureForked($menuKey, $orgId);
            $model  = new Tiger_Model_Menu();
            $n      = $model->deleteItem($this->_mapId($id, $map));
            $this->_success(['deleted' => $n, 'idmap' => $map], 'cms.menu.item_deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Soft-delete an entire menu (every item in the org+key scope).
     *
     * @param  array $params the request payload (`menu_key`, `org_id`)
     * @return void
     */
    public function deleteMenu(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $menuKey = (string) ($params['menu_key'] ?? '');
        $orgId   = (string) ($params['org_id'] ?? '');
        if ($menuKey === '') { $this->_error('core.api.error.general'); return; }
        try {
            $model = new Tiger_Model_Menu();
            $db    = $model->getAdapter();
            $model->softDelete($db->quoteInto('menu_key = ?', $menuKey) . ' AND ' . $db->quoteInto('org_id = ?', $orgId));
            $this->_success(['menu_key' => $menuKey], 'cms.menu.deleted', '/cms/menu');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Persist a drag-drop reorder: `tree` is a JSON array of
     * [{menu_id, parent_id, sort_order}, …]. Only items in the given menu are touched.
     *
     * @param  array $params the request payload (`menu_key`, `org_id`, `tree` JSON)
     * @return void
     */
    public function reorder(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $menuKey = (string) ($params['menu_key'] ?? '');
        $orgId   = (string) ($params['org_id'] ?? '');
        $items   = json_decode((string) ($params['tree'] ?? ''), true);
        if ($menuKey === '' || !is_array($items)) { $this->_error('core.api.error.general'); return; }
        try {
            // A drag on a theme menu materializes it (fork), then persists the order against real ids.
            $map   = $this->_ensureForked($menuKey, $orgId);
            if ($map) {
                foreach ($items as &$it) {
                    $it['menu_id'] = $this->_mapId($it['menu_id'] ?? '', $map);
                    if (!empty($it['parent_id'])) { $it['parent_id'] = $this->_mapId($it['parent_id'], $map); }
                }
                unset($it);
            }
            $n = (new Tiger_Model_Menu())->reorder($items, $menuKey, $orgId);
            $this->_success(['updated' => $n, 'idmap' => $map], 'cms.menu.reordered');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Clone the active theme's declared menus (`configs/menus.ini`) into editable DB rows.
     *
     * The explicit "import starter content" step (THEMES.md §4/§5) for menus: it never runs on
     * install/activate — the theme's `.ini` already renders live via `Tiger_Menu`'s base-tier
     * fallback; this copies it into `menu` rows so the drag-drop builder can edit it, after which
     * the DB copy overrides the file. **Idempotent:** a menu key that already has rows in the
     * target scope is skipped, so re-running never clobbers an edited menu. Pass `menu_key` to
     * import just one; omit it to import all the theme declares.
     *
     * @param  array $params the request payload (optional `menu_key`, optional `org_id`)
     * @return void
     */
    public function importFromTheme(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $themeMenus = Tiger_Theme_Menus::all();
        if (!$themeMenus) { $this->_error('cms.menu.import_none'); return; }

        $orgId    = (string) ($params['org_id'] ?? '');
        $only     = trim((string) ($params['menu_key'] ?? ''));
        $themeKey = $this->_activeThemeKey();
        $model    = new Tiger_Model_Menu();
        $imported = [];
        $skipped  = [];

        try {
            $this->_transaction(function () use ($model, $themeMenus, $orgId, $only, $themeKey, &$imported, &$skipped) {
                foreach ($themeMenus as $key => $tree) {
                    if (($only !== '' && $key !== $only) || !$tree) { continue; }
                    if ($model->itemsForEditor((string) $key, $orgId)) { $skipped[] = $key; continue; }
                    $this->_importNodes($model, $tree, (string) $key, $orgId, null, $themeKey);
                    $imported[] = $key;
                }
            });
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
            return;
        }

        if ($imported) {
            $this->_success(['imported' => $imported, 'skipped' => $skipped], 'cms.menu.imported', '/cms/menu');
        } elseif ($skipped) {
            $this->_success(['imported' => [], 'skipped' => $skipped], 'cms.menu.import_present', '/cms/menu');
        } else {
            $this->_error('cms.menu.import_none');
        }
    }

    /** Insert a theme menu tree (recursively) as provenance-stamped `menu` rows, parents first. */
    protected function _importNodes(Tiger_Model_Menu $model, array $nodes, string $menuKey, string $orgId, ?string $parentId, string $themeKey): void
    {
        $sort = 0;
        foreach ($nodes as $node) {
            $id = $model->insert($this->_themeRow($node, $menuKey, $orgId, $parentId, $sort++, $themeKey));
            if (!empty($node['children'])) {
                $this->_importNodes($model, $node['children'], $menuKey, $orgId, (string) $id, $themeKey);
            }
        }
    }

    /**
     * Revert an overridden menu to the theme's version by soft-deleting its DB rows — the base-tier
     * `menus.ini` then renders again (the inverse of forking).
     *
     * @param  array $params the request payload (`menu_key`, `org_id`)
     * @return void
     */
    public function revertToTheme(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $menuKey = (string) ($params['menu_key'] ?? '');
        $orgId   = (string) ($params['org_id'] ?? '');
        if ($menuKey === '') { $this->_error('core.api.error.general'); return; }
        try {
            $model = new Tiger_Model_Menu();
            $db    = $model->getAdapter();
            $model->softDelete($db->quoteInto('menu_key = ?', $menuKey) . ' AND ' . $db->quoteInto('org_id = ?', $orgId));
            $this->_success(['menu_key' => $menuKey], 'cms.menu.reverted', '/cms/menu');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Fork-on-edit: if a menu has no DB rows in this scope but the active theme declares it,
     * materialize the theme menu into DB rows now and return a `['theme:<sourceKey>' => realId]`
     * map so the caller can translate the editor's synthetic ids. Returns [] when there's nothing
     * to fork (already DB-backed, or not a theme menu).
     *
     * @param  string $menuKey the menu key
     * @param  string $orgId   the tenant scope ('' = global)
     * @return array<string,string> synthetic-id => new real menu_id
     */
    protected function _ensureForked(string $menuKey, string $orgId): array
    {
        $model = new Tiger_Model_Menu();
        if ($menuKey === '' || $model->itemsForEditor($menuKey, $orgId)) {
            return [];   // no key, or already DB-backed — nothing to fork
        }
        $tree = Tiger_Theme_Menus::tree($menuKey);
        if (!$tree) {
            return [];
        }
        $map = [];
        $this->_forkNodes($model, $tree, $menuKey, $orgId, null, $map, $this->_activeThemeKey());
        return $map;
    }

    /** Materialize theme nodes into `menu` rows, recording each node's synthetic id -> new real id. */
    protected function _forkNodes(Tiger_Model_Menu $model, array $nodes, string $menuKey, string $orgId, ?string $parentId, array &$map, string $themeKey): void
    {
        $sort = 0;
        foreach ($nodes as $node) {
            $id = $model->insert($this->_themeRow($node, $menuKey, $orgId, $parentId, $sort++, $themeKey));
            if (!empty($node['_source_key'])) {
                $map['theme:' . $node['_source_key']] = $id;
            }
            if (!empty($node['children'])) {
                $this->_forkNodes($model, $node['children'], $menuKey, $orgId, (string) $id, $map, $themeKey);
            }
        }
    }

    /** One `menu` row from a theme node, provenance-stamped (source=theme, source_key=<theme key>). */
    protected function _themeRow(array $node, string $menuKey, string $orgId, ?string $parentId, int $sort, string $themeKey): array
    {
        return [
            'menu_key'    => $menuKey,
            'org_id'      => $orgId,
            'source'      => 'theme',
            'source_key'  => ($themeKey !== '') ? $themeKey : null,
            'parent_id'   => $parentId,
            'sort_order'  => $sort,
            'label'       => (string) ($node['label'] ?? ''),
            'page_key'    => $this->_nn($node['page_key']    ?? ''),
            'url'         => $this->_nn($node['url']         ?? ''),
            'icon'        => $this->_nn($node['icon']        ?? ''),
            'css_class'   => $this->_nn($node['css_class']   ?? ''),
            'dom_id'      => $this->_nn($node['dom_id']      ?? ''),
            'link_target' => $this->_nn($node['link_target'] ?? ''),
            'link_rel'    => $this->_nn($node['link_rel']    ?? ''),
            'resource'    => $this->_nn($node['resource']    ?? ''),
            'privilege'   => $this->_nn($node['privilege']   ?? ''),
            'status'      => Tiger_Model_Menu::STATUS_PUBLISHED,
        ];
    }

    /** The active theme's key (provenance stamp on forked/imported menus), or '' if none. */
    protected function _activeThemeKey(): string
    {
        return (string) (Tiger_Theme::active()['key'] ?? '');
    }

    /** Translate a synthetic theme id to its materialized real id; a real id passes through. */
    protected function _mapId($id, array $map): ?string
    {
        $id = (string) $id;
        return $id === '' ? null : ($map[$id] ?? $id);
    }

    /** Trim to a value, or null when empty — keeps NULLs clean for optional columns. */
    protected function _nn($value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
