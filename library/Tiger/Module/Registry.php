<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Module_Registry — the client for the module catalog, now **multi-source**.
 *
 * The Add screen aggregates an **ordered list of sources** (`Tiger_Module_Source`), not a single
 * feed. Two are shipped by default (both removable, admin-overridable):
 *
 *  - **`webtigers` — a live-API marketplace ("marketplace #0")**, priority 0. The source of truth
 *    for the *dynamic/commercial* layer (ratings, downloads, paid catalog). Inert until its URL is
 *    configured (`tiger.modules.marketplace`) — phase 2 stands up the endpoint.
 *  - **`tiger-vendors` — the git Directory**, priority 10. A public `index.json` compiled by CI
 *    from `WebTigers/TigerVendors` — the free, reviewable community catalog and the resilient
 *    offline **fallback**. Its URL stays config-overridable (`tiger.modules.registry`).
 *
 * Each source is fetched + cached independently (GitHub is the infrastructure for a git index; no
 * server, no DB). `index()` merges them: modules are deduped by slug — the **lower-priority source
 * wins** (an enriching marketplace overlays the plain directory), a later source only *fills* fields
 * the winner is missing and *appends* new slugs; taxonomy is unioned. A down source is skipped (its
 * last-good cache is served first), so the Add screen **never hard-fails** on one source's outage —
 * if the marketplace is unreachable, the directory still yields free modules.
 *
 * An admin adds / removes / reorders / disables sources in the config tier
 * (`tiger.modules.sources.<id>.*`) — the store a future "connect a marketplace" UI writes; no table.
 * If the whole registry is unreachable, search returns empty and the admin falls back to
 * Install-from-URL.
 *
 * @api
 * @see Tiger_Module_Source
 */
class Tiger_Module_Registry
{
    const DEFAULT_INDEX     = 'https://raw.githubusercontent.com/WebTigers/TigerVendors/main/data/index.json';
    const CACHE_TTL         = 10800;   // 3h — a few refreshes a day, per the discovery model
    const CACHE_FILE        = 'registry-index.json';   // the Directory source's cache (legacy-stable name)

    /** The shipped default source ids. */
    const SOURCE_MARKETPLACE = 'webtigers';      // live-API, "marketplace #0" (dynamic/commercial)
    const SOURCE_DIRECTORY   = 'tiger-vendors';  // git index (free presence + offline fallback)

    /** Result orderings. `featured` = sponsored-first (marketplace-promoted), then neutral; the rest are neutral. */
    const SORTS = ['featured', 'title', 'latest'];

    /** @var array<string,array> module-contributed sources (id => spec), registered in-memory per request. */
    protected static $registered = [];

    /**
     * Register a catalog source from a module (call it from the module's Bootstrap `_init*`). This is the
     * one-call seam that lets any module add its own marketplace to the Add screen — no config editing, no
     * core change. It mirrors `Tiger_Audience::register()` / `Tiger_Search::register()`: in-memory and
     * re-declared each request, so the source exists exactly while the module is active and vanishes when
     * it's deactivated (nothing to clean up). An admin can still disable/reorder it — a config override
     * (`tiger.modules.sources.<id>.*`) always wins over what a module registered (see `sources()`), which
     * is how the "Connect a marketplace" screen manages module and admin sources uniformly.
     *
     * A source just has to return index-shaped JSON (`{modules, taxonomy}`) from its `url`; everything else
     * (merge, dedupe, taxonomy union, sponsored/featured, offline fallback) is handled here.
     *
     * @param  string $id       a stable id ([a-z0-9-]); the config key + cache namespace
     * @param  array  $spec     {label, kind:'git-index'|'live-api', url, priority (lower=earlier), enabled}
     * @param  string $provider the registering module's slug (shown in the manage UI), optional
     * @return void
     */
    public static function register(string $id, array $spec, string $provider = ''): void
    {
        $spec['id']       = $id;
        $spec['origin']   = 'module';
        $spec['provider'] = $provider;
        if (!isset($spec['priority'])) { $spec['priority'] = 20; }   // default: after the shipped marketplace(0)+directory(10)
        $source = new Tiger_Module_Source($spec);
        if ($source->id === '') { return; }
        self::$registered[$source->id] = $spec;
    }

