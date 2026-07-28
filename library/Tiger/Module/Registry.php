<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Module_Registry — the client for the open Vendor Registry (WebTigers/TigerVendors).
 *
 * The registry is just a public git repo: one JSON file per module under /data, compiled by
 * CI into a single `index.json` search index Tiger fetches + caches (a few times/day). No
 * server, no DB — GitHub is the infrastructure. If the registry isn't reachable yet (the repo
 * doesn't exist / offline), search returns empty and the admin falls back to Install-from-URL.
 *
 * The index URL is config-overridable (`tiger.modules.registry`) so a fork can point Tiger at
 * a different catalog — the whole thing is decentralized by design.
 *
 * @api
 */
class Tiger_Module_Registry
{
    const DEFAULT_INDEX     = 'https://raw.githubusercontent.com/WebTigers/TigerVendors/main/data/index.json';
    const CACHE_TTL         = 10800;   // 3h — a few refreshes a day, per the discovery model
    const CACHE_FILE        = 'registry-index.json';

    /** Result orderings for the directory. `featured` = the index's own neutral order (no paid placement). */
    const SORTS = ['featured', 'title', 'latest'];

    /**
     * True if the registry index is reachable (fetch or fresh cache).
     *
     * @param  bool $refresh bypass the cache and re-fetch now
     * @return bool true if the index could be loaded
     */
    public static function available($refresh = false)
    {
        return self::index($refresh) !== null;
    }

    /**
     * Search the registry; [] when unavailable or no match. Matches name/slug/description/
     * keywords/vendor/type, then orders by $sort. The directory is **neutral** — there is no paid
     * placement here; sponsorship/promotion is an on-platform concern (a marketplace's points system),
     * not a boost baked into this distributed catalog.
     *
     * @param  string $query   the search term ('' returns all modules)
     * @param  string $sort    'featured' (the index's neutral order), 'title', or 'latest'
     * @param  bool   $refresh bypass the cache and re-fetch the index now
     * @return array the matching module entries
     */
    public static function search($query, $sort = 'featured', $refresh = false)
    {
        $index = self::index($refresh);
        if (!$index) {
            return [];
        }
        $modules = isset($index['modules']) && is_array($index['modules']) ? $index['modules'] : (array) $index;
        $q = strtolower(trim((string) $query));

        $out = [];
        foreach ($modules as $m) {
            if (!is_array($m)) { continue; }
            if ($q !== '') {
                $hay = strtolower(self::_title($m) . ' ' . ($m['slug'] ?? '') . ' ' . ($m['description'] ?? '')
                    . ' ' . implode(' ', (array) ($m['keywords'] ?? [])) . ' ' . ($m['vendor'] ?? $m['author'] ?? '')
                    . ' ' . ($m['type'] ?? ''));
                if (strpos($hay, $q) === false) { continue; }
            }
            $out[] = self::_resolveImages($m);
        }

        self::_sort($out, in_array($sort, self::SORTS, true) ? $sort : 'featured');
        return $out;
    }

