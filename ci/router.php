<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * CI-only router for PHP's built-in server — the stand-in for Apache's `.htaccess` front-controller
 * rewrite. Serves a real file from public/ when one exists, else hands the request to the front
 * controller. Launched from the app root: `php -S 127.0.0.1:8000 <tiger-core>/ci/router.php`.
 * Not for production — a real deploy uses the shipped `.htaccess` / vhost.
 */
$root = getcwd();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    // Serve it HERE rather than `return false`. The built-in server resolves a returned-false request
    // against its OWN docroot — the app root, since that is where we launch it — not public/. So every
    // static asset 404'd, and the smoke suite, whose job is to prove a release SERVES, had never
    // served a stylesheet or a script. Nothing noticed because nothing checked (TIGER-72's lesson).
    // Guard the REQUEST PATH, not the resolved target. public/_theme and public/_tiger are symlinks
    // that deliberately point OUT of public/ (into vendor/ and the theme) — that is the whole
    // asset-publishing design — so a realpath-containment check would reject precisely the files this
    // is meant to serve. Refuse traversal in the path instead.
    if (strpos($path, '..') !== false || strpos($path, "\0") !== false) {
        http_response_code(404);
        return true;
    }
    $types = [
        'css' => 'text/css', 'js' => 'application/javascript', 'json' => 'application/json',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'map' => 'application/json',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
        'txt' => 'text/plain', 'xml' => 'application/xml',
    ];
    $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($file));
    readfile($file);
    return true;
}

require $root . '/public/index.php';