    /**
     * Drop a previously registered module source (rarely needed — deactivating the module already removes
     * it, since registration is per-request). Mainly a test/So seam.
     *
     * @param  string $id the source id
     * @return void
     */
    public static function unregister(string $id): void
    {
        unset(self::$registered[$id]);
    }

    /**
     * True if the registry is reachable (at least one source resolved via fetch or fresh cache).
     *
     * @param  bool $refresh bypass the caches and re-fetch now
     * @return bool true if the merged index could be loaded
     */
    public static function available($refresh = false)
    {
        return self::index($refresh) !== null;
    }

    /**
     * Search the aggregated registry; [] when unavailable or no match. Matches name/slug/description/
     * keywords/vendor/type, then orders by $sort. **`featured` surfaces sponsored listings first** — a
     * `live-api` marketplace source (e.g. `webtigers`) may flag a listing `sponsored` (with an optional
     * `sponsored_rank`) to give a paying vendor promoted placement; every other sort (`title`, `latest`)
     * is neutral, so a buyer can always opt out of sponsorship by re-sorting. The git Directory carries
     * no `sponsored` field, so a directory-only install is unaffected (nothing to promote).
     *
     * @param  string $query   the search term ('' returns all modules)
     * @param  string $sort    'featured' (sponsored-first, then neutral), 'title', or 'latest'
     * @param  bool   $refresh bypass the caches and re-fetch the index now
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
     * Order results in place: `title` (A–Z), `latest` (newest review), or `featured` — **sponsored-first**.
     * A marketplace (`live-api`) source may flag a listing `sponsored` with an optional integer
     * `sponsored_rank` (higher = earlier; a bare `sponsored:true` ranks as 1). Featured floats those to the
     * top by rank, then leaves the index's neutral order beneath them; the sort is **stable** (PHP 8) so
     * un-sponsored listings keep their compiled order. `title`/`latest` ignore sponsorship entirely, so
     * promotion is always something the buyer can sort past. The git Directory ships no `sponsored` field,
     * so a directory-only install sees pure neutral order.
     */
    protected static function _sort(array &$out, $sort)
    {
        if ($sort === 'title') {
            usort($out, static fn($a, $b) => strcmp(self::_title($a), self::_title($b)));
        } elseif ($sort === 'latest') {
            $at = static fn($m) => (string) ($m['review']['reviewed_at'] ?? '');
            usort($out, static fn($a, $b) => strcmp($at($b), $at($a)) ?: strcmp(self::_title($a), self::_title($b)));
        } elseif ($sort === 'featured') {
            $rank = static fn($m) => !empty($m['sponsored']) ? max(1, (int) ($m['sponsored_rank'] ?? 1)) : 0;
            usort($out, static fn($a, $b) => $rank($b) <=> $rank($a));   // stable: neutral order kept within a band
        }
    }

    /** A listing's display title (the registry uses `module`; tolerate a legacy `name`). */
    protected static function _title(array $m)
    {
        return strtolower((string) ($m['module'] ?? $m['name'] ?? ''));
    }

