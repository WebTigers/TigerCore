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
        $file = self::dir() . '/theme.json';
        if (!is_file($file)) {
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
     * (THEMES.md §8a), each with its `tiger:page` hint parsed. The CMS surfaces these so an author
     * can CUSTOMIZE one — fork it into an editable page row that overrides the file (live-override).
     *
     * @return array<int,array<string,string>> [{slug,title,layout,skin}] sorted by title
     */
    public static function pages()
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
                $slug = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
                $slug = preg_replace('/\.phtml$/i', '', $slug);
                $meta = self::hint((string) file_get_contents($file->getPathname()), 'tiger:page');
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

    /**
     * One page template by slug — its `tiger:page` hint + the body (hint comment stripped), ready
     * to fork into a CMS page. null if the slug is invalid or the file is absent.
     *
     * @param  string $slug the content slug (may be nested)
     * @return array<string,string>|null [{slug,title,layout,skin,body}]
     */
    public static function page($slug)
    {
        $slug = trim((string) $slug, '/');
        // Strict, dot-free token — can never traverse out of content/ (same guard as ThemeContent).
        if ($slug === '' || self::dir() === '' || !preg_match('#^[A-Za-z0-9][A-Za-z0-9/_-]*$#', $slug)) {
            return null;
        }
        $file = self::dir() . '/content/' . $slug . '.phtml';
        if (!is_file($file)) {
            return null;
        }
        $raw  = (string) file_get_contents($file);
        $meta = self::hint($raw, 'tiger:page');
        $body = preg_replace('/^\s*<!--\s*tiger:page\b.*?-->\s*/s', '', $raw, 1);
        return [
            'slug'   => $slug,
            'title'  => $meta['title']  ?? ucfirst(str_replace(['-', '/'], ' ', $slug)),
            'layout' => $meta['layout'] ?? '',
            'skin'   => $meta['skin']   ?? '',
            'body'   => trim((string) $body),
        ];
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
}
