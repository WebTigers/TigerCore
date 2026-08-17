<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Skills — the INSTALLED side of skills: pull a chosen skill's files into a local, app-owned
 * store, discover what's installed, toggle each on/off, and remove.
 *
 * Browse/search is `Tiger_Skill_Index` (+ the source adapters, TIGERSKILLS.md §2). This class is §3: once a
 * user picks one, its `SKILL.md` (+ bundled files) are fetched into `application/skills/<key>/` — app-owned
 * (survives `composer update`), files-are-source-of-truth (no DB row for a skill). "What's active" is the
 * ONLY state, held in a config value (`tiger.agent.skills.active`, the live-override tier) — install ≠
 * activate ≠ remove. The loader (§4) reads the ACTIVE skills' bodies into the agent turn.
 *
 * @api
 * @see Tiger_Skill_Index  the browse/search catalog a user installs FROM
 */
class Tiger_Agent_Skills
{
    const ACTIVE_KEY = 'tiger.agent.skills.active';   // config: comma-separated active skill keys
    const MAX_FILES  = 40;                            // a skill folder is small; bound the fetch
    const MAX_BYTES  = 262144;                        // per file (256KB)

    /** The app-owned install store, or '' pre-boot. */
    public static function dir()
    {
        return defined('APPLICATION_PATH') ? APPLICATION_PATH . '/skills' : '';
    }

    /**
     * Installed skills, each with its live active flag.
     *
     * @return array<int,array<string,mixed>> [{key,name,description,sourceLabel,repo,url,active,dir}]
     */
    public static function installed()
    {
        $dir = self::dir();
        if ($dir === '' || !is_dir($dir)) { return []; }
        $active = self::active();
        $out = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            $md = $d . '/SKILL.md';
            if (!is_file($md)) { continue; }
            $front = Tiger_Skill_Source::parseFrontmatter((string) @file_get_contents($md));
            $meta  = is_file($d . '/source.json') ? (array) json_decode((string) @file_get_contents($d . '/source.json'), true) : [];
            $key   = basename($d);
            $out[] = [
                'key'         => $key,
                'name'        => $front['name'] ?? $key,
                'description' => $front['description'] ?? '',
                'sourceLabel' => (string) ($meta['sourceLabel'] ?? ''),
                'repo'        => (string) ($meta['repo'] ?? ''),
                'path'        => (string) ($meta['path'] ?? ''),   // canonical skill location (repo + path) — for catalog dedup
                'url'         => (string) ($meta['url'] ?? ''),
                'active'      => in_array($key, $active, true),
                'dir'         => $d,
            ];
        }
        usort($out, static function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
        return $out;
    }

    /** Is a skill installed (by key)? */
    public static function isInstalled($key)
    {
        $d = self::dir();
        return $d !== '' && is_file($d . '/' . self::_safeKey($key) . '/SKILL.md');
    }

    /** The installed SKILL.md body (for the "view source" modal / the loader), or ''. */
    public static function body($key)
    {
        $md = self::dir() . '/' . self::_safeKey($key) . '/SKILL.md';
        return is_file($md) ? (string) @file_get_contents($md) : '';
    }

    // ----- active set (config tier) --------------------------------------------------------------

