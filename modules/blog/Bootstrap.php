<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog module bootstrap.
 *
 * First-party blog/articles feature. An article is a `page` row (type='article') whose
 * scalar metadata rides in page.meta; this module is the authoring surface + the two
 * relational tables it owns (taxonomy, page_taxonomy). The content engine itself stays
 * in the platform layer (Tiger_Model_Page, the renderer, the page dispatcher).
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader,
 * so Blog_Model_* (models/), Blog_Service_* (services/) and Blog_Form_* (forms/) load by
 * convention; controllers load via the registered module dir; configs/acl.ini, configs/routes.ini
 * (the /blog/* pretty URLs), and languages/ are picked up by the core globs.
 */
class Blog_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Register the blog's "articles" search provider — the tap-in demo for Tiger_Search. */
    protected function _initSearchProvider()
    {
        if (!class_exists('Tiger_Search')) {
            return;
        }
        Tiger_Search::register([
            'key'    => 'articles',
            'label'  => 'blog.nav.label',
            'icon'   => 'fa-newspaper',
            'weight' => 20,
            'search' => function ($term, $ctx) {
                $out = [];
                $rows = (new Tiger_Model_Page())->search($term, $ctx['locale'], $ctx['orgId'], $ctx['limit'], 'article');
                foreach ($rows as $r) {
                    $out[] = [
                        'title'   => (string) $r['title'],
                        'url'     => '/blog/' . ltrim((string) $r['slug'], '/'),
                        'snippet' => Tiger_Search::snippet($r['body'], $term),
                        'score'   => (float) $r['score'],
                    ];
                }
                return $out;
            },
        ]);
    }

    /** Contribute published articles to the sitemap — the blog owns the /blog/<slug> URL form. */
    protected function _initBlogSitemap()
    {
        if (!class_exists('Tiger_Sitemap')) {
            return;   // a core too old for the sitemap registry — nothing to contribute to
        }
        Tiger_Sitemap::register('blog', function (array $ctx) {
            $posts = (new Blog_Model_Post())->published([
                'locale' => (string) ($ctx['locale'] ?? 'en'),
                'orgId'  => (string) ($ctx['orgId'] ?? ''),
                'limit'  => 10000,
            ]);
            $urls = [];
            foreach ($posts as $p) {
                // `title`/`desc` are optional sitemap fields that let this same provider feed /llms.txt.
                $desc = '';
                $meta = json_decode((string) $p->meta, true);
                if (is_array($meta)) {
                    $desc = (string) ($meta['excerpt'] ?? $meta['subtitle'] ?? ($meta['seo']['description'] ?? ''));
                }
                $urls[] = [
                    'loc'     => '/blog/' . $p->slug,
                    'lastmod' => $p->updated_at ?: $p->published_at,
                    'title'   => (string) $p->title,
                    'desc'    => $desc,
                ];
            }
            return $urls;
        });
    }
}
