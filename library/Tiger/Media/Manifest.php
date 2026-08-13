<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Media_Manifest — discover the STATIC media that ACTIVE modules ship.
 *
 * A module of ANY type (theme, plugin, app, code) may ship a `media.json` at its root declaring image
 * assets it wants surfaced in the Media Library. These are read-only *files* the module owns — NOT rows
 * in the `media` table. The Media service merges them into the Library grid + picker as "static" entries;
 * an admin can Copy one into managed media (an ordinary `media` row) to use it durably.
 *
 * Because the entries are DISCOVERED (never inserted), the lifecycle is automatic and lossless:
 *   - a module ACTIVATES  → it's no longer in `inactiveSlugs()` → its media appears (no write);
 *   - a module DEACTIVATES → skipped here → its static entries vanish (no delete);
 *   - anything an admin COPIED is a real `media` row this class never touches, so copies persist.
 * That's why the feature needs no schema change and no activation hook — discovery IS the lifecycle.
 *
 * The manifest (`media.json`):
 *   { "imageDir": "images",                       // expose EVERY image under assets/<imageDir> (recursive)
 *     "match": ["demo-*"],                         // optional glob(s) on the filename to curate imageDir
 *     "images": [                                  // and/or an explicit curated list (relative to assets/)
 *       "images/hero.webp",
 *       { "file": "images/team.webp", "title": "Our team", "alt": "The team" }
 *     ] }
 * `match` filters ONLY the `imageDir` sweep (keeping chrome — favicons, avatars, sprites — out); an
 * explicit `images` entry is always included (it's already curated).
 * Paths are relative to the module's `assets/` dir — the same dir the public symlink points at, so a file
 * `assets/images/hero.webp` served by a theme with assetBase `/_crafto` resolves to `/_crafto/images/hero.webp`.
 *
 * @api
 * @see Tiger_Module_Discovery  the installed-module scan this builds on
 */
class Tiger_Media_Manifest
{
    /** Image extensions a media manifest may surface. */
    const IMG_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

    /**
     * Every static media entry from every ACTIVE module that ships a `media.json`.
     *
     * @return array<int,array<string,string>> [{id,module,moduleName,filename,relpath,title,alt,ext,url}]
     *         sorted by module name then path; `id` is `static:<slug>:<relpath>`.
     */
    public static function entries()
    {
        $inactive = [];
        try { $inactive = array_flip((new Tiger_Model_Module())->inactiveSlugs()); } catch (Throwable $e) {}

        $out = [];
        foreach (self::_modules() as $slug => $info) {
            if (isset($inactive[$slug])) { continue; }                 // only ACTIVE modules contribute
            $manifest = self::_readManifest($info['dir']);
            if (!$manifest) { continue; }
            $base = self::_publicBase($slug, $info);
            if ($base === '') { continue; }                            // no live public symlink -> not reachable
            foreach (self::_resolveImages($info['dir'], $manifest) as $img) {
                $out[] = [
                    'id'         => 'static:' . $slug . ':' . $img['relpath'],
                    'module'     => $slug,
                    'moduleName' => $info['name'],
                    'filename'   => basename($img['relpath']),
                    'relpath'    => $img['relpath'],
                    'title'      => $img['title'],
                    'alt'        => $img['alt'],
                    'ext'        => strtolower((string) pathinfo($img['relpath'], PATHINFO_EXTENSION)),
                    'url'        => rtrim($base, '/') . '/' . $img['relpath'],
                    'w'          => (int) $img['w'],
                    'h'          => (int) $img['h'],
                    'size'       => (int) $img['size'],
                ];
            }
        }
        usort($out, static function ($a, $b) {
            return strcasecmp($a['moduleName'] . '/' . $a['relpath'], $b['moduleName'] . '/' . $b['relpath']);
        });
        return $out;
    }

    /**
     * Resolve a static entry id (`static:<slug>:<relpath>`) back to its absolute file path — active-only,
     * traversal-guarded, and it must be DECLARED in the module's manifest (so a copy can't reach an
     * arbitrary asset file). '' if the module is inactive/unknown, the path is unsafe, or the file is absent.
     *
     * @param  string $id the static entry id
     * @return string the absolute file path, or ''
     */
    public static function file($id)
    {
        $id = (string) $id;
        if (strpos($id, 'static:') !== 0) { return ''; }
        $rest = substr($id, strlen('static:'));
        $sep  = strpos($rest, ':');
        if ($sep === false) { return ''; }
        $slug = substr($rest, 0, $sep);
        $rel  = trim(str_replace('\\', '/', substr($rest, $sep + 1)), '/');

        if ($rel === '' || strpos($rel, '..') !== false || !preg_match('#^[A-Za-z0-9][A-Za-z0-9/._-]*$#', $rel)) {
            return '';
        }
        try { if (in_array($slug, (new Tiger_Model_Module())->inactiveSlugs(), true)) { return ''; } } catch (Throwable $e) {}

        $mods = self::_modules();
        if (!isset($mods[$slug])) { return ''; }
        $manifest = self::_readManifest($mods[$slug]['dir']);
        $declared = false;
        foreach (self::_resolveImages($mods[$slug]['dir'], $manifest) as $img) {
            if ($img['relpath'] === $rel) { $declared = true; break; }
        }
        if (!$declared) { return ''; }

        $file = $mods[$slug]['dir'] . '/assets/' . $rel;
        return is_file($file) ? $file : '';
    }

    /**
     * The distinct ACTIVE modules that contribute static media — for a source filter ("Uploads" + these).
     *
     * @return array<int,array{slug:string,name:string}> sorted by display name
     */
    public static function sources()
    {
        $seen = [];
        foreach (self::entries() as $e) { $seen[$e['module']] = $e['moduleName']; }
        asort($seen, SORT_NATURAL | SORT_FLAG_CASE);
        $out = [];
        foreach ($seen as $slug => $name) { $out[] = ['slug' => $slug, 'name' => $name]; }
        return $out;
    }

    /** Installed modules keyed by slug: {dir,name,type,asset_base,key}. */
    protected static function _modules()
    {
        $out = [];
        try {
            foreach (Tiger_Module_Discovery::all() as $slug => $info) {
                $dir = self::_dir($slug);
                if ($dir === '') { continue; }
                $out[$slug] = [
                    'dir'        => $dir,
                    'name'       => (string) ($info['name'] ?? $slug),
                    'type'       => strtolower((string) ($info['type'] ?? '')),
                    'asset_base' => (string) ($info['asset_base'] ?? ''),
                    'key'        => (string) ($info['key'] ?? ''),
                ];
            }
        } catch (Throwable $e) {}
        return $out;
    }

    /** A module slug's absolute dir (app modules win over core), or ''. */
    protected static function _dir($slug)
    {
        foreach ([defined('APPLICATION_PATH') ? APPLICATION_PATH . '/modules' : '',
                  defined('TIGER_CORE_PATH')  ? TIGER_CORE_PATH  . '/modules' : ''] as $root) {
            if ($root !== '' && is_dir($root . '/' . $slug)) { return $root . '/' . $slug; }
        }
        return '';
    }

    /**
     * The PUBLIC URL base a module's assets are served from — a theme's `assetBase` symlink (`/_crafto`),
     * else the conventional `/_modules/<slug>`. Returns '' unless the symlink actually exists (the module
     * has been activated at least once), so a never-activated module's URLs can't 404.
     */
    protected static function _publicBase($slug, array $info)
    {
        if ($info['type'] === 'theme') {
            $key  = $info['key'] !== '' ? $info['key'] : preg_replace('/^theme-/', '', $slug);
            $base = $info['asset_base'] !== '' ? $info['asset_base'] : '/_' . $key;
        } else {
            $base = '/_modules/' . $slug;
        }
        if (defined('APPLICATION_ROOT') && !file_exists(APPLICATION_ROOT . '/public/' . ltrim($base, '/'))) {
            return '';
        }
        return $base;
    }

    /** Read a module's `media.json`, or null when absent/invalid. */
    protected static function _readManifest($dir)
    {
        $file = $dir . '/media.json';
        if (!is_file($file)) { return null; }
        $data = json_decode((string) @file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Expand a manifest to a flat, de-duplicated, sorted list of images relative to the module's `assets/`
     * dir: everything under `imageDir` (recursive glob) plus any explicit `images` entries.
     *
     * @return array<int,array{relpath:string,title:string,alt:string}>
     */
    protected static function _resolveImages($dir, $manifest)
    {
        if (!$manifest) { return []; }
        $assets = $dir . '/assets';
        $out = [];
        $seen = [];

        // Explicit, curated entries FIRST — they may carry pre-computed dimensions/size (baked by
        // bin/media-manifest.php) so the Library shows real size/dimensions without stat-ing each file.
        foreach ((array) ($manifest['images'] ?? []) as $entry) {
            $file = is_array($entry) ? (string) ($entry['file'] ?? '') : (string) $entry;
            $file = trim(str_replace('\\', '/', $file), '/');
            if ($file === '' || strpos($file, '..') !== false
                || !in_array(strtolower((string) pathinfo($file, PATHINFO_EXTENSION)), self::IMG_EXT, true)
                || isset($seen[$file])) {
                continue;
            }
            $seen[$file] = 1;
            $out[] = [
                'relpath' => $file,
                'title'   => is_array($entry) ? (string) ($entry['title'] ?? '') : '',
                'alt'     => is_array($entry) ? (string) ($entry['alt'] ?? '') : '',
                'w'       => is_array($entry) ? (int) ($entry['w'] ?? 0) : 0,
                'h'       => is_array($entry) ? (int) ($entry['h'] ?? 0) : 0,
                'size'    => is_array($entry) ? (int) ($entry['size'] ?? 0) : 0,
            ];
        }

        // Then sweep imageDir for anything not explicitly listed (dimensions unknown -> 0).
        $imgDir = isset($manifest['imageDir']) ? trim((string) $manifest['imageDir'], '/') : '';
        $match  = array_values(array_filter(array_map('strval', (array) ($manifest['match'] ?? []))));
        if ($imgDir !== '' && strpos($imgDir, '..') === false && is_dir($assets . '/' . $imgDir)) {
            try {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($assets . '/' . $imgDir, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $f) {
                    if (!$f->isFile() || !in_array(strtolower($f->getExtension()), self::IMG_EXT, true)) { continue; }
                    if ($match && !self::_matches($f->getFilename(), $match)) { continue; }   // curate the sweep
                    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($assets) + 1));
                    if (!isset($seen[$rel])) {
                        $seen[$rel] = 1;
                        $out[] = ['relpath' => $rel, 'title' => '', 'alt' => '', 'w' => 0, 'h' => 0, 'size' => 0];
                    }
                }
            } catch (Throwable $e) {}
        }

        usort($out, static function ($a, $b) { return strcasecmp($a['relpath'], $b['relpath']); });
        return $out;
    }

    /** True if $name matches any of the fnmatch glob patterns (case-insensitive). */
    protected static function _matches($name, array $patterns)
    {
        foreach ($patterns as $p) {
            if ($p !== '' && fnmatch($p, (string) $name, FNM_CASEFOLD)) { return true; }
        }
        return false;
    }
}
