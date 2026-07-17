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

if ($path !== '/' && is_file($root . '/public' . $path)) {
    return false;   // let the built-in server serve the static asset as-is
}
require $root . '/public/index.php';