    /**
     * The **merged** registry index across all active sources, or null if none is reachable.
     *
     * Sources are walked in priority order (ascending). Modules dedupe by slug — the first
     * (lower-priority) source to define a slug **wins**; a later source only fills fields the winner
     * lacks (enrich) and appends slugs the winner didn't have. Each module is annotated with its
     * home `source_id`. Taxonomy `types`/`categories` are unioned by id (first-seen wins).
     *
     * @param  bool $refresh bypass the caches and re-fetch every source now
     * @return array|null the merged `{modules, taxonomy}`, or null if nothing resolved
     */
    public static function index($refresh = false)
    {
        $merged  = ['modules' => [], 'taxonomy' => []];
        $bySlug  = [];   // slug => index into $merged['modules']
        $seenTax = ['types' => [], 'categories' => []];
        $any     = false;

        foreach (self::_activeSources() as $source) {
            $data = self::_fetchSource($source, $refresh);
            if ($data === null) { continue; }   // a down source is skipped, never fatal
            $any = true;

            $mods = (isset($data['modules']) && is_array($data['modules'])) ? $data['modules'] : [];
            foreach ($mods as $m) {
                if (!is_array($m)) { continue; }
                $m['source_id'] = $source->id;
                $slug = (string) ($m['slug'] ?? '');
                if ($slug === '') { $merged['modules'][] = $m; continue; }
                if (!isset($bySlug[$slug])) {
                    $bySlug[$slug] = count($merged['modules']);
                    $merged['modules'][] = $m;
                } else {
                    // The higher-precedence (earlier) source already owns this slug; a later one
                    // only ENRICHES — `+=` keeps the winner's keys, fills only what it's missing.
                    $merged['modules'][$bySlug[$slug]] += $m;
                }
            }

            $tax = (isset($data['taxonomy']) && is_array($data['taxonomy'])) ? $data['taxonomy'] : [];
            foreach (['types', 'categories'] as $axis) {
                $rows = (isset($tax[$axis]) && is_array($tax[$axis])) ? $tax[$axis] : [];
                foreach ($rows as $row) {
                    $id = (string) ($row['id'] ?? '');
                    if ($id === '' || isset($seenTax[$axis][$id])) { continue; }
                    $seenTax[$axis][$id] = true;
                    $merged['taxonomy'][$axis][] = $row;
                }
            }
        }

        return $any ? $merged : null;
    }

    /**
     * The raw aggregated listing for one slug (optionally pinned to a source) as it appears in the
     * merged index — every field the source published (including a marketplace's `readme`/media) plus
     * the annotated `source_id`. This is how a `live-api` marketplace serves the "View more" detail for
     * a module whose code repo is private (a PASS/paid module): the review copy comes from the index,
     * not a GitHub fetch.
     *
     * @param  string $slug     the module slug
     * @param  string $sourceId optional — require the listing to come from this source
     * @return array|null the listing dict, or null if no active source lists it
     */
    public static function listing($slug, $sourceId = '')
    {
        $slug = (string) $slug;
        if ($slug === '') { return null; }
        $index = self::index();
        $mods  = (is_array($index) && isset($index['modules']) && is_array($index['modules'])) ? $index['modules'] : [];
        foreach ($mods as $m) {
            if (!is_array($m) || (string) ($m['slug'] ?? '') !== $slug) { continue; }
            if ($sourceId !== '' && (string) ($m['source_id'] ?? '') !== (string) $sourceId) { continue; }
            return $m;
        }
        return null;
    }

    /**
     * The `kind` of an active source by id (`git-index` | `live-api`), or '' if unknown — so a caller
     * can decide whether a listing's detail comes from a public repo (directory) or the marketplace
     * index (a live-api source that serves its own enriched review copy).
     *
     * @param  string $sourceId the source id
     * @return string the source kind, or ''
     */
    public static function sourceKind($sourceId)
    {
        $sourceId = (string) $sourceId;
        foreach (self::sources() as $s) {
            if ((string) $s->id === $sourceId) { return (string) $s->kind; }
        }
        return '';
    }

