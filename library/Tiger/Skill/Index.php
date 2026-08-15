<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Skill_Index — the internal, searchable skill catalog built by running the source adapters.
 *
 * Tiger is **not a trust authority**: this aggregates what the *supported* repos advertise (each via a
 * `Tiger_Skill_Source` adapter) into one normalized, searchable list for the user to review + install.
 * Each source is scanned + **cached independently** (a down/rate-limited source serves its last-good cache,
 * so the browse screen never hard-fails on one repo's outage — the `Tiger_Module_Registry` pattern).
 *
 * Sources = the built-in supported adapters + any the user added (config tier, `tiger.agent.skills.sources`,
 * per config-discipline — a live-override key, no schema). Enabling/adding/removing a source is a config
 * write; the browse/search here is read-only.
 *
 * @api
 * @see Tiger_Skill_Source  the per-repo scan/normalize adapter
 */
class Tiger_Skill_Index
{
    /** Cache TTL for a source's scan (seconds). Scans hit the GitHub API, so don't re-scan per page load. */
    const TTL = 21600;   // 6h

    /**
     * The built-in supported sources. Tiger SUPPORTS (can read) these layouts; it does NOT endorse the
     * skills in them. The user reviews each SKILL.md before installing.
     */
    protected static $extra = [];   // programmatically-registered sources (tests / runtime adds)

    /** Register an extra source at runtime (used by the admin's "add source" + by tests). */
    public static function registerSource(Tiger_Skill_Source $source)
    {
        self::$extra[$source->id()] = $source;
    }

    /** Drop all runtime-registered sources (leaves built-in + config sources). */
    public static function clearSources()
    {
        self::$extra = [];
    }

    /** All active `Tiger_Skill_Source` adapters (built-in + config-declared + runtime), minus disabled. */
    public static function sources()
    {
        $out = [];
        // Built-in: the official Anthropic Agent Skills collection (skills/*/SKILL.md).
        $out['anthropic-skills'] = new Tiger_Skill_Source_SkillsDir('anthropic-skills', 'Anthropic Skills', 'anthropics/skills', 'main', 'skills');
        // Built-in: the Composio community collection — 100+ skills via its .claude-plugin/marketplace.json
        // (ONE fetch, no per-SKILL.md scrape). Community-curated, NOT a Tiger endorsement — review before install.
        $out['composio-skills'] = new Tiger_Skill_Source_Marketplace('composio-skills', 'Composio Skills (community)', 'ComposioHQ/awesome-claude-skills', 'master', 'composio-skills/.claude-plugin/marketplace.json', '');

        // Config-declared sources: tiger.agent.skills.sources.<id> = { label, repo|url, ref?, base?, enabled? }.
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $node = ($cfg && $cfg->get('tiger') && $cfg->tiger->get('agent') && $cfg->tiger->agent->get('skills'))
            ? $cfg->tiger->agent->skills->get('sources') : null;
        if ($node) {
            foreach ($node->toArray() as $id => $s) {
                if (isset($s['enabled']) && !$s['enabled']) { unset($out[$id]); continue; }
                try {
                    $out[(string) $id] = !empty($s['url'])
                        ? new Tiger_Skill_Source_Url((string) $s['url'])
                        : new Tiger_Skill_Source_SkillsDir((string) $id, (string) ($s['label'] ?? $id),
                            (string) ($s['repo'] ?? ''), (string) ($s['ref'] ?? 'main'), (string) ($s['base'] ?? 'skills'));
                } catch (Throwable $e) { /* skip a malformed source, don't break the screen */ }
            }
        }
        foreach (self::$extra as $id => $src) { $out[$id] = $src; }
        return $out;
    }

    /**
     * The full normalized catalog — every enabled source, each cached independently, merged + de-duped.
     *
     * @param  bool $refresh bypass the caches and re-scan now
     * @return array<int,array<string,string>> normalized entries (see Tiger_Skill_Source)
     */
    public static function all($refresh = false)
    {
        $seen = [];
        $out  = [];
        foreach (self::sources() as $source) {
            foreach (self::_cachedScan($source, $refresh) as $e) {
                $key = $e['key'] ?? ($e['source'] . ':' . ($e['name'] ?? ''));
                if (isset($seen[$key])) { continue; }   // first source wins a collision
                $seen[$key] = 1;
                $out[] = $e;
            }
        }
        usort($out, static function ($a, $b) {
            return strcasecmp($a['name'] . $a['sourceLabel'], $b['name'] . $b['sourceLabel']);
        });
        return $out;
    }

    /**
     * Search the catalog (name / description / source), case-insensitive substring.
     *
     * @param  string $query the search text ('' = everything)
     * @param  bool   $refresh re-scan sources first
     * @return array<int,array<string,string>>
     */
    public static function search($query, $refresh = false)
    {
        $q = strtolower(trim((string) $query));
        $all = self::all($refresh);
        if ($q === '') { return $all; }
        return array_values(array_filter($all, static function ($e) use ($q) {
            return strpos(strtolower($e['name'] . ' ' . $e['description'] . ' ' . $e['sourceLabel']), $q) !== false;
        }));
    }

    /** A source's entries via cache: fresh cache → scan → last-good stale cache on failure (never hard-fail). */
    protected static function _cachedScan(Tiger_Skill_Source $source, $refresh)
    {
        $file = self::_cacheFile($source->id());
        $cached = ($file !== '' && is_file($file)) ? json_decode((string) @file_get_contents($file), true) : null;
        $fresh  = is_array($cached) && isset($cached['at']) && (self::_now() - (int) $cached['at']) < self::TTL;

        if (!$refresh && $fresh && isset($cached['entries'])) { return (array) $cached['entries']; }

        $entries = [];
        try { $entries = $source->scan(); } catch (Throwable $e) { $entries = []; }

        if ($entries) {
            self::_cacheWrite($file, $entries);
            return $entries;
        }
        // Scan failed/empty → serve last-good cache even if stale (outage resilience).
        return (is_array($cached) && isset($cached['entries'])) ? (array) $cached['entries'] : [];
    }

    protected static function _cacheFile($id)
    {
        if (!defined('APPLICATION_ROOT')) { return ''; }
        $id = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $id));
        return APPLICATION_ROOT . '/var/cache/skills/' . $id . '.json';
    }

    protected static function _cacheWrite($file, array $entries)
    {
        if ($file === '') { return; }
        if (!is_dir(dirname($file))) { @mkdir(dirname($file), 0775, true); }
        @file_put_contents($file, json_encode(['at' => self::_now(), 'entries' => $entries]), LOCK_EX);
    }

    /** Seam for tests (time is otherwise fine to read directly). */
    protected static function _now() { return time(); }
}
