<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_Service_Pages — discovery of the PUBLIC VIEW PAGES whose social card is authorable.
 *
 * A "view page" is a shipped marketing page: a plain `.phtml` under the default namespace's
 * `index` view-script dir (`/agency`, `/vibe`, `/how-it-works`, …) served by a controller action
 * with **no CMS `page` row**, so nothing in `page.meta.seo` can describe it. `Seo_Service_Head::site()`
 * gives those pages an authorable tier keyed by `Seo_Service_Head::pageKey()` — and this class is the
 * other half: it enumerates which keys actually exist, so the admin screen can list them instead of
 * asking an operator to guess a config key.
 *
 * The filesystem IS the registry: a page's **basename is its page key** and its URL (`agency.phtml`
 * → key `agency` → `/agency`; `index.phtml` → `/`). Locale variants (`agency.es.phtml`) are the SAME
 * page in another language and share one key, so they're skipped — any basename carrying a `.` is a
 * variant, since a key is `[a-z0-9-]` by construction. Partials (`_foo.phtml`) are skipped too.
 *
 * Both the framework's dir and the app's same-named dir are scanned, app last, so an app-owned page
 * shadowing a shipped one wins — mirroring the view-script path cascade that resolves the page itself.
 *
 * Not a `/api` service (it doesn't extend `Tiger_Service_Service`, so the gateway can never dispatch
 * it); it's the plain helper `Seo_Service_Social` and the admin screen read.
 *
 * @api
 * @see Seo_Service_Head::pageKey    the runtime twin — the key a dispatched request resolves to
 * @see Seo_Service_Social           the /api service that reads + writes each page's authored values
 */
class Seo_Service_Pages
{
    /**
     * Every editable public view page, keyed listing sorted by page key.
     *
     * @param  array<string,string>|null $dirs source-label => directory to scan; null uses dirs()
     * @return array<int,array<string,string>> rows of ['key' => …, 'url' => …, 'source' => …, 'file' => …]
     */
    public static function discover(?array $dirs = null)
    {
        $found = [];
        foreach (($dirs === null ? self::dirs() : $dirs) as $source => $dir) {
            $dir = rtrim((string) $dir, '/');
            if ($dir === '' || !is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*.phtml') ?: [] as $file) {
                $name = basename($file, '.phtml');
                if ($name === '' || $name[0] === '_') {
                    continue;                       // a partial, not a page
                }
                if (strpos($name, '.') !== false) {
                    continue;                       // a locale variant (agency.es.phtml) — same page, same key
                }
                $key = self::key($name);
                if ($key === '') {
                    continue;
                }
                // Later dir wins: an app page shadowing a shipped one, like the view-path cascade.
                $found[$key] = ['key' => $key, 'url' => self::url($key), 'source' => (string) $source, 'file' => $file];
            }
        }
        ksort($found);
        return array_values($found);
    }

    /**
     * True when a key names a real, editable view page — the allow-list a write must pass, so a
     * caller can never author `tiger.seo.page.<anything>.*` config for a page that doesn't exist.
     *
     * @param  string $key the page key to check
     * @return bool        whether the key was discovered
     */
    public static function exists($key)
    {
        $key = self::key($key);
        if ($key === '') {
            return false;
        }
        foreach (self::discover() as $page) {
            if ($page['key'] === $key) { return true; }
        }
        return false;
    }

    /**
     * The view-script dirs that hold public view pages: the framework's, then the app's (app wins).
     *
     * @return array<string,string> source label => absolute directory path
     */
    public static function dirs()
    {
        $core = (defined('TIGER_CORE_PATH') ? TIGER_CORE_PATH : dirname(__DIR__, 3));
        $dirs = ['core' => $core . '/core/views/scripts/index'];
        if (defined('APPLICATION_PATH')) {
            $dirs['app'] = APPLICATION_PATH . '/views/scripts/index';
        }
        return $dirs;
    }

    /**
     * The public URL a page key is served at (`index` is the site root).
     *
     * @param  string $key the page key
     * @return string      the root-relative URL
     */
    public static function url($key)
    {
        $key = self::key($key);
        return ($key === '' || $key === 'index') ? '/' : '/' . $key;
    }

    /**
     * Normalise a basename to a config-safe page key — lowercase `[a-z0-9-]`, exactly the shape
     * `Seo_Service_Head::pageKey()` produces, so the two halves address the same config node.
     *
     * @param  string $name the raw basename (or key)
     * @return string       the sanitised key, '' when nothing survives
     */
    public static function key($name)
    {
        $name = strtolower(trim((string) $name));
        $name = preg_replace('/[^a-z0-9-]+/', '-', $name);
        return trim((string) $name, '-');
    }
}
