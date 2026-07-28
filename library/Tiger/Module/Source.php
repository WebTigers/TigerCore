<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Module_Source — one catalog feed the Module Manager reads.
 *
 * The registry is **multi-source**: the Add screen aggregates an ordered list of sources, each a
 * value object described here. A source is either a serverless **git index** (a public
 * `index.json` — the free directory: reviewable, community-curated, effectively always up) or a
 * **live API** (a marketplace endpoint that serves the same index-shaped payload, *enriched* —
 * ratings, download counts, a paid catalog). Both fetch a URL and yield the same
 * `{modules, taxonomy}` shape; the `kind` records **provenance and trust** (a git index is
 * public/reviewable; a live API is operator-run) and is the seam where a live-API fetch later
 * gains authenticated / ETag-aware behavior.
 *
 * Sources are ordered by `priority` **ascending** — lower is earlier and *wins a slug collision*,
 * so an enriching marketplace (priority 0) overlays the plain directory (priority 10). Shipped
 * defaults carry `default = true`; an admin adds / removes / reorders / disables sources in the
 * **config tier** (`tiger.modules.sources.<id>.*`), never a table (config-discipline). A
 * non-`removable` default can be disabled but not deleted.
 *
 * @api
 * @see Tiger_Module_Registry
 */
class Tiger_Module_Source
{
    /** A serverless public `index.json` (the free, reviewable directory). */
    const KIND_GIT_INDEX = 'git-index';
    /** An operator-run endpoint serving an index-shaped, enriched payload (a marketplace). */
    const KIND_LIVE_API  = 'live-api';
    /** The recognized kinds; an unknown kind coerces to a git index (the safe, public default). */
    const KINDS = [self::KIND_GIT_INDEX, self::KIND_LIVE_API];

    /** @var string stable id (`[a-z0-9-]`) — the config key and cache namespace */
    public $id;
    /** @var string human label for a settings UI */
    public $label;
    /** @var string one of self::KINDS */
    public $kind;
    /** @var string the index.json URL (git) or API endpoint (live); '' = inert */
    public $url;
    /** @var int order weight; lower = earlier = wins a slug collision */
    public $priority;
    /** @var bool fetched only when true */
    public $enabled;
    /** @var bool a shipped default that may be disabled but not deleted when false */
    public $removable;
    /** @var bool true for a shipped default source */
    public $default;
    /** @var string the per-source cache file basename */
    public $cache;

    /**
     * Build a source from a spec array (missing keys take sane defaults).
     *
     * @param array $spec id, label, kind, url, priority, enabled, removable, default, cache
     */
    public function __construct(array $spec)
    {
        $this->id        = self::_slug((string) ($spec['id'] ?? ''));
        $this->label     = (string) ($spec['label'] ?? ($this->id !== '' ? $this->id : ''));
        $this->kind      = in_array($spec['kind'] ?? '', self::KINDS, true) ? (string) $spec['kind'] : self::KIND_GIT_INDEX;
        $this->url       = (string) ($spec['url'] ?? '');
        $this->priority  = (int) ($spec['priority'] ?? 100);
        $this->enabled   = self::_bool($spec['enabled'] ?? true);
        $this->removable = self::_bool($spec['removable'] ?? true);
        $this->default   = self::_bool($spec['default'] ?? false);
        $this->cache     = (string) ($spec['cache'] ?? ($this->id !== '' ? 'registry-' . $this->id . '.json' : ''));
    }

    /**
     * Overlay a partial spec (config override) — only the keys present are changed, so an admin
     * can flip one field (e.g. `enabled`) without re-declaring the whole source.
     *
     * @param  array $spec the subset of fields to override
     * @return void
     */
    public function apply(array $spec): void
    {
        if (array_key_exists('label', $spec))     { $this->label = (string) $spec['label']; }
        if (array_key_exists('kind', $spec) && in_array($spec['kind'], self::KINDS, true)) { $this->kind = (string) $spec['kind']; }
        if (array_key_exists('url', $spec))       { $this->url = (string) $spec['url']; }
        if (array_key_exists('priority', $spec))  { $this->priority = (int) $spec['priority']; }
        if (array_key_exists('enabled', $spec))   { $this->enabled = self::_bool($spec['enabled']); }
        if (array_key_exists('removable', $spec)) { $this->removable = self::_bool($spec['removable']); }
        if (array_key_exists('cache', $spec))     { $this->cache = (string) $spec['cache']; }
    }

    /**
     * True when this source should actually be fetched (enabled with a real URL).
     *
     * @return bool
     */
    public function isFetchable(): bool
    {
        return $this->enabled && $this->url !== '';
    }

    /**
     * The per-source cache file basename (e.g. `registry-webtigers.json`).
     *
     * @return string
     */
    public function cacheFile(): string
    {
        return $this->cache;
    }

    /**
     * The source as a plain array (for a settings UI / diagnostics).
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'label' => $this->label, 'kind' => $this->kind, 'url' => $this->url,
            'priority' => $this->priority, 'enabled' => $this->enabled, 'removable' => $this->removable,
            'default' => $this->default, 'cache' => $this->cache,
        ];
    }

    /** Reduce an id to `[a-z0-9-]` so it's safe as a config key and a cache filename. */
    protected static function _slug($id): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', (string) $id)), '-');
    }

    /** Coerce a config-ish value ("0"/"1"/"true"/bool/int) to bool. */
    protected static function _bool($v): bool
    {
        return is_bool($v) ? $v : (bool) filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}
