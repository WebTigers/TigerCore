<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Service_Paths — /api discovery for the home-page selector's combobox.
 *
 * The home page is ANY valid path (a CMS page, a theme page, a module page, a login screen, a dynamic
 * product page the control never sees) — so the control ALWAYS accepts a free-typed value; this service
 * only supplies the *convenience* list of paths it can discover, searchable. Two depths (a checkbox in
 * the UI):
 *   - BASIC   — the uncluttered set: the built-in landing, published CMS pages, each installed theme's
 *               HOME page, and module home prefixes. ("what we have now" + theme homes.)
 *   - ADVANCED — the full litterbox: the above PLUS every installed theme's individual content pages.
 *
 * Values are stored verbatim in `tiger.site.home_page` and resolved by `IndexController`:
 * '' (built-in landing) · a CMS `page_id` · a PATH ("/marketplace") · a theme page ("@theme:<key>" or
 * "@theme:<key>:<slug>"). ACL: admin+ (modules/cms/configs/acl.ini).
 *
 * @api
 */
class Cms_Service_Paths extends Tiger_Service_Service
{
    /** Max options returned per group (a search narrows; a huge theme can't flood the list). */
    const LIMIT = 50;

    /**
     * Search the discoverable valid paths for the combobox.
     *
     * @param  array $params `q` (substring filter), `advanced` (include every theme page)
     * @return void
     */
    public function search(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $q        = strtolower(trim((string) ($params['q'] ?? '')));
        $advanced = !empty($params['advanced']) && (string) $params['advanced'] !== '0';

        $out = [];
        foreach (self::_discover($advanced) as $group) {
            $opts = [];
            foreach ($group['options'] as $o) {
                if ($q === '' || strpos(strtolower($o['label'] . ' ' . $o['value']), $q) !== false) {
                    $opts[] = $o;
                    if (count($opts) >= self::LIMIT) { break; }
                }
            }
            if ($opts) { $out[] = ['label' => $group['label'], 'options' => $opts]; }
        }

        $this->_success(['groups' => $out]);
    }

    /**
     * The human label for a stored home-page value — for rendering the control's initial state. Shared
     * with `Cms_SettingsController`. Unknown/free-typed values echo back as-is (they're paths).
     *
     * @param  string $value the stored `tiger.site.home_page` value
     * @return string
     */
    public static function labelFor($value)
    {
        $value = (string) $value;
        if ($value === '') { return self::_t('cms.settings.opt_builtin_landing'); }

        if (strncmp($value, '@theme:', 7) === 0) {
            $rest  = substr($value, 7);
            $colon = strpos($rest, ':');
            $key   = $colon === false ? $rest : substr($rest, 0, $colon);
            $slug  = $colon === false ? '' : (string) substr($rest, $colon + 1);
            $names = Tiger_Theme::names();
            $name  = $names[$key] ?? $key;
            if ($slug === '' || $slug === 'index') {
                return $name . ' — ' . self::_t('cms.settings.theme_home');
            }
            return $name . ' — ' . ucfirst(str_replace(['-', '/'], ' ', $slug));
        }

        // A CMS page_id (UUID) → its title.
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $page = (new Tiger_Model_Page())->findById($value);
            if ($page) { return (string) ($page->title ?: $page->slug ?: $page->page_key); }
        }

        return $value;   // a path, or an unknown value — shown verbatim
    }

    /**
     * Build the grouped option set (unfiltered). BASIC unless $advanced adds every theme page.
     *
     * @param  bool $advanced
     * @return array<int,array{label:string,options:array<int,array{value:string,label:string}>}>
     */
    protected static function _discover(bool $advanced): array
    {
        $groups = [];

        // General — the built-in landing (value '').
        $groups[] = ['label' => self::_t('cms.settings.optgroup_general'), 'options' => [
            ['value' => '', 'label' => self::_t('cms.settings.opt_builtin_landing')],
        ]];

        // CMS pages — stored as a page_id.
        $pages = [];
        $pm    = new Tiger_Model_Page();
        foreach ($pm->fetchAll(
            $pm->activeSelect()
               ->where('type = ?', Tiger_Model_Page::TYPE_PAGE)
               ->where('status = ?', Tiger_Model_Page::STATUS_PUBLISHED)
               ->order(['title ASC', 'locale ASC'])
        ) as $p) {
            $pages[] = ['value' => (string) $p->page_id, 'label' => ($p->title ?: $p->slug ?: $p->page_key) . ' (' . $p->locale . ')'];
        }
        if ($pages) { $groups[] = ['label' => self::_t('cms.settings.optgroup_pages'), 'options' => $pages]; }

        // Themes — each installed theme's HOME, plus (advanced) its every content page.
        $themes = [];
        foreach (Tiger_Theme::names() as $key => $name) {
            $dir = Tiger_Theme::dirForKey($key);
            if ($dir === '') { continue; }
            if (is_file($dir . '/content/index.phtml')) {
                $themes[] = ['value' => '@theme:' . $key, 'label' => $name . ' — ' . self::_t('cms.settings.theme_home')];
            }
            if ($advanced) {
                foreach (Tiger_Theme::pagesForKey($key) as $pg) {
                    if ($pg['slug'] === 'index') { continue; }   // the home is already listed
                    $themes[] = ['value' => '@theme:' . $key . ':' . $pg['slug'], 'label' => $name . ' — ' . $pg['title']];
                }
            }
        }
        if ($themes) { $groups[] = ['label' => self::_t('cms.settings.optgroup_themes'), 'options' => $themes]; }

        // Module pages — an active module's pretty public prefix (stored as a PATH).
        $modules = [];
        if (class_exists('Tiger_Routing_Overrides')) {
            foreach (Tiger_Routing_Overrides::all() as $o) {
                $prefix = trim((string) ($o['prefix'] ?? ''), '/');
                if ($prefix === '' || strpos($prefix, '.') !== false) { continue; }   // robots.txt / sitemap.xml / llms.txt
                $modules['/' . $prefix] = ['value' => '/' . $prefix, 'label' => '/' . $prefix];
            }
            ksort($modules);
        }
        if ($modules) { $groups[] = ['label' => self::_t('cms.settings.optgroup_modules'), 'options' => array_values($modules)]; }

        return $groups;
    }

    /** Translate a key via the registered translator (no `_t` helper outside a view/form). */
    protected static function _t($key)
    {
        return Zend_Registry::isRegistered('Zend_Translate')
            ? (string) Zend_Registry::get('Zend_Translate')->translate($key)
            : (string) $key;
    }
}
