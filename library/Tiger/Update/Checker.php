<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Update_Checker — "what has an update?" for the WordPress-simple Updates screen.
 *
 * Diffs installed-vs-latest for the platform (TigerCore) and every installer-managed module, so the
 * Updates screen can list everything stale with a checkbox. Detection only — applying an update is
 * the installer's job (`Tiger_Module_Installer`) surfaced through `System_Service_Updates`.
 *
 * - **TigerCore**: `Tiger_Version::VERSION` vs the newest tag on Packagist (`repo.packagist.org/p2`).
 * - **Modules**: each `module` row's `version` vs the latest release on its `repository` (GitHub).
 *
 * Remote checks are file-cached (a few hours) so opening the screen doesn't hammer GitHub/Packagist.
 *
 * @api
 */
class Tiger_Update_Checker
{
    const CORE_PACKAGE = 'webtigers/tiger-core';
    const PACKAGIST    = 'https://repo.packagist.org/p2/%s.json';
    const CACHE_TTL    = 10800;   // 3h

    /**
     * Every checkable item (core + installer-managed modules), each with an `update` flag.
     *
     * @param  bool $refresh bypass the cache and re-check now
     * @return array<int,array> update descriptors
     */
    public static function all($refresh = false)
    {
        $out = [];
        if ($core = self::core($refresh)) {
            $out[] = $core;
        }
        foreach (self::modules($refresh) as $m) {
            $out[] = $m;
        }
        return $out;
    }

    /**
     * Only the items with a pending update.
     *
     * @param  bool $refresh bypass the cache
     * @return array<int,array>
     */
    public static function available($refresh = false)
    {
        return array_values(array_filter(self::all($refresh), static function ($u) {
            return !empty($u['update']);
        }));
    }

    /**
     * The TigerCore (platform) update descriptor, or null if the version can't be read.
     *
     * @param  bool $refresh bypass the cache
     * @return array|null
     */
    public static function core($refresh = false)
    {
        if (!class_exists('Tiger_Version')) {
            return null;
        }
        $installed = (string) Tiger_Version::VERSION;
        $latest    = self::_cached('core', $refresh, static function () {
            return self::_latestCore();
        });
        // `composer` where it can run (advise/auto in the CLI), else `manual` (the no-shell release-ZIP path).
        $method = (class_exists('Tiger_Vendor_Environment') && Tiger_Vendor_Environment::composerUsable()) ? 'composer' : 'manual';
        return self::_descriptor('core', 'TigerCore', 'tiger-core', $installed, $latest, $method, self::CORE_PACKAGE, null);
    }

    /**
     * Update descriptors for every installer-managed module (one with a repository + recorded version).
     *
     * @param  bool $refresh bypass the cache
     * @return array<int,array>
     */
    public static function modules($refresh = false)
    {
        try {
            $rows = (new Tiger_Model_Module())->bySlugMap();
        } catch (Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $slug => $row) {
            $repo      = (string) ($row->repository ?? '');
            $installed = (string) ($row->version ?? '');
            $parsed    = $repo !== '' ? Tiger_Module_Github::parseRepo($repo) : null;
            if ($installed === '' || !$parsed) {
                continue;   // discovered/local module — nothing authoritative to diff against
            }
            $ref = self::_cached('mod-' . $slug, $refresh, static function () use ($parsed) {
                return (string) Tiger_Module_Github::latestRef($parsed['org'], $parsed['repo']);
            });
            $out[] = self::_descriptor(
                'module', (string) ($row->name ?: $slug), $slug, $installed, self::_stripV($ref), 'installer', $repo, $ref
            );
        }
        return $out;
    }

    // ---- helpers ---------------------------------------------------------------

    protected static function _descriptor($type, $name, $slug, $installed, $latest, $method, $repository, $ref)
    {
        $latest = ($latest === null || $latest === '') ? null : self::_stripV($latest);
        $update = $latest !== null && version_compare($latest, self::_stripV($installed), '>');
        return [
            'type'       => $type,
            'name'       => $name,
            'slug'       => $slug,
            'installed'  => self::_stripV($installed),
            'latest'     => $latest ?? self::_stripV($installed),
            'update'     => $update,
            'method'     => $method,       // installer | composer | manual
            'repository' => $repository,
            'ref'        => $ref,
        ];
    }

    /** Newest non-dev version of tiger-core on Packagist. */
    protected static function _latestCore()
    {
        $body = Tiger_Module_Github::get(sprintf(self::PACKAGIST, self::CORE_PACKAGE));
        $data = is_string($body) ? json_decode($body, true) : null;
        $best = null;
        foreach (($data['packages'][self::CORE_PACKAGE] ?? []) as $rel) {
            $ver = self::_stripV((string) ($rel['version'] ?? ''));
            if ($ver === '' || stripos($ver, 'dev') !== false) {
                continue;
            }
            if ($best === null || version_compare($ver, $best, '>')) {
                $best = $ver;
            }
        }
        return $best;
    }

    protected static function _stripV($v)
    {
        return ltrim(trim((string) $v), 'vV');
    }

    /** Read-through file cache for a remote check. `$fn` returns the fresh value (string|null). */
    protected static function _cached($key, $refresh, callable $fn)
    {
        $file = self::_cacheDir() . '/' . preg_replace('/[^a-z0-9._-]/i', '_', $key) . '.json';
        if (!$refresh && is_file($file) && (time() - filemtime($file)) < self::CACHE_TTL) {
            $cached = json_decode((string) @file_get_contents($file), true);
            if (is_array($cached) && array_key_exists('v', $cached)) {
                return $cached['v'];
            }
        }
        $value = $fn();
        if ($value !== null && $value !== '') {
            @mkdir(self::_cacheDir(), 0775, true);
            @file_put_contents($file, json_encode(['v' => $value]));
        }
        return $value;
    }

    /** App-root cache dir (never system tmp — cPanel-safe). */
    protected static function _cacheDir()
    {
        $base = defined('APPLICATION_PATH') ? dirname(APPLICATION_PATH) : (getcwd() ?: '.');
        return $base . '/var/cache/updates';
    }
}