    /**
     * The registry's filter vocabulary — the top-level `types` (filter doors) and functional
     * `categories` (each scoped to one or more types), unioned across every active source (declared
     * in each source's taxonomy.json and folded into its index by its compiler). Powers the
     * data-driven Add-screen filters so a new module type never needs a code change here. Empty
     * arrays if nothing is reachable or the indexes predate the taxonomy (the client then derives the
     * doors from the results it has).
     *
     * @param  bool $refresh bypass the caches and re-fetch now
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

    // ---- sources -----------------------------------------------------------------------------

    /**
     * The ordered list of configured sources (shipped defaults overlaid by the config tier), sorted
     * by priority ascending. Includes disabled sources (a settings UI lists them to toggle); use
     * `_activeSources()` for the fetch set.
     *
     * @return Tiger_Module_Source[]
     */
    public static function sources()
    {
        $byId = [];
        // 1) shipped defaults (marketplace #0 + the git Directory).
        foreach (self::_defaultSources() as $s) { $s->origin = 'default'; $byId[$s->id] = $s; }
        // 2) module-contributed sources (Tiger_Module_Registry::register) — fill new ids; never clobber a default.
        foreach (self::_registeredSources() as $s) {
            if (!isset($byId[$s->id])) { $byId[$s->id] = $s; }
        }
        // 3) admin config overrides win LAST — a connected marketplace, or an enable/priority tweak of any
        //    source above (config-discipline: `tiger.modules.sources.<id>.*`, never a table).
        foreach (self::_configSources() as $id => $spec) {
            if (isset($byId[$id])) { $byId[$id]->apply($spec); }   // keeps the base source's origin (default|module)
            else { $spec['id'] = $id; $spec['origin'] = 'connected'; $byId[$id] = new Tiger_Module_Source($spec); }
        }
        $all = array_values($byId);
        usort($all, static fn($a, $b) => ($a->priority <=> $b->priority) ?: strcmp($a->id, $b->id));
        return $all;
    }

    /** The module-contributed sources (from register()), as Source objects. */
    protected static function _registeredSources()
    {
        $out = [];
        foreach (self::$registered as $spec) { $out[] = new Tiger_Module_Source($spec); }
        return $out;
    }

    /**
     * The primary git Directory index URL — the configured `tiger.modules.registry`, else
     * DEFAULT_INDEX. (Kept for back-compat + the Add-screen "registry URL" hint; it feeds the
     * `tiger-vendors` source's URL.)
     *
     * @return string the index URL
     */
    public static function indexUrl()
    {
        $mod = self::_modulesConfig();
        $url = ($mod && $mod->get('registry')) ? (string) $mod->registry : '';
        return $url !== '' ? $url : self::DEFAULT_INDEX;
    }

    /**
     * The WebTigers marketplace API URL ("marketplace #0"), from `tiger.modules.marketplace`; '' =
     * inert (the live-API source stays disabled) until phase 2 configures the endpoint.
     *
     * @return string the marketplace endpoint, or '' when unset
     */
    public static function marketplaceUrl()
    {
        $mod = self::_modulesConfig();
        return ($mod && $mod->get('marketplace')) ? (string) $mod->marketplace : '';
    }

    /** The two shipped default sources (marketplace #0 first, then the git Directory). */
    protected static function _defaultSources()
    {
        $mktUrl = self::marketplaceUrl();
        return [
            new Tiger_Module_Source([
                'id'      => self::SOURCE_MARKETPLACE, 'label' => 'WebTigers Marketplace',
                'kind'    => Tiger_Module_Source::KIND_LIVE_API, 'url' => $mktUrl,
                'priority' => 0, 'enabled' => $mktUrl !== '', 'removable' => true, 'default' => true,
            ]),
            new Tiger_Module_Source([
                'id'      => self::SOURCE_DIRECTORY, 'label' => 'Tiger Directory',
                'kind'    => Tiger_Module_Source::KIND_GIT_INDEX, 'url' => self::indexUrl(),
                'priority' => 10, 'enabled' => true, 'removable' => true, 'default' => true,
                'cache'   => self::CACHE_FILE,
            ]),
        ];
    }

