<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Theme — read helpers for the ACTIVE theme's on-disk resources.
 *
 * The active theme's directory is resolved once at bootstrap (`_initTheme`) into the
 * `Tiger_ThemeDir` registry entry; these helpers read from there. They give the rest of the
 * platform a small, uniform way to reach a theme's **manifest** (`theme.json`) and its
 * **builder components** (`components/*.phtml`) without each caller re-deriving the path.
 *
 * A theme is otherwise resolved purely by path (see ARCHITECTURE §9a) — this class adds the
 * two data reads a theme needs to participate in the CMS: its manifest (asset base, canvas
 * CSS, skins) and its GrapesJS block library (THEMES.md Tier 2).
 *
 * @api
 */
class Tiger_Theme
{
    /**
     * The active theme's absolute directory, or '' if not resolved (e.g. a CLI run pre-boot).
     *
     * @return string
     */
    public static function dir()
    {
        return Zend_Registry::isRegistered('Tiger_ThemeDir') ? (string) Zend_Registry::get('Tiger_ThemeDir') : '';
    }

    /**
     * The theme's manifest (`theme.json`) as an array, or [] when absent/invalid.
     *
     * @return array<string,mixed>
     */
    public static function manifest()
    {
        return self::_manifestAt(self::dir());
    }

    /**
     * A theme's public stylesheet URLs (its manifest `canvasCss` — the same sheets its shell loads,
     * reachable via the theme's `public/_<assetBase>` symlink). Used to bake a forked page's head so it
     * self-loads its origin theme's CSS regardless of the active theme.
     *
     * @param  string|null $dir the theme dir (null = the active theme)
     * @return array<int,string>
     */
    public static function stylesheets($dir = null)
    {
        $man = self::_manifestAt(($dir !== null && $dir !== '') ? $dir : self::dir());
        $css = $man['canvasCss'] ?? [];
        return is_array($css) ? array_values(array_filter(array_map('strval', $css))) : [];
    }

    /** Read a theme.json manifest at a specific theme dir (for enumerating non-active themes). */
    protected static function _manifestAt($dir)
    {
        $file = rtrim((string) $dir, '/') . '/theme.json';
        if ($dir === '' || !is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /**
     * The theme's public asset base URL (the `public/_<x>` symlink), e.g. `/_theme`. From the
     * manifest's `assetBase`, else the conventional `/_theme`.
     *
     * @return string
     */
    public static function assetBase()
    {
        $man = self::manifest();
        return (isset($man['assetBase']) && $man['assetBase'] !== '') ? (string) $man['assetBase'] : '/_theme';
    }

    /**
     * The theme's GrapesJS block components. Each `components/<id>.phtml` is one block: a leading
     * `<!-- tiger:block label="…" category="…" icon="…" -->` hint names it; the rest is the block's
     * HTML. Returned as `[{id,label,category,media,content}]` for the visual builder's palette.
     *
     * @return array<int,array<string,string>>
     */
    public static function components()
    {
        $out = [];
        foreach (glob(self::dir() . '/components/*.phtml') ?: [] as $file) {
            $raw  = (string) file_get_contents($file);
            $meta = self::hint($raw, 'tiger:block');
            $body = preg_replace('/^\s*<!--\s*tiger:block\b.*?-->\s*/s', '', $raw, 1);
            $id   = basename($file, '.phtml');
            $out[] = [
                'id'       => $id,
                'label'    => $meta['label']   ?? ucfirst(str_replace('-', ' ', $id)),
                'category' => $meta['category'] ?? 'Theme',
                'media'    => $meta['icon']     ?? '',
                'content'  => trim((string) $body),
            ];
        }
        return $out;
    }

    /**
     * The active theme's page templates — every `content/**‍/*.phtml` it serves from files
     * (THEMES.md §8a) that is NOT a layout/partial skeleton, each with its `tiger:page` hint parsed.
     * The CMS surfaces these so an author can CUSTOMIZE one — fork it into an editable page row that
     * overrides the file (live-override).
     *
     * @return array<int,array<string,string>> [{slug,title,layout,skin}] sorted by title
     */
    public static function pages()
    {
        // Default kind: anything in content/ that isn't explicitly hinted as a layout/partial skeleton
        // is a page (preserves the historical "every content file is a page" behavior).
        return self::_scan('tiger:page', ['tiger:layout', 'tiger:partial']);
    }

    /**
     * The active theme's forkable LAYOUT skeletons — `content/**‍/*.phtml` hinted `tiger:layout`. A
     * layout is a body skeleton (`[partial]…[content]…[partial]`), NOT the outer shell (the shell stays
     * a theme file — AUTHORING.md §1). Forking one yields an editable `type=layout` row.
     *
     * @return array<int,array<string,string>> [{slug,title,layout,skin}] sorted by title
     */
    public static function layouts()
    {
        return self::_scan('tiger:layout');
    }

    /**
     * The active theme's forkable PARTIAL fragments — `content/**‍/*.phtml` hinted `tiger:partial`.
     * Forking one yields an editable `type=partial` row (a synced reference: header, footer, …).
     *
     * @return array<int,array<string,string>> [{slug,title,layout,skin}] sorted by title
     */
    public static function partials()
    {
        return self::_scan('tiger:partial');
    }

    /**
     * ALL forkable material in ONE pass — every `content/**‍/*.phtml`, bucketed by kind from its hint
     * (tiger:layout → layout, tiger:partial → partial, else page). Cheaper than pages()+layouts()+
     * partials() (which each re-scan the tree); use this where the whole set is needed at once (the
     * Theme Templates datatable, which a large theme — Porto ~830 — makes worth doing in one scan).
     *
     * @return array<int,array<string,string>> [{kind,slug,title,layout,skin}] sorted by title
     */
    public static function forkables($themeDir = null)
    {
        $root = ($themeDir !== null && $themeDir !== '') ? (string) $themeDir : self::dir();
        $base = $root . '/content';
        if ($root === '' || !is_dir($base)) {
            return [];
        }

        // A THEME may ship hundreds of forkables (Porto ~834), and the Theme Templates datatable scans
        // EVERY installed theme per request. So: a cheap stat sweep builds a fingerprint; the parsed
        // result (which needs the expensive file_get_contents per file to read hints) is cached against
        // it — one cache file per theme, re-scanned only when a file changes.
        try {
            $files = [];
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'phtml') {
                    $files[$file->getPathname()] = $file->getMTime() . ':' . $file->getSize();
                }
            }
        } catch (Throwable $e) {
            return [];
        }
        ksort($files);
        $fp     = md5($root . '|' . serialize($files));
        $cached = self::_cacheGet($root, $fp);
        if ($cached !== null) { return $cached; }

        $out = [];
        foreach ($files as $path => $_) {
            $raw  = (string) file_get_contents($path);
            $kind = self::_hasTag($raw, 'tiger:layout') ? 'layout'
                  : (self::_hasTag($raw, 'tiger:partial') ? 'partial' : 'page');
            $slug = str_replace('\\', '/', substr($path, strlen($base) + 1));
            $slug = preg_replace('/\.phtml$/i', '', $slug);
            $meta = self::hint($raw, 'tiger:' . $kind);
            $out[] = [
                'kind'   => $kind,
                'slug'   => $slug,
                'title'  => $meta['title']  ?? ucfirst(str_replace(['-', '/'], ' ', $slug)),
                'layout' => $meta['layout'] ?? '',
                'skin'   => $meta['skin']   ?? '',
            ];
        }
        usort($out, static function ($a, $b) { return strcasecmp($a['title'], $b['title']); });
        self::_cacheSet($root, $fp, $out);
        return $out;
    }

