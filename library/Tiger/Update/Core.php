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

    /** Retries for a FAILED health probe, and the wait between them (seconds). Covers opcache turnover. */
    const HEALTH_RETRIES    = 3;
    const HEALTH_RETRY_WAIT = 2;

    /**
     * Header carrying the maintenance nonce on the post-swap health probe.
     *
     * The swap happens behind a 503 maintenance page, so a naive probe measures OUR OWN holding page
     * and concludes the new code is broken — which rolled back every successful update on any host
     * where the probe could actually reach the site. The probe presents the nonce, and
     * Tiger_Application lets exactly that request through to a real dispatch, so what is measured is
     * whether the NEW code boots. Read as an underscored server key: X-Tiger-Update-Probe.
     */
    const PROBE_HEADER = 'X-Tiger-Update-Probe';
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
        $probeNonce = self::_maintenance($work, true);
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

        // The rename is atomic on disk, but PHP does not notice immediately: with
        // opcache.validate_timestamps=1 and revalidate_freq=N, workers keep executing the PREVIOUS
        // bytecode for up to N seconds. Real visitors can therefore run a MIX of old and new code,
        // and the health probe below — which fires within a second — reaches a worker still running
        // the old code, reads the maintenance page it was supposed to be let through, and rolls back
        // a perfectly good update. Observed on a live cPanel host (revalidate_freq=2): probe at t+0
        // returned 503, a moment later the identical request returned 200.
        $opcacheReset = static::_resetOpcache();
        $add('opcache', $opcacheReset, $opcacheReset
            ? 'Opcode cache reset — the new code takes effect at once.'
            : 'Opcode cache could not be reset (absent or restricted) — the health probe retries to cover it.');

        // ---- health check --------------------------------------------------
        $liveVer = self::_versionIn($vendor);
        // Probe with retries. opcache_reset() above should make the first attempt authoritative, but it
        // can be unavailable or restricted (opcache.restrict_api), and a worker may still be mid-flight
        // — so a single "unhealthy" reading is not trusted until the cache has had time to turn over.
        // Only a FAILURE is retried: a healthy or inconclusive answer is taken immediately, so a good
        // update is never slowed down.
        $http = static::_httpHealth($probeNonce);      // true | false | null(unknown) — overridable for tests
        for ($try = 1; $http === false && $try <= self::HEALTH_RETRIES; $try++) {
            static::_pause(self::HEALTH_RETRY_WAIT);
            $http = static::_httpHealth($probeNonce);
        }
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
                    $db = Zend_Db_Table_Abstract::getDefaultAdapter();

                    $paths = self::_migrationPaths($vendor);
                    if ($db && $paths) {
                        (new Tiger_Db_Migrator($db, $paths))->migrate();
                        $add('migrate', true, 'Migrations applied (' . count($paths) . ' source'
                            . (count($paths) === 1 ? '' : 's') . ' — core, app and bundled modules).');
                    } else {
                        $add('migrate', true, 'No migrations to run.');
                    }
                }
            } catch (Throwable $e) {
                $add('migrate', false, 'Migration issue (code is updated + healthy — review): ' . $e->getMessage());
            }
        }

        // ---- re-publish assets ---------------------------------------------
        // vendor/ just moved under us. A SYMLINKED install needs nothing (the link already points at
        // the new files) and this is a cheap no-op there. A COPY-mode install — a host with
        // symlink() disabled, i.e. much of shared cPanel — would otherwise serve the OLD theme and
        // framework assets forever, silently, with updated PHP behind them. Fail-soft: the code is
        // already updated and healthy, so a publish problem is reported, never a rollback.
        try {
            $assets = Tiger_Install::republishAssets($root);
            if ($assets['republished']) {
                $add('assets', true, 'Re-published copied assets (this host blocks symlink()).');
            } elseif ($assets['error'] !== null) {
                $add('assets', false, 'Assets need re-publishing but it failed — run `tiger link:assets`: '
                    . $assets['error']);
            }
        } catch (Throwable $e) {
            $add('assets', false, 'Asset re-publish issue (code is updated + healthy — review): ' . $e->getMessage());
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

    /**
     * Every migration source a self-update must apply, existing dirs only.
     *
     * ONE authority for the scan — `Tiger_Module_Installer::migrationPaths()`, the same helper
     * `bin/tiger migrate`, the module installer and the web installer use. The self-update previously
     * hand-rolled its own list containing ONLY tiger-core/migrations, so migrations shipped inside
     * BUNDLED core modules were never applied: the code arrived but its tables did not, and the
     * feature then failed at runtime with nothing pointing back at the update. That is the same blind
     * spot the web installer had (TIGER-55); this was the last caller still on its own copy.
     *
     * Falls back to the core dir alone if the helper is unavailable — running core migrations beats
     * running none.
     *
     * @param  string $vendor the (already swapped) vendor dir
     * @return array<int,string> de-duplicated, existing migration directories
     */
    protected static function _migrationPaths($vendor)
    {
        $paths = (class_exists('Tiger_Module_Installer')
                  && method_exists('Tiger_Module_Installer', 'migrationPaths'))
            ? Tiger_Module_Installer::migrationPaths()
            : [$vendor . '/webtigers/tiger-core/migrations'];

        return array_values(array_filter(array_unique($paths), 'is_dir'));
    }

    /**
     * Drop the compiled bytecode for the code we just replaced. Without this, PHP serves the PREVIOUS
     * vendor/ for up to opcache.revalidate_freq seconds after the swap.
     *
     * Best effort by design: the API is absent when OPcache is off and can be closed off by
     * opcache.restrict_api on shared hosts. A false return is reported, not fatal — the probe retries
     * cover it, and a plain timestamp revalidation gets there on its own shortly.
     *
     * @return bool whether the cache was actually reset
     */
    protected static function _resetOpcache()
    {
        if (!function_exists('opcache_reset')) { return false; }
        try {
            return (bool) @opcache_reset();
        } catch (Throwable $e) {
            return false;   // restrict_api, or a disabled cache
        }
    }

    /** Wall-clock pause between health retries. A seam so tests never actually sleep. */
    protected static function _pause($seconds)
    {
        sleep((int) $seconds);
    }

    /** Best-effort HTTP boot check of the just-swapped code: true | false | null(unknown). */
    protected static function _httpHealth($nonce = '')
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '' || !function_exists('curl_init')) { return null; }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $ch = curl_init($scheme . '://' . $host . '/');
        // Present the nonce so the maintenance page steps aside for THIS request only — otherwise the
        // probe reads our own 503 and reports the new code as broken.
        $headers = ($nonce !== '') ? [self::PROBE_HEADER . ': ' . $nonce] : [];
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => self::HEALTH_TIMEOUT, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Tiger_Update health',
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $ok   = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($ok === false || $code === 0) { return null; }   // couldn't reach it — inconclusive
        return $code < 500;
    }

    protected static function _maintenance($work, $on)
    {
        $flag = $work . '/.maintenance';
        if (!$on) { @unlink($flag); return ''; }

        // The flag file carries "<timestamp> <nonce>". The timestamp keeps the existing 120s
        // auto-expiry (a crashed update can never wedge the site); the nonce is what lets the health
        // probe through the maintenance page. Freshly minted per update and never reused, and it only
        // exists while the flag does, so it cannot be replayed after the window closes.
        $nonce = bin2hex(random_bytes(16));
        @file_put_contents($flag, time() . ' ' . $nonce);
        return $nonce;
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
