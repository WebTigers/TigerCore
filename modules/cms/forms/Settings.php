<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_Settings — site/CMS settings (site name + the home page served at "/").
 *
 * Values are stored in the `config` table (scope=global) by Cms_Service_Settings —
 * NOT a settings table (see the config-discipline: config store + registry, no
 * option landfill). The home-page dropdown lists published pages; its value is a
 * `page_id` ('' = the built-in landing page).
 *
 * @api
 */
class Cms_Form_Settings extends Tiger_Form
{
    /** Sentinel option value meaning "use the typed path instead". Never stored. */
    const CUSTOM = '__custom__';

    /**
     * Public module pages, as `path => label` — the active modules' pretty public prefixes.
     *
     * Read from `Tiger_Routing_Overrides`, which is where a module declares the public alias it wants
     * (`/docs`, `/marketplace`). Non-page endpoints are skipped: an override whose prefix looks like a
     * file (`robots.txt`, `sitemap.xml`, `llms.txt`) serves plain text, and offering it as a home page
     * would only ever be a mistake. Reserved prefixes are already excluded by `all()`.
     *
     * @return array<string,string>
     */
    public static function modulePaths(): array
    {
        if (!class_exists('Tiger_Routing_Overrides')) { return []; }

        $out = [];
        foreach (Tiger_Routing_Overrides::all() as $o) {
            $prefix = trim((string) ($o['prefix'] ?? ''), '/');
            if ($prefix === '' || strpos($prefix, '.') !== false) { continue; }   // robots.txt / sitemap.xml / llms.txt
            $out['/' . $prefix] = '/' . $prefix;
        }
        ksort($out);
        return $out;
    }

    protected function elements(): array
    {
        $control = ['class' => 'form-control'];
        $select  = ['class' => 'form-select'];

        $home = ['' => $this->_t('cms.settings.opt_builtin_landing')];

        // CMS pages — stored as a page_id.
        $pages = [];
        $pm    = new Tiger_Model_Page();
        foreach ($pm->fetchAll(
            $pm->activeSelect()
               ->where('type = ?', Tiger_Model_Page::TYPE_PAGE)
               ->where('status = ?', Tiger_Model_Page::STATUS_PUBLISHED)
               ->order(['title ASC', 'locale ASC'])
        ) as $p) {
            $label = ($p->title ?: $p->slug ?: $p->page_key) . ' (' . $p->locale . ')';
            $pages[$p->page_id] = $label;
        }
        if ($pages) { $home[$this->_t('cms.settings.optgroup_pages')] = $pages; }

        // Public module pages — stored as a PATH. An active module's pretty public prefix is exactly
        // what an admin thinks of as "the marketplace page" or "the docs page".
        $modulePages = self::modulePaths();
        if ($modulePages) { $home[$this->_t('cms.settings.optgroup_modules')] = $modulePages; }

        // The escape hatch: any other route, typed in.
        $home[self::CUSTOM] = $this->_t('cms.settings.opt_custom_path');

        return [
            ['text', 'site_name', [
                'required' => true,
                'filters'  => ['StringTrim'],
                'attribs'  => array_merge($control, ['id' => 'set-site-name', 'maxlength' => 191]),
            ]],
            ['select', 'home_page', [
                'multiOptions' => $home,
                'attribs'      => array_merge($select, ['id' => 'set-home-page']),
            ]],
            // Revealed by the view when "custom path" is picked; its value replaces home_page on save.
            ['text', 'home_page_custom', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '~^/[A-Za-z0-9/_\-.]*$~']]],
                'attribs'    => array_merge($control, ['id' => 'set-home-page-custom', 'placeholder' => '/marketplace']),
            ]],
        ];
    }
}