    /**
     * The ACTIVE theme — the one THIS request resolved (tiger.theme, org-scopable, + preview cookie).
     * Only the active theme's content is forkable: an installed-but-INACTIVE theme has no asset symlink
     * (it's created on Activate, removed on Deactivate), so its templates can't render and must not
     * surface. Mirrors the Modules admin's rule — a theme is active iff tiger.theme === its key.
     *
     * @return array{key:string,name:string,dir:string}
     */
    public static function active()
    {
        $dir = self::dir();
        $man = self::_manifestAt($dir);
        // Derive the key from the manifest, else the dir basename (minus a `theme-` module prefix) — so a
        // runtime Tiger_ThemeDir override (an admin preview, or a test's temp theme) is reflected, not the
        // boot-time THEME constant (which can't change mid-request).
        $key = (string) ($man['key'] ?? ($dir !== '' ? preg_replace('/^theme-/', '', basename($dir)) : ''));
        return [
            'key'  => $key,
            'name' => (string) ($man['name'] ?? ($key !== '' ? ucfirst(str_replace('-', ' ', $key)) : '')),
            'dir'  => $dir,
        ];
    }

    /**
     * Scan `content/**‍/*.phtml` for files carrying $hintTag (skipping any that carry an $exclude tag),
     * returning the fork-list shape. The shared engine behind pages()/layouts()/partials().
     *
     * @param  string        $hintTag  the tiger:* tag whose hint drives title/layout/skin
     * @param  array<string> $exclude  tags that, if present, remove the file from THIS list
     * @return array<int,array<string,string>>
     */
    protected static function _scan($hintTag, array $exclude = [])
    {
        $base = self::dir() . '/content';
        if (self::dir() === '' || !is_dir($base)) {
            return [];
        }
        $out = [];
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'phtml') { continue; }
                $raw = (string) file_get_contents($file->getPathname());
                // Membership: this list requires its own tag EXCEPT the pages() list, which is the
                // default kind (no tag required) and only excludes the layout/partial skeletons.
                if ($exclude) {
                    foreach ($exclude as $x) { if (self::_hasTag($raw, $x)) { continue 2; } }
                } elseif (!self::_hasTag($raw, $hintTag)) {
                    continue;
                }
                $slug = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                $slug = preg_replace('/\.phtml$/i', '', $slug);
                $meta = self::hint($raw, $hintTag);
                $out[] = [
                    'slug'   => $slug,
                    'title'  => $meta['title']  ?? ucfirst(str_replace(['-', '/'], ' ', $slug)),
                    'layout' => $meta['layout'] ?? '',
                    'skin'   => $meta['skin']   ?? '',
                ];
            }
        } catch (Throwable $e) {
            return $out;
        }
        usort($out, static function ($a, $b) { return strcasecmp($a['title'], $b['title']); });
        return $out;
    }

    /** True if $raw carries a leading-comment hint tag `<!-- <tag> … -->`. */
    protected static function _hasTag($raw, $tag)
    {
        return (bool) preg_match('/<!--\s*' . preg_quote($tag, '/') . '\b/', (string) $raw);
    }

    /**
     * One forkable theme template by kind + slug — its hint + the body (any tiger:* hint comment
     * stripped), ready to fork into the matching CMS row. null if the kind is unknown, the slug is
     * invalid, or the file is absent.
     *
     * @param  string      $kind page | layout | partial
     * @param  string      $slug the content slug (may be nested)
     * @param  string|null $dir  the theme dir to read from (null = the active theme)
     * @return array<string,string>|null [{kind,slug,title,layout,skin,body}]
     */
    public static function template($kind, $slug, $dir = null)
    {
        $tag = ['page' => 'tiger:page', 'layout' => 'tiger:layout', 'partial' => 'tiger:partial'][$kind] ?? null;
        if ($tag === null) { return null; }

        $root = ($dir !== null && $dir !== '') ? (string) $dir : self::dir();
        $slug = trim((string) $slug, '/');
        // Strict, dot-free token — can never traverse out of content/ (same guard as ThemeContent).
        if ($slug === '' || $root === '' || !preg_match('#^[A-Za-z0-9][A-Za-z0-9/_-]*$#', $slug)) {
            return null;
        }
        $file = $root . '/content/' . $slug . '.phtml';
        if (!is_file($file)) {
            return null;
        }
        $raw  = (string) file_get_contents($file);
        $meta = self::hint($raw, $tag);
        $body = preg_replace('/^\s*<!--\s*tiger:(?:page|layout|partial)\b.*?-->\s*/s', '', $raw, 1);
        return [
            'kind'   => $kind,
            'slug'   => $slug,
            'title'  => $meta['title']  ?? ucfirst(str_replace(['-', '/'], ' ', $slug)),
            'layout' => $meta['layout'] ?? '',
            'skin'   => $meta['skin']   ?? '',
            'body'   => trim((string) $body),
        ];
    }

    /**
     * One PAGE template by slug — a thin back-compat wrapper over template('page', …).
     *
     * @param  string $slug the content slug (may be nested)
     * @return array<string,string>|null [{slug,title,layout,skin,body}]
     */
    public static function page($slug)
    {
        return self::template('page', $slug);
    }

    /**
     * Parse a leading `<!-- <tag> key="value" … -->` hint comment into an assoc array (empty if none).
     * The shared parser behind `tiger:page` (theme static pages) and `tiger:block` (components).
     *
     * @param  string $raw the file contents
     * @param  string $tag the hint tag (e.g. `tiger:page`, `tiger:block`)
     * @return array<string,string>
     */
    public static function hint($raw, $tag)
    {
        $meta = [];
        if (preg_match('/<!--\s*' . preg_quote($tag, '/') . '\b(.*?)-->/s', (string) $raw, $m)
            && preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $m[1], $kv, PREG_SET_ORDER)) {
            foreach ($kv as $pair) {
                $meta[$pair[1]] = $pair[2];
            }
        }
        return $meta;
    }

    /** The forkables cache file for a theme dir (one file per theme, inside the app cache root). */
    protected static function _cacheFile($root)
    {
        if (!defined('APPLICATION_ROOT')) { return ''; }
        return APPLICATION_ROOT . '/var/cache/theme/forkables-' . md5((string) $root) . '.json';
    }

    /** Return the cached forkables for $root iff the stored fingerprint still matches; else null. */
    protected static function _cacheGet($root, $fp)
    {
        $f = self::_cacheFile($root);
        if ($f === '' || !is_file($f)) { return null; }
        $d = json_decode((string) file_get_contents($f), true);
        return (is_array($d) && ($d['fp'] ?? '') === $fp && isset($d['data']) && is_array($d['data'])) ? $d['data'] : null;
    }

    /** Persist the scanned forkables + fingerprint for $root (best-effort; a read-only cache dir is fine). */
    protected static function _cacheSet($root, $fp, array $data)
    {
        $f = self::_cacheFile($root);
        if ($f === '') { return; }
        if (!is_dir(dirname($f))) { @mkdir(dirname($f), 0775, true); }
        @file_put_contents($f, json_encode(['fp' => $fp, 'data' => $data]), LOCK_EX);
    }
}
