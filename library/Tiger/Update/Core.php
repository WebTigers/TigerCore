<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Update_Core — no-shell TigerCore self-update via a pre-resolved vendored release ZIP.
 *
 * The hard case: a running framework replacing its own `vendor/`, with no shell and no Composer.
 * We never resolve dependencies on the host — CI ships a **pre-resolved `vendor/`** (Composer run
 * off-box) as a checksummed ZIP; the host only downloads → verifies → **atomically swaps** `vendor/`
 * (rename-based, same filesystem) → health-checks → rolls back on failure. A maintenance flag covers
 * the millisecond swap window. See DEPENDENCIES.md (same "resolve off-box" thesis) + UPDATING.md.
 *
 * The current PHP process keeps the OLD classes it already loaded; the NEW code serves the *next*
 * request. So the health check is file-level (the swapped-in `Tiger_Version`) plus a best-effort HTTP
 * self-check (a fresh request = new code). On any doubt we restore the previous `vendor/` and keep
 * the bad copy for inspection — fail-safe over clever.
 *
 * @api
 */
class Tiger_Update_Core
{
    const HEALTH_TIMEOUT = 15;
    const GH_API         = 'https://api.github.com';

    /**
     * Resolve a version's pre-built vendored release ZIP from the tiger-core GitHub release assets:
     * a `*vendor*.zip` asset + its `*.zip.sha256`. Returns {url, sha256, version} or null when no
     * such release ZIP is published yet (the caller then falls back to Composer/advisory).
     *
     * @param  string $version the target version (e.g. "0.6.0-beta")
     * @param  string $org
     * @param  string $repo
     * @return array|null
     */
    public static function resolveRelease($version, $org = 'WebTigers', $repo = 'tiger-core')
    {
        if (!class_exists('Tiger_Module_Github')) {
            return null;
        }
        $v    = self::_norm($version);
        $body = Tiger_Module_Github::get(self::GH_API . "/repos/{$org}/{$repo}/releases/tags/v{$v}");
        $rel  = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($rel) || empty($rel['assets'])) {
            return null;
        }
        $zip = null;
        $sha = null;
        foreach ($rel['assets'] as $a) {
            $name = strtolower((string) ($a['name'] ?? ''));
            $url  = (string) ($a['browser_download_url'] ?? '');
            if ($url === '') { continue; }
            if (substr($name, -11) === '.zip.sha256') {
                $body = Tiger_Module_Github::get($url);
                if (is_string($body) && $body !== '') { $sha = strtolower(trim(explode(' ', trim($body))[0])); }
            } elseif (substr($name, -4) === '.zip' && strpos($name, 'vendor') !== false) {
                $zip = $url;
            }
        }
        return $zip ? ['url' => $zip, 'sha256' => $sha, 'version' => $v] : null;
    }

    /**
     * Perform the swap. Returns {ok, version?, log:[{step, ok, detail}]} — never throws.
     *
     * @param  array $opts {url: string, sha256?: string, version?: string, migrate?: bool}
     * @return array
     */
    public static function update(array $opts)
    {
        $log = [];
        $add = static function ($step, $ok, $detail) use (&$log) {
            $log[] = ['step' => $step, 'ok' => (bool) $ok, 'detail' => $detail];
            if (class_exists('Tiger_Log')) {
                Tiger_Log::info('update.core', ['step' => $step, 'ok' => (bool) $ok, 'detail' => $detail]);
            }
        };
        $fail = static function ($detail) use (&$log, $add) {
            $add('error', false, $detail);
            return ['ok' => false, 'log' => $log];
        };

        $url    = (string) ($opts['url'] ?? '');
        $sha    = isset($opts['sha256']) ? (string) $opts['sha256'] : null;
        $target = isset($opts['version']) ? self::_norm($opts['version']) : null;
        if ($url === '') {
            return $fail('No release-ZIP URL supplied.');
        }

        $root   = self::_appRoot();
        $vendor = $root . '/vendor';
        $work   = $root . '/var/update';

        // ---- pre-flight -----------------------------------------------------
        if (!self::_canExtract()) {
            return $fail('No ZipArchive/PharData available — enable ext-zip (or ext-phar) to self-update.');
        }
        if (!is_dir($vendor)) {
            return $fail('No vendor/ directory to update at ' . $vendor . '.');
        }
        if (!is_writable($vendor) || !is_writable(dirname($vendor))) {
            return $fail('vendor/ and its parent must be writable for the swap.');
        }
        if (!is_dir($work) && !@mkdir($work, 0775, true)) {
            return $fail('Cannot create the var/update working dir.');
        }
        $add('preflight', true, 'Host can extract + swap (writable vendor/, extractor present).');

        // ---- download + verify ---------------------------------------------
        $zip = $work . '/tiger-core-' . ($target ?: 'new') . '.zip';
        if (!self::_download($url, $zip)) {
            return $fail('Download failed: ' . $url);
        }
        $add('download', true, 'Downloaded ' . self::_hsize((int) @filesize($zip)) . '.');
        if ($sha !== null) {
            if (!hash_equals(strtolower($sha), strtolower((string) hash_file('sha256', $zip)))) {
                @unlink($zip);
                return $fail('Checksum mismatch — refusing to install.');
            }
            $add('verify', true, 'sha256 verified.');
        } else {
            $add('verify', true, 'No checksum supplied — skipped (supply one in production).');
        }

        // ---- extract to staging (zip-slip guarded) -------------------------
        $stage = $work . '/staging-' . getmypid();
        self::_rrmdir($stage);
        if (!@mkdir($stage, 0775, true)) {
            return $fail('Cannot create the staging dir.');
        }
        if (!self::_extract($zip, $stage)) {
            self::_rrmdir($stage);
            return $fail('Extract failed (or an unsafe path was found in the archive).');
        }
        $newVendor = self::_locateVendor($stage);
        if (!$newVendor) {
            self::_rrmdir($stage);
            return $fail('The release ZIP contains no vendor/ tree.');
        }
        $newVer = self::_versionIn($newVendor);
        if ($newVer === null) {
            self::_rrmdir($stage);
            return $fail('The staged vendor/ has no readable TigerCore version.');
        }
        if ($target !== null && self::_norm($newVer) !== $target) {
            self::_rrmdir($stage);
            return $fail("Staged version {$newVer} does not match the target {$opts['version']}.");
        }
        $add('stage', true, "Staged pre-resolved vendor/ — TigerCore {$newVer}.");

        // ---- the atomic swap (renames on one filesystem) -------------------
        self::_maintenance($work, true);
        $old = $root . '/vendor.old-' . getmypid();
        if (!@rename($vendor, $old)) {
            self::_maintenance($work, false);
            self::_rrmdir($stage);
            return $fail('Swap failed moving the current vendor/ aside.');
        }
        if (!@rename($newVendor, $vendor)) {
            @rename($old, $vendor);              // put the original back
            self::_maintenance($work, false);
            self::_rrmdir($stage);
            return $fail('Swap failed moving the new vendor/ in — restored the previous vendor/.');
        }
        $add('swap', true, 'vendor/ swapped atomically.');

        // ---- health check --------------------------------------------------
        $liveVer = self::_versionIn($vendor);
        $http    = static::_httpHealth();         // true | false | null(unknown) — overridable for tests
        $healthy = $liveVer !== null && ($target === null || self::_norm($liveVer) === $target) && $http !== false;
        if (!$healthy) {
            $bad = $root . '/vendor.bad-' . getmypid();
            @rename($vendor, $bad);
            @rename($old, $vendor);
            self::_maintenance($work, false);
            self::_rrmdir($stage);
            $add('rollback', true, 'Health check failed — restored the previous vendor/ (bad copy kept at '
                . basename($bad) . ' for inspection).');
            return ['ok' => false, 'version' => $liveVer, 'log' => $log];
        }
        $add('health', true, 'Live TigerCore ' . $liveVer
            . ($http === true ? ' — boots OK.' : ' (file-level; HTTP self-check unavailable).'));

        // ---- migrations (best-effort; a failure here does NOT roll back healthy code) ----
        if (!empty($opts['migrate']) || !array_key_exists('migrate', $opts)) {
            try {
                if (class_exists('Tiger_Db_Migrator') && class_exists('Zend_Db_Table_Abstract')) {
                    $db  = Zend_Db_Table_Abstract::getDefaultAdapter();
                    $dir = $vendor . '/webtigers/tiger-core/migrations';
                    if ($db && is_dir($dir)) {
                        (new Tiger_Db_Migrator($db, [$dir]))->migrate();
                        $add('migrate', true, 'Core migrations applied.');
                    } else {
                        $add('migrate', true, 'No core migrations to run.');
                    }
                }
            } catch (Throwable $e) {
                $add('migrate', false, 'Migration issue (code is updated + healthy — review): ' . $e->getMessage());
            }
        }

        // ---- commit --------------------------------------------------------
        self::_rrmdir($old);
        self::_rrmdir($stage);
        @unlink($zip);
        self::_maintenance($work, false);
        $add('done', true, "Updated TigerCore to {$liveVer}. The new code serves the next request.");
        return ['ok' => true, 'version' => $liveVer, 'log' => $log];
    }

    /** Is a core self-update possible on this host? (extractor + writable vendor/). */
    public static function possible()
    {
        $vendor = self::_appRoot() . '/vendor';
        return self::_canExtract() && is_dir($vendor) && is_writable($vendor) && is_writable(dirname($vendor));
    }

    /** The maintenance flag path (present + fresh ⇒ the app should serve a 503). */
    public static function maintenanceFlag()
    {
        return self::_appRoot() . '/var/update/.maintenance';
    }

    // ---- helpers ---------------------------------------------------------------

    protected static function _appRoot()
    {
        if (defined('APPLICATION_ROOT')) { return APPLICATION_ROOT; }
        if (defined('APPLICATION_PATH')) { return dirname(APPLICATION_PATH); }
        return getcwd() ?: '.';
    }

    protected static function _canExtract()
    {
        return class_exists('ZipArchive') || class_exists('PharData');
    }

    protected static function _download($url, $dest)
    {
        if (strncmp($url, 'file://', 7) === 0 || (isset($url[0]) && $url[0] === '/')) {
            $data = @file_get_contents($url);
            return $data !== false && @file_put_contents($dest, $data) !== false;
        }
        if (function_exists('curl_init')) {
            $fp = @fopen($dest, 'w');
            if (!$fp) { return false; }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 300, CURLOPT_FAILONERROR => true, CURLOPT_USERAGENT => 'Tiger_Update',
            ]);
            $ok = curl_exec($ch);
            fclose($fp);
            return $ok !== false && is_file($dest) && filesize($dest) > 0;
        }
        $data = @file_get_contents($url);
        return $data !== false && @file_put_contents($dest, $data) !== false;
    }

    /** Extract a .zip (ZipArchive, zip-slip guarded) or .tar.gz (PharData) into $into. */
    protected static function _extract($archive, $into)
    {
        if (class_exists('ZipArchive')) {
            $za = new ZipArchive();
            if ($za->open($archive) === true) {
                for ($i = 0; $i < $za->numFiles; $i++) {
                    $name = (string) $za->getNameIndex($i);
                    if ($name === '' || $name[0] === '/' || strpos($name, '..') !== false) {
                        $za->close();
                        return false;   // zip-slip / absolute path — refuse
                    }
                }
                $ok = $za->extractTo($into);
                $za->close();
                if ($ok) { return true; }
            }
        }
        try {
            (new PharData($archive))->extractTo($into, null, true);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Find the vendor/ dir in a staging tree: staging/vendor, or staging/<single>/vendor. */
    protected static function _locateVendor($stage)
    {
        if (is_dir($stage . '/vendor')) { return $stage . '/vendor'; }
        foreach (glob($stage . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            if (is_dir($d . '/vendor')) { return $d . '/vendor'; }
        }
        return null;
    }

    /** Read TigerCore's VERSION constant straight from the file (no class load). */
    protected static function _versionIn($vendorDir)
    {
        $file = $vendorDir . '/webtigers/tiger-core/library/Tiger/Version.php';
        if (!is_file($file)) { return null; }
        return preg_match('/VERSION\s*=\s*[\'"]([^\'"]+)[\'"]/', (string) @file_get_contents($file), $m)
            ? $m[1] : null;
    }

    /** Best-effort HTTP boot check of the just-swapped code: true | false | null(unknown). */
    protected static function _httpHealth()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || !function_exists('curl_init')) { return null; }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $ch = curl_init($scheme . '://' . $host . '/');
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => self::HEALTH_TIMEOUT, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Tiger_Update health',
        ]);
        $ok   = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($ok === false || $code === 0) { return null; }   // couldn't reach it — inconclusive
        return $code < 500;
    }

    protected static function _maintenance($work, $on)
    {
        $flag = $work . '/.maintenance';
        if ($on) { @file_put_contents($flag, (string) time()); }
        else { @unlink($flag); }
    }

    protected static function _norm($v)
    {
        return ltrim(trim((string) $v), 'vV');
    }

    protected static function _hsize($bytes)
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB'
            : ($bytes >= 1024 ? round($bytes / 1024) . ' KB' : $bytes . ' B');
    }

    protected static function _rrmdir($dir)
    {
        if (!is_dir($dir)) { if (is_file($dir)) { @unlink($dir); } return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            (is_dir($p) && !is_link($p)) ? self::_rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
