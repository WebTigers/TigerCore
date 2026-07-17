<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_SitemapController — serves /sitemap.xml (a route, never a file — a physical sitemap.xml in the
 * docroot would silently shadow it under cPanel's real-file-first .htaccess). Enumerates every public
 * URL contributed to Tiger_Sitemap (CMS pages, blog articles, docs, …), scoped to the current site org,
 * and emits sitemaps.org XML. Public (guest ACL). Per-request build for v1 — a fingerprint cache is the
 * scale follow-up. The route is declared in Seo_Bootstrap via Tiger_Routing_Overrides.
 */
class Seo_SitemapController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setHeader('Content-Type', 'application/xml; charset=UTF-8', true);
        @ini_set('display_errors', '0');   // the body must be pure XML; notices go to the log, not here
    }

    public function xmlAction()
    {
        $request = $this->getRequest();
        $base    = $request->getScheme() . '://' . $request->getHttpHost();
        $context = [
            'locale' => defined('LANG') ? LANG : 'en',
            'orgId'  => method_exists('Tiger_Model_Org', 'siteOrgId') ? Tiger_Model_Org::siteOrgId() : '',
        ];

        // The homepage always belongs in the sitemap; providers supply the rest.
        $urls = array_merge([['loc' => '/', 'lastmod' => null]], Tiger_Sitemap::collect($context));

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $loc = (string) $u['loc'];
            $abs = preg_match('#^https?://#i', $loc) ? $loc : $base . '/' . ltrim($loc, '/');
            $out .= '  <url>' . "\n";
            $out .= '    <loc>' . htmlspecialchars($abs, ENT_QUOTES | ENT_XML1) . '</loc>' . "\n";
            $lastmod = self::_w3c($u['lastmod'] ?? null);
            if ($lastmod !== '') {
                $out .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            }
            if (!empty($u['changefreq'])) {
                $out .= '    <changefreq>' . htmlspecialchars((string) $u['changefreq'], ENT_QUOTES | ENT_XML1) . '</changefreq>' . "\n";
            }
            if (isset($u['priority']) && $u['priority'] !== null && $u['priority'] !== '') {
                $out .= '    <priority>' . number_format((float) $u['priority'], 1) . '</priority>' . "\n";
            }
            $out .= '  </url>' . "\n";
        }
        $out .= '</urlset>' . "\n";

        $this->getResponse()->setBody($out);
    }

    /** A DB DATETIME as a W3C/ISO-8601 <lastmod>; '' if blank/unparseable. */
    private static function _w3c($datetime)
    {
        $datetime = trim((string) $datetime);
        if ($datetime === '') {
            return '';
        }
        $ts = strtotime($datetime);
        return $ts !== false ? date('c', $ts) : '';
    }
}
