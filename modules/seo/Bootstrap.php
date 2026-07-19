<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_Bootstrap — TigerSEO's module bootstrap. Phase 1 registers the head plugin that contributes a CMS
 * page's SEO metadata to the head registry (see ARCHITECTURE.md / FEATURES.md in this dir for the full
 * design). It adds NO custom head registry of its own — it appends to TigerZF's headTitle/headMeta/
 * headLink, which the core layout now renders. Uninstall the module and the head still renders, with less.
 */
class Seo_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Register the head plugin — high stackIndex so it runs after core routing plugins (PageDispatch). */
    protected function _initSeoHead()
    {
        Zend_Controller_Front::getInstance()->registerPlugin(new Seo_Plugin_Head(), 90);
    }

    /** Declare the public /sitemap.xml + /robots.txt + /llms.txt routes (PHP-layer overrides, never docroot files). */
    protected function _initSeoRoutes()
    {
        Tiger_Routing_Overrides::register('seo_sitemap', ['pattern' => 'sitemap.xml', 'target' => 'seo/sitemap/xml', 'priority' => 150]);
        Tiger_Routing_Overrides::register('seo_robots',  ['pattern' => 'robots.txt',  'target' => 'seo/robots/txt',  'priority' => 150]);
        Tiger_Routing_Overrides::register('seo_llms',    ['pattern' => 'llms.txt',     'target' => 'seo/llms/txt',    'priority' => 150]);
    }

    /** Contribute the CMS pages to the sitemap. Blog registers its articles; other modules their own URLs. */
    protected function _initSeoSitemap()
    {
        Tiger_Sitemap::register('pages', function (array $ctx) {
            $model = new Tiger_Model_Page();
            $db    = $model->getAdapter();
            $sel   = $db->select()
                ->from('page', ['slug', 'title', 'meta', 'updated_at'])
                ->where('deleted = 0')
                ->where('type = ?', Tiger_Model_Page::TYPE_PAGE)
                ->where('status = ?', Tiger_Model_Page::STATUS_PUBLISHED)
                ->where('published_at IS NULL OR published_at <= NOW()')
                ->where('locale = ?', (string) ($ctx['locale'] ?? 'en'))
                ->where('org_id IN (?)', array_values(array_unique([(string) ($ctx['orgId'] ?? ''), ''])))
                ->order('updated_at DESC');
            $urls = [];
            foreach ($db->fetchAll($sel) as $r) {
                $slug = trim((string) $r['slug'], '/');
                if ($slug === '') { continue; }                 // the homepage is added by the controller
                // `title`/`desc` are optional sitemap fields that let this same provider feed /llms.txt.
                $desc = '';
                $meta = json_decode((string) $r['meta'], true);
                if (is_array($meta)) {
                    $desc = (string) ($meta['seo']['description'] ?? $meta['description'] ?? '');
                }
                $urls[] = ['loc' => '/' . $slug, 'lastmod' => $r['updated_at'], 'title' => (string) $r['title'], 'desc' => $desc];
            }
            return $urls;
        });
    }
}
