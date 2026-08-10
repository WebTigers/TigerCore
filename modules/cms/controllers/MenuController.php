<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_MenuController — the CMS Menus admin (admin shell). Two thin screens:
 * a DataTables list of menus (index) and the item editor for one menu (edit).
 *
 * Thin by rule: this reads + renders only; every mutation goes through the /api
 * service Cms_Service_Menu (save/delete/deleteMenu/reorder). ACL-gated admin+ via
 * modules/cms/configs/acl.ini. The drag-drop reordering UI builds on the tree this
 * renders (each item carries data-menu-id for the sortable to persist via reorder).
 */
class Cms_MenuController extends Tiger_Controller_Admin_Action
{
    /**
     * Set up the admin shell — the layout comes from the base; keep the explicit init cascade.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Render the menus list — shell only; rows load over AJAX (Cms_Service_Menu::datatable).
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->title         = 'Menus — Tiger Admin';
        $this->view->useDataTables = true;
    }

    /**
     * Edit one menu (by key), or start a new one (no key). Renders the item tree + editor.
     *
     * @return void
     */
    public function editAction()
    {
        $key   = (string) $this->getParam('key', '');
        $orgId = '';   // global scope for now (per-tenant menu editing is a later concern)

        $model = new Tiger_Model_Menu();
        $tree  = ($key !== '') ? $model->tree($key, $orgId, false) : [];

        // No DB rows but the active theme declares this menu -> edit the theme's version (base tier),
        // rendered with synthetic ids; the first save materializes it (fork-on-edit). See Cms_Service_Menu.
        $isTheme = false;
        if ($key !== '' && !$tree) {
            $themeTree = Tiger_Theme_Menus::tree($key);
            if ($themeTree) {
                $tree    = $this->_themeEditorTree($themeTree);
                $isTheme = true;
            }
        }

        $this->view->menuKey = $key;
        $this->view->orgId   = $orgId;
        $this->view->tree    = $tree;
        $this->view->isTheme = $isTheme;
        $this->view->form    = new Cms_Form_MenuItem();
        $this->view->pages   = $this->_pagePalette();
        $this->view->title   = ($key !== '' ? 'Edit Menu' : 'New Menu') . ' — Tiger Admin';
    }

    /**
     * Normalize a theme menu tree (Tiger_Theme_Menus) into the editor's row shape: a synthetic
     * `theme:<sourceKey>` menu_id, a full column set, and published status — so the builder renders
     * it like any menu, and the first mutation forks it (Cms_Service_Menu::_ensureForked).
     */
    protected function _themeEditorTree(array $nodes)
    {
        $cols = ['label' => null, 'page_key' => null, 'url' => null, 'icon' => null, 'css_class' => null,
                 'dom_id' => null, 'link_target' => null, 'link_rel' => null, 'resource' => null, 'privilege' => null];
        $out = [];
        foreach ($nodes as $n) {
            $row             = array_merge($cols, array_intersect_key($n, $cols));
            $row['menu_id']  = 'theme:' . ($n['_source_key'] ?? '');
            $row['status']   = Tiger_Model_Menu::STATUS_PUBLISHED;
            $row['children'] = $this->_themeEditorTree($n['children'] ?? []);
            $out[] = $row;
        }
        return $out;
    }

    /** Published pages with a stable key — the "Pages" source column for the builder. */
    protected function _pagePalette()
    {
        $pm   = new Tiger_Model_Page();
        $rows = $pm->fetchAll(
            $pm->activeSelect()
               ->where('type = ?', Tiger_Model_Page::TYPE_PAGE)
               ->where('page_key IS NOT NULL')
               ->order(['title ASC', 'page_key ASC'])
        );
        $out = [];
        foreach ($rows as $p) {
            if ((string) $p->page_key === '') { continue; }
            $out[] = ['page_key' => (string) $p->page_key, 'title' => (string) ($p->title ?: $p->slug ?: $p->page_key)];
        }
        return $out;
    }
}
