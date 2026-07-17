<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sitemap — the registry of a site's PUBLIC (guest-reachable) URLs.
 *
 * A module contributes the URLs it owns — CMS pages, blog articles, TigerDocs pages, a marketplace's
 * listing pages — by registering a provider from its Bootstrap; it never learns anyone else's routes.
 * TigerSEO reads the registry to build /sitemap.xml, but the registry itself is CORE (like
 * Tiger_Dashboard / Tiger_Menu) so declaring your public URLs never depends on an SEO module being
 * installed — a search index or a link checker could read it just as well.
 *
 * A provider is `fn(array $context): array`, returning a list of URL entries. $context carries `locale`
 * and `orgId` (the current site's org — see Tiger_Model_Org::siteOrgId()) so a provider scopes itself.
 * Each entry: ['loc' => '/path' (site-relative — the consumer absolutizes), 'lastmod' => datetime|null,
 * 'changefreq' => string|null, 'priority' => float|null]. A throwing provider is skipped (fail-soft).
 *
 * @api
 */
class Tiger_Sitemap
{
    /** @var array<string,callable> provider key => fn(array $context): array */
    protected static $_providers = [];

    /**
     * Register (or replace) a public-URL provider. Call from a module Bootstrap.
     *
     * @param  string   $key      a unique provider key (e.g. 'pages', 'blog', 'docs')
     * @param  callable $provider fn(array $context): array of URL entries
     * @return void
     */
    public static function register($key, callable $provider)
    {
        $key = (string) $key;
        if ($key !== '') {
            self::$_providers[$key] = $provider;
        }
    }

    /** The registered providers, keyed. @return array<string,callable> */
    public static function providers()
    {
        return self::$_providers;
    }

    /**
     * Run every provider and return a flat, de-duplicated (by `loc`) list of URL entries.
     *
     * @param  array $context passed to each provider (expects `locale`, `orgId`)
     * @return array<int,array{loc:string,lastmod:?string,changefreq:?string,priority:mixed}>
     */
    public static function collect(array $context = [])
    {
        $urls = [];
        $seen = [];
        foreach (self::$_providers as $provider) {
            try {
                $set = $provider($context);
            } catch (Throwable $e) {
                continue;   // a broken provider drops out; the sitemap still builds
            }
            if (!is_array($set)) {
                continue;
            }
            foreach ($set as $entry) {
                $loc = isset($entry['loc']) ? (string) $entry['loc'] : '';
                if ($loc === '' || isset($seen[$loc])) {
                    continue;
                }
                $seen[$loc] = true;
                $urls[] = [
                    'loc'        => $loc,
                    'lastmod'    => $entry['lastmod']    ?? null,
                    'changefreq' => $entry['changefreq'] ?? null,
                    'priority'   => $entry['priority']   ?? null,
                ];
            }
        }
        return $urls;
    }
}