    /**
     * Admin-declared source overrides from the config tier (`tiger.modules.sources.<id>.*`), id => spec.
     *
     * Read LIVE from the config DB model — NOT the boot-time Zend_Config snapshot — so a source an admin
     * just connected/updated/removed is reflected in the SAME request (the config tier is eager: a
     * mid-request write wouldn't otherwise appear until the next boot, so the manage UI would look stale).
     * Falls back to the Zend_Config snapshot when the DB model isn't available (very early boot / tests).
     *
     * @return array<string,array> id => {field: value}
     */
    protected static function _configSources()
    {
        if (!class_exists('Tiger_Model_Config')) { return self::_configSourcesFromZend(); }
        try {
            $rows = (new Tiger_Model_Config())->getForScope(Tiger_Model_Config::SCOPE_GLOBAL, '');
        } catch (Throwable $e) {
            return self::_configSourcesFromZend();
        }
        $prefix = 'tiger.modules.sources.';
        $out    = [];
        foreach ($rows as $row) {
            $key = (string) $row->config_key;
            if (strpos($key, $prefix) !== 0) { continue; }
            $rest = substr($key, strlen($prefix));   // "<id>.<field>"
            $dot  = strpos($rest, '.');
            if ($dot === false) { continue; }
            $id    = substr($rest, 0, $dot);
            $field = substr($rest, $dot + 1);
            if ($id === '' || $field === '') { continue; }
            $out[$id][$field] = (string) $row->config_value;
        }
        return $out;
    }

    /** Source overrides from the boot-time Zend_Config snapshot (fallback when the DB model isn't ready). */
    protected static function _configSourcesFromZend()
    {
        $mod = self::_modulesConfig();
        $src = $mod ? $mod->get('sources') : null;
        if (!$src instanceof Zend_Config) { return []; }
        $out = [];
        foreach ($src as $id => $spec) {
            $out[(string) $id] = ($spec instanceof Zend_Config) ? $spec->toArray() : [];
        }
        return $out;
    }

    /** The fetchable subset of sources (enabled with a URL), in priority order. */
    protected static function _activeSources()
    {
        return array_values(array_filter(self::sources(), static fn($s) => $s->isFetchable()));
    }

    /** The `tiger.modules` config node, or null when config isn't registered. */
    protected static function _modulesConfig()
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $t   = $cfg ? $cfg->get('tiger') : null;
        return $t ? $t->get('modules') : null;
    }

    // ---- per-source fetch + cache ------------------------------------------------------------

    /**
     * Fetch one source's index (cached per source; stale-served on an outage), or null if
     * unreachable with no cache. Mirrors the old single-source cache dance, per source.
     *
     * @param  Tiger_Module_Source $source  the source to load
     * @param  bool                $refresh bypass this source's fresh cache and re-fetch
     * @return array|null the decoded index, or null
     */
    protected static function _fetchSource(Tiger_Module_Source $source, $refresh)
    {
        $cache = self::_cacheFile($source->cacheFile());
        if (!$refresh && $cache && is_file($cache) && (time() - filemtime($cache)) < self::CACHE_TTL) {
            $j = json_decode((string) @file_get_contents($cache), true);
            if (is_array($j)) { return $j; }
        }

        $body = self::_fetch($source);
        if ($body === null) {
            if ($cache && is_file($cache)) {   // serve a stale cache (offline resilience)
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
     * Fetch a source's raw body. Both kinds are a plain HTTP GET of an index-shaped JSON document
     * today; `kind` is the seam where a live-API source later gains authenticated / ETag-aware
     * fetching (phase 2). Returns the body string, or null on failure.
     */
    protected static function _fetch(Tiger_Module_Source $source)
    {
        return Tiger_Module_Github::get($source->url);
    }

    protected static function _cacheFile($name = self::CACHE_FILE)
    {
        if ((string) $name === '') { return null; }
        $base = defined('APPLICATION_ROOT') ? rtrim(APPLICATION_ROOT, '/') : rtrim(getcwd(), '/');
        $dir  = $base . '/storage/cache';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        return $dir . '/' . $name;
    }
}
