<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * media-manifest.php — bake image dimensions + byte sizes into a module's `media.json`.
 *
 * A module's static media (Tiger_Media_Manifest) shows in the Media Library with real size + dimensions
 * WITHOUT the server stat-ing each file per request — the numbers are pre-computed here and stored in the
 * manifest's `images` list ({file,w,h,size}). Run it once at publish (or whenever the image set changes).
 *
 * It reads the module's existing `media.json` for the selection (`imageDir` + `match` globs, and/or an
 * explicit `images` list), scans the matching files under `assets/`, computes width/height (getimagesize)
 * + size (filesize), and rewrites `media.json` preserving `imageDir`/`match`/title/alt. A pure-filesystem
 * script — no app boot, run it anywhere the module's files live.
 *
 *   Usage:  php bin/media-manifest.php <module-dir>
 *   e.g.    php bin/media-manifest.php application/modules/theme-crafto-interior
 */

$dir = isset($argv[1]) ? rtrim((string) $argv[1], '/') : '';
if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php bin/media-manifest.php <module-dir>\n");
    exit(1);
}

$manFile = $dir . '/media.json';
$assets  = $dir . '/assets';
$exts    = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

$man = is_file($manFile) ? json_decode((string) file_get_contents($manFile), true) : [];
if (!is_array($man)) { $man = []; }

$imgDir = isset($man['imageDir']) ? trim((string) $man['imageDir'], '/') : '';
$match  = array_values(array_filter(array_map('strval', (array) ($man['match'] ?? []))));

// The selection: the imageDir/match sweep, unioned with any explicitly-listed images (whose title/alt
// we preserve).
$files = [];   // relpath => true
$meta  = [];   // relpath => ['title'=>…, 'alt'=>…]
if ($imgDir !== '' && is_dir($assets . '/' . $imgDir)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assets . '/' . $imgDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || !in_array(strtolower($f->getExtension()), $exts, true)) { continue; }
        if ($match) {
            $ok = false;
            foreach ($match as $p) { if (fnmatch($p, $f->getFilename(), FNM_CASEFOLD)) { $ok = true; break; } }
            if (!$ok) { continue; }
        }
        $files[str_replace('\\', '/', substr($f->getPathname(), strlen($assets) + 1))] = true;
    }
}
foreach ((array) ($man['images'] ?? []) as $e) {
    $file = trim(str_replace('\\', '/', is_array($e) ? (string) ($e['file'] ?? '') : (string) $e), '/');
    if ($file === '') { continue; }
    $files[$file] = true;
    if (is_array($e)) { $meta[$file] = ['title' => (string) ($e['title'] ?? ''), 'alt' => (string) ($e['alt'] ?? '')]; }
}
ksort($files, SORT_NATURAL | SORT_FLAG_CASE);

$images  = [];
$missing = 0;
foreach (array_keys($files) as $rel) {
    $abs = $assets . '/' . $rel;
    if (!is_file($abs)) { $missing++; continue; }
    $entry = ['file' => $rel, 'w' => 0, 'h' => 0, 'size' => (int) @filesize($abs)];
    if (strtolower((string) pathinfo($rel, PATHINFO_EXTENSION)) !== 'svg') {   // SVG has no raster size
        $d = @getimagesize($abs);
        if ($d) { $entry['w'] = (int) $d[0]; $entry['h'] = (int) $d[1]; }
    }
    if (!empty($meta[$rel]['title'])) { $entry['title'] = $meta[$rel]['title']; }
    if (!empty($meta[$rel]['alt']))   { $entry['alt']   = $meta[$rel]['alt']; }
    $images[] = $entry;
}

$out = [];
if ($imgDir !== '') { $out['imageDir'] = $imgDir; }
if ($match)         { $out['match']    = $match; }
$out['images'] = $images;

file_put_contents($manFile, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
fwrite(STDERR, 'Wrote ' . count($images) . ' images to ' . $manFile . ($missing ? " ({$missing} listed but missing on disk)" : '') . "\n");