    /**
     * Resolve a listing's image paths to absolute raw URLs. Images live in the module's OWN repo
     * (the registry only points at them); a listing may store repo-relative paths (e.g.
     * "assets/screenshots/01.png") which resolve against the pinned ref
     * (raw.githubusercontent.com/<org>/<repo>/<ref>/…) so the SAME paths are reusable in the repo's
     * README.md. A value already starting with http(s) is passed through unchanged (back-compat with
     * full-URL logo/hero). Covers logo, hero, the screenshots[] gallery, and the video (self-hosted
     * mp4 → raw, YouTube/Vimeo → a click-only nocookie embed).
     *
     * @param  array $m the listing
     * @return array the listing with absolute media URLs
     */
    protected static function _resolveImages(array $m)
    {
        if (!preg_match('#github\.com/([^/]+)/([^/]+?)/?$#i', (string) ($m['repository'] ?? ''), $r)) {
            return $m;
        }
        $ref  = (string) ($m['ref'] ?? $m['version'] ?? 'main');
        $base = "https://raw.githubusercontent.com/{$r[1]}/{$r[2]}/{$ref}/";
        $abs  = static function ($p) use ($base) {
            $p = (string) $p;
            return ($p === '' || preg_match('#^https?://#i', $p)) ? $p : $base . ltrim($p, '/');
        };
        foreach (['logo', 'hero'] as $k) {
            if (!empty($m[$k])) { $m[$k] = $abs($m[$k]); }
        }
        if (!empty($m['screenshots']) && is_array($m['screenshots'])) {
            $m['screenshots'] = array_values(array_filter(array_map($abs, $m['screenshots'])));
        }

        // video: a self-hosted .mp4/.webm (repo-relative → raw, or a full CDN URL) plays inline;
        // a YouTube/Vimeo link becomes a privacy-enhanced embed that only loads on click (the
        // lightbox builds the iframe on open, so nothing phones home until the admin plays it).
        // An optional repo-hosted poster avoids a third-party thumbnail.
        if (!empty($m['video'])) {
            $v   = is_array($m['video']) ? $m['video'] : ['src' => (string) $m['video']];
            $src = (string) ($v['src'] ?? '');
            if ($src === '') {
                unset($m['video']);
            } else {
                if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([\w-]+)#i', $src, $y)) {
                    $v['src']  = 'https://www.youtube-nocookie.com/embed/' . $y[1];
                    $v['type'] = 'iframe';
                } elseif (preg_match('#vimeo\.com/(?:video/)?(\d+)#i', $src, $vm)) {
                    $v['src']  = 'https://player.vimeo.com/video/' . $vm[1];
                    $v['type'] = 'iframe';
                } else {
                    $v['src']  = $abs($src);
                    $v['type'] = 'video';
                }
                if (!empty($v['poster'])) { $v['poster'] = $abs($v['poster']); }
                $m['video'] = $v;
            }
        }
        return $m;
    }

    /**
     * Order results in place: `title` (A–Z), `latest` (newest review), or `featured` — which is now the
     * index's own neutral order (the compiler sorts it alphabetically). There is no paid placement in the
     * directory: **sponsorship is on-platform** (a marketplace's points system), never a boost baked into
     * this distributed Add-screen catalog.
     */
    protected static function _sort(array &$out, $sort)
    {
        if ($sort === 'title') {
            usort($out, static fn($a, $b) => strcmp(self::_title($a), self::_title($b)));
        } elseif ($sort === 'latest') {
            $at = static fn($m) => (string) ($m['review']['reviewed_at'] ?? '');
            usort($out, static fn($a, $b) => strcmp($at($b), $at($a)) ?: strcmp(self::_title($a), self::_title($b)));
        }
        // 'featured' → leave the index's neutral order untouched.
    }

    /** A listing's display title (the registry uses `module`; tolerate a legacy `name`). */
    protected static function _title(array $m)
    {
        return strtolower((string) ($m['module'] ?? $m['name'] ?? ''));
    }

    /**
     * The (cached) registry index array, or null if unreachable.
     *
     * @param  bool $refresh bypass the cache and re-fetch now (the fresh copy is written back)
     * @return array|null the decoded index, or null if unreachable
     */
    public static function index($refresh = false)
    {
        $cache = self::_cacheFile();
        if (!$refresh && $cache && is_file($cache) && (time() - filemtime($cache)) < self::CACHE_TTL) {
            $j = json_decode((string) @file_get_contents($cache), true);
            if (is_array($j)) { return $j; }
        }

        $body = Tiger_Module_Github::get(self::indexUrl());
        if ($body === null) {
            // serve a stale cache if we have one (offline resilience), else null
            if ($cache && is_file($cache)) {
                $j = json_decode((string) @file_get_contents($cache), true);
                return is_array($j) ? $j : null;
            }
            return null;
        }
        $j = json_decode($body, true);
        if (!is_array($j)) { return null; }
        if ($cache) { @file_put_contents($cache, $body); }
        return $j;
    }

    /**
     * The registry's filter vocabulary — the top-level `types` (filter doors) and functional
     * `categories` (each scoped to one or more types), as declared in the registry's taxonomy.json
     * and folded into the index by the registry compiler. Powers the data-driven Add-screen filters
     * so a new module type never needs a code change here. Empty arrays if the index is unreachable
     * or predates the taxonomy (the client then derives the doors from the results it has).
     *
     * @param  bool $refresh bypass the cache and re-fetch the index now
     * @return array{types:array,categories:array}
     */
    public static function taxonomy($refresh = false)
    {
        $index = self::index($refresh);
        $tax   = (is_array($index) && isset($index['taxonomy']) && is_array($index['taxonomy'])) ? $index['taxonomy'] : [];
        return [
            'types'      => isset($tax['types']) && is_array($tax['types']) ? array_values($tax['types']) : [],
            'categories' => isset($tax['categories']) && is_array($tax['categories']) ? array_values($tax['categories']) : [],
        ];
    }

    /**
     * The registry index URL — the configured `tiger.modules.registry`, else DEFAULT_INDEX.
     *
     * @return string the index URL
     */
    public static function indexUrl()
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $t   = $cfg ? $cfg->get('tiger') : null;
        $mod = $t ? $t->get('modules') : null;
        $url = ($mod && $mod->get('registry')) ? (string) $mod->registry : '';
        return $url !== '' ? $url : self::DEFAULT_INDEX;
    }

    protected static function _cacheFile($name = self::CACHE_FILE)
    {
        $base = defined('APPLICATION_ROOT') ? rtrim(APPLICATION_ROOT, '/') : rtrim(getcwd(), '/');
        $dir  = $base . '/storage/cache';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        return $dir . '/' . $name;
    }
}