    /** The active skill keys. */
    public static function active()
    {
        try {
            $raw = (string) (new Tiger_Model_Config())->get('global', '', self::ACTIVE_KEY);
        } catch (Throwable $e) { $raw = ''; }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function isActive($key) { return in_array(self::_safeKey($key), self::active(), true); }

    /**
     * Turn a skill on/off (idempotent). Writes the config active-set; no schema, effective next request.
     *
     * @return bool the resulting active state
     */
    public static function setActive($key, $on)
    {
        $key    = self::_safeKey($key);
        $active = self::active();
        $has    = in_array($key, $active, true);
        if ($on && !$has) { $active[] = $key; }
        if (!$on && $has) { $active = array_values(array_diff($active, [$key])); }
        (new Tiger_Model_Config())->set('global', '', self::ACTIVE_KEY, implode(',', $active));
        return (bool) $on;
    }

    // ----- install / remove ----------------------------------------------------------------------

    /**
     * Install a skill from a normalized browse entry ({source,name,repo,ref,path,sourceLabel,url}): fetch its
     * folder's files (SKILL.md + resources) into the store + write provenance meta. Idempotent (re-fetches).
     *
     * @param  array $entry a Tiger_Skill_Source entry
     * @return string the installed key
     * @throws RuntimeException if the SKILL.md can't be fetched
     */
    public static function install(array $entry)
    {
        $repo = (string) ($entry['repo'] ?? '');
        $path = trim((string) ($entry['path'] ?? ''), '/');
        $ref  = (string) ($entry['ref'] ?? 'main');
        [$org, $rname] = array_pad(explode('/', $repo, 2), 2, '');
        if ($org === '' || $rname === '') { throw new RuntimeException('skill.install.bad_repo'); }

        $key  = self::_safeKey(($entry['source'] ?? 'src') . '__' . ($entry['name'] ?? basename($path)));
        $dest = self::dir() . '/' . $key;
        if (self::dir() === '') { throw new RuntimeException('skill.install.no_store'); }

        // List the skill folder's files via one git-trees call; fetch each blob under the path.
        $body = @Tiger_Module_Github::get('https://api.github.com/repos/' . $org . '/' . $rname . '/git/trees/' . rawurlencode($ref) . '?recursive=1');
        $tree = $body ? json_decode((string) $body, true) : null;
        $prefix = $path !== '' ? $path . '/' : '';
        $files  = [];
        foreach ((is_array($tree) && !empty($tree['tree'])) ? $tree['tree'] : [] as $node) {
            if (($node['type'] ?? '') !== 'blob') { continue; }
            $p = (string) ($node['path'] ?? '');
            if ($prefix !== '' && strpos($p, $prefix) !== 0) { continue; }
            if (($node['size'] ?? 0) > self::MAX_BYTES) { continue; }
            $files[] = $p;
            if (count($files) >= self::MAX_FILES) { break; }
        }

        @mkdir($dest, 0775, true);
        $gotSkillMd = false;
        foreach ($files as $p) {
            $raw = @Tiger_Module_Github::fetchRaw($org, $rname, $ref, $p);
            if ($raw === false || $raw === null) { continue; }
            $rel = $prefix !== '' ? substr($p, strlen($prefix)) : $p;
            if ($rel === '' || strpos($rel, '..') !== false) { continue; }
            $target = $dest . '/' . $rel;
            @mkdir(dirname($target), 0775, true);
            @file_put_contents($target, $raw);
            if (basename($rel) === 'SKILL.md') { $gotSkillMd = true; }
        }
        if (!$gotSkillMd) {
            self::_rrmdir($dest);
            throw new RuntimeException('skill.install.no_skillmd');
        }

        @file_put_contents($dest . '/source.json', json_encode([
            'source'      => (string) ($entry['source'] ?? ''),
            'sourceLabel' => (string) ($entry['sourceLabel'] ?? ''),
            'repo'        => $repo,
            'ref'         => $ref,
            'path'        => $path,
            'url'         => (string) ($entry['url'] ?? ''),
        ]));
        return $key;
    }

    /** Uninstall a skill (delete its files + drop it from the active set). */
    public static function remove($key)
    {
        $key = self::_safeKey($key);
        self::setActive($key, false);
        $d = self::dir() . '/' . $key;
        if (is_dir($d)) { self::_rrmdir($d); }
        return true;
    }

    /** Filesystem/config-safe key (source + name). */
    protected static function _safeKey($key)
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $key);
    }

    protected static function _rrmdir($dir)
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            (is_dir($p) && !is_link($p)) ? self::_rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
