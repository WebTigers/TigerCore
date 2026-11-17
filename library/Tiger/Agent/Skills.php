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
                'source'      => (string) ($meta['source'] ?? ''),
                'sourceLabel' => (string) ($meta['sourceLabel'] ?? ''),
                'repo'        => (string) ($meta['repo'] ?? ''),
                'ref'         => (string) ($meta['ref'] ?? '') ?: 'main',
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

    /**
     * The active skill keys for an org (TIGER-532): that org's own list, else the install-wide list. Skills are
     * INSTALLED install-wide (files), but each org curates which of them its agents use — an org that has never
     * chosen inherits the install's list, and an org that switched everything off (an empty row) gets none.
     *
     * @param  string|null $orgId the org (null = the caller's current org)
     * @return string[] the active keys
     */
    public static function active($orgId = null)
    {
        try {
            $org = ($orgId === null) ? Tiger_Model_Config::currentOrgId() : (string) $orgId;
            $raw = (string) Tiger_Model_Config::orgValue($org, self::ACTIVE_KEY, '');
        } catch (Throwable $e) { $raw = ''; }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Is a skill active for an org?
     *
     * @param  string      $key   the skill key
     * @param  string|null $orgId the org (null = the caller's current org)
     * @return bool
     */
    public static function isActive($key, $orgId = null) { return in_array(self::_safeKey($key), self::active($orgId), true); }

    /**
     * Turn a skill on/off for an org (idempotent). Writes that org's active list (Tiger_Model_Config::settingScope —
     * the global row only on an install with no org at all); effective next request, no schema.
     *
     * @param  string      $key   the skill key
     * @param  bool        $on    the wanted state
     * @param  string|null $orgId the org (null = the caller's current org)
     * @return bool the resulting active state
     */
    public static function setActive($key, $on, $orgId = null)
    {
        $key    = self::_safeKey($key);
        $active = self::active($orgId);
        $has    = in_array($key, $active, true);
        if ($on && !$has) { $active[] = $key; }
        if (!$on && $has) { $active = array_values(array_diff($active, [$key])); }
        [$scope, $scopeId] = Tiger_Model_Config::settingScope($orgId);
        (new Tiger_Model_Config())->set($scope, $scopeId, self::ACTIVE_KEY, implode(',', $active));
        return (bool) $on;
    }

    // ----- the loader (TIGERSKILLS.md §4 — progressive disclosure) ---------------------------------

    /** The most text load() returns for one skill (SKILL.md first, then its other files). */
    const LOAD_MAX_BYTES = 120000;

    /**
     * The skill MENU an agent sees up front: each skill that is installed AND active for the org, one line of
     * know-how each — the cheap half of progressive disclosure. The same list feeds the in-app agent's system
     * prompt and the MCP server's `initialize` instructions, so the two agents can't drift.
     *
     * @param  string|null $orgId the org (null = the caller's current org)
     * @return array<int,array{key:string,name:string,description:string}>
     */
    public static function menu($orgId = null)
    {
        $active = self::active($orgId);
        if (!$active) { return []; }
        $out = [];
        foreach (self::installed() as $s) {
            if (in_array($s['key'], $active, true)) {
                $out[] = ['key' => $s['key'], 'name' => (string) $s['name'], 'description' => (string) $s['description']];
            }
        }
        return $out;
    }

    /**
     * LOAD one skill — the on-demand half, itself two-level so a skill that ships a large dataset doesn't land
     * whole in the model's context (and the org's token bill):
     *  - with no `$file`: its SKILL.md plus a MANIFEST of the text files it ships (path + size), no contents;
     *  - with `$file`: that ONE bundled file's text. The path must be in the manifest exactly — never a path the
     *    caller builds — so it can't step outside the skill's folder.
     * Only a skill that is installed AND active for the org loads; anything else is null (so an admin's curation
     * holds for every agent). A bundled script comes back as text to read, never something that runs: nothing here
     * includes, evaluates or executes a file. Binary files and our own source.json are not listed. Bounded by
     * LOAD_MAX_BYTES.
     *
     * @param  string      $key   the skill key (as in menu())
     * @param  string|null $orgId the org (null = the caller's current org)
     * @param  string|null $file  a manifest path to read one bundled file (null = the SKILL.md + manifest)
     * @return array|null  {key,name,description,body,files:[{path,bytes}],truncated} or, for a file,
     *                     {key,name,file,content,truncated}; null when not active/installed or the file isn't listed
     */
    public static function load($key, $orgId = null, $file = null)
    {
        $key = self::_safeKey($key);
        if ($key === '' || !self::isInstalled($key) || !self::isActive($key, $orgId)) { return null; }
        $dir   = self::dir() . '/' . $key;
        $body  = self::body($key);
        $front = Tiger_Skill_Source::parseFrontmatter($body);
        $name  = (string) ($front['name'] ?? $key);
        $files = self::_textFiles($dir);

        if ($file !== null && $file !== '') {
            $sizes = array_column($files, 'bytes', 'path');
            if (!array_key_exists((string) $file, $sizes)) { return null; }   // only a listed file, exactly
            $content = (string) @file_get_contents($dir . '/' . $file, false, null, 0, self::LOAD_MAX_BYTES);
            return ['key' => $key, 'name' => $name, 'file' => (string) $file, 'content' => $content,
                    'truncated' => $sizes[$file] > self::LOAD_MAX_BYTES];
        }
        return [
            'key'         => $key,
            'name'        => $name,
            'description' => (string) ($front['description'] ?? ''),
            'body'        => substr($body, 0, self::LOAD_MAX_BYTES),
            'files'       => $files,
            'truncated'   => strlen($body) > self::LOAD_MAX_BYTES,
        ];
    }

    /**
     * The text files a skill ships (not SKILL.md, not our source.json, no symlinks, no binary), sorted by path.
     *
     * @param  string $dir the installed skill's folder
     * @return array<int,array{path:string,bytes:int}>
     */
    protected static function _textFiles($dir)
    {
        $out = [];
        $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->isLink()) { continue; }
            $rel = substr($f->getPathname(), strlen($dir) + 1);
            if ($rel === 'SKILL.md' || $rel === 'source.json') { continue; }
            $head = (string) @file_get_contents($f->getPathname(), false, null, 0, 8192);   // sniff: text only
            if ($head === '' || strpos($head, "\0") !== false || !mb_check_encoding($head, 'UTF-8')) { continue; }
            $out[] = ['path' => $rel, 'bytes' => (int) $f->getSize()];
        }
        usort($out, static function ($a, $b) { return strcmp($a['path'], $b['path']); });
        return $out;
    }

    /**
     * The menu as the text block both agents read (the in-app system prompt and the MCP `initialize`
     * instructions): a heading, then one "key — name: description" line per active skill. '' with none, so a
     * caller can skip the section entirely.
     *
     * @param  string|null $orgId the org (null = the caller's current org)
     * @return string
     */
    public static function menuText($orgId = null)
    {
        $rows = [];
        foreach (self::menu($orgId) as $s) {
            $desc = trim(preg_replace('/\s+/', ' ', $s['description']));
            if (strlen($desc) > 300) { $desc = substr($desc, 0, 300) . '…'; }
            $rows[] = '  - ' . $s['key'] . ' — ' . $s['name'] . ($desc !== '' ? ': ' . $desc : '');
        }
        if (!$rows) { return ''; }
        return "SKILLS — installed know-how for this site. Each line says when to use it; load one before following it:\n"
             . implode("\n", $rows);
    }

    /**
     * A load() result as the plain text an agent reads: the SKILL.md followed by the list of files it ships (read
     * one by loading again with its path), or — for a file load — that file under a heading. Bundled files are
     * labelled as text to read, never to run.
     *
     * @param  array $skill a load() result
     * @return string
     */
    public static function toText(array $skill)
    {
        if (array_key_exists('file', $skill)) {
            return 'SKILL "' . $skill['name'] . '" — file ' . $skill['file'] . " (text to read; Tiger never runs it):\n\n"
                 . $skill['content'] . (!empty($skill['truncated']) ? "\n\n…(truncated)" : '');
        }
        $out = 'SKILL "' . $skill['name'] . "\" (SKILL.md):\n\n" . $skill['body'] . (!empty($skill['truncated']) ? "\n\n…(truncated)" : '');
        if ($skill['files']) {
            $out .= "\n\nFILES THIS SKILL SHIPS — read one only if the SKILL.md points you to it, by loading this skill again "
                  . "with its path as \"file\" (text to read; Tiger never runs them):";
            foreach ($skill['files'] as $f) {
                $out .= "\n  - " . $f['path'] . ' (' . number_format($f['bytes']) . ' bytes)';
            }
        }
        return $out;
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

    // ----- update tracking (content-addressed — a skill has no version) --------------------------

    /**
     * A content fingerprint of an INSTALLED skill's files, for comparison against the same skill's
     * current state upstream. Each file is reduced to its **git blob object id** (`sha1("blob <len>\0
     * <bytes>")`) — the exact value GitHub's tree API reports for the same blob — so a local digest and
     * a {@see treeDigest} of the repo are directly comparable. Stateless (no stored hash), so it works
     * for skills installed before update-tracking existed. Excludes our own `source.json`. '' if absent.
     *
     * @param  string $key the installed skill key
     * @return string a sha256 over the file map, or ''
     */
    public static function localDigest($key)
    {
        $dir = self::dir() . '/' . self::_safeKey($key);
        if ($dir === '/' || !is_dir($dir)) { return ''; }
        $map  = [];
        $walk = function ($d, $rel) use (&$walk, &$map) {
            foreach (scandir($d) ?: [] as $f) {
                if ($f === '.' || $f === '..') { continue; }
                $p = $d . '/' . $f;
                $r = ($rel === '' ? '' : $rel . '/') . $f;
                if (is_dir($p) && !is_link($p)) { $walk($p, $r); continue; }
                if ($rel === '' && $f === 'source.json') { continue; }   // our metadata, not part of the skill
                $c = (string) @file_get_contents($p);
                $map[$r] = sha1('blob ' . strlen($c) . "\0" . $c);       // the git blob object id
            }
        };
        $walk($dir, '');
        return self::_digest($map);
    }

    /**
     * The skill folder's file listing from ONE git-trees call (the same call {@see install} makes), or
     * null when it can't be fetched (so a transient outage isn't cached as "no upstream").
     *
     * @param  string $org
     * @param  string $repo
     * @param  string $ref
     * @return array|null the tree nodes ([{path,type,sha,size}, …]), or null
     */
    public static function remoteTree($org, $repo, $ref)
    {
        $body = @Tiger_Module_Github::get('https://api.github.com/repos/' . $org . '/' . $repo
            . '/git/trees/' . rawurlencode($ref) . '?recursive=1');
        $tree = $body ? json_decode((string) $body, true) : null;
        return (is_array($tree) && !empty($tree['tree']) && is_array($tree['tree'])) ? $tree['tree'] : null;
    }

    /**
     * The same fingerprint as {@see localDigest}, computed from a git-trees listing — the blobs UNDER
     * `$path`, keyed by their path relative to it, valued by each blob's git sha. Bounded identically to
     * {@see install} (MAX_BYTES / MAX_FILES, same order) so it covers exactly the files that were installed.
     *
     * @param  array  $tree the tree nodes from {@see remoteTree}
     * @param  string $path the skill's path within the repo
     * @return string a sha256 over the file map, or ''
     */
    public static function treeDigest(array $tree, $path)
    {
        $prefix = trim((string) $path, '/');
        $prefix = $prefix !== '' ? $prefix . '/' : '';
        $map = [];
        foreach ($tree as $node) {
            if (($node['type'] ?? '') !== 'blob') { continue; }
            $p = (string) ($node['path'] ?? '');
            if ($prefix !== '' && strpos($p, $prefix) !== 0) { continue; }
            if (($node['size'] ?? 0) > self::MAX_BYTES) { continue; }
            $rel = $prefix !== '' ? substr($p, strlen($prefix)) : $p;
            if ($rel === '' || strpos($rel, '..') !== false) { continue; }
            $map[$rel] = (string) ($node['sha'] ?? '');
            if (count($map) >= self::MAX_FILES) { break; }
        }
        return self::_digest($map);
    }

    /** Order-independent digest over a {relpath => blob-sha} map. '' if empty. */
    protected static function _digest(array $map)
    {
        if (!$map) { return ''; }
        ksort($map);
        $parts = [];
        foreach ($map as $rel => $sha) { $parts[] = $rel . ':' . $sha; }
        return hash('sha256', implode("\n", $parts));
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
