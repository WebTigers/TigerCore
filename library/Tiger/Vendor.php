<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Vendor — provisions a third-party PHP library and makes it autoloadable, on any host.
 *
 * It picks the best tier the environment + the library allow — Composer (Tier 1), a pre-built
 * bundle (Tier 2), or a raw source tarball (Tier 3) — fails closed, and registers a shared
 * autoloader so every module can find the library. It beats dependency hell by CONSUMING
 * pre-resolved bundles (resolution runs off-box, once) rather than solving a dependency graph on a
 * shared host. See DEPENDENCIES.md for the full model.
 *
 * @api
 */
class Tiger_Vendor
{
    /** The registry index (nightly-built bundles.json). Override via config `tiger.vendor.registry` or the TIGER_VENDOR_REGISTRY env. */
    const REGISTRY_INDEX_URL = 'https://raw.githubusercontent.com/WebTigers/tiger-vendor-bundles/main/bundles.json';

    /**
     * Register the autoloader of every library already in the shared store. Call once at bootstrap
     * so `Aws\`, `Stripe\`, etc. resolve for every module. A no-op if the store is empty.
     *
     * @return void
     */
    public static function registerAutoloaders()
    {
        $store = Tiger_Vendor_Environment::storeDir();
        if (!is_dir($store)) {
            return;
        }
        foreach (glob($store . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $auto = $dir . '/autoload.php';
            if (is_file($auto)) {
                require_once $auto;
            }
        }
    }

    /**
     * Is a library already present — in the store (Tier 2/3) or Composer's vendor/ (Tier 1)?
     *
     * @param  string $name the package name (e.g. "aws/aws-sdk-php")
     * @return bool
     */
    public static function isInstalled($name)
    {
        return is_dir(Tiger_Vendor_Environment::storeDir() . '/' . self::_slug($name))
            || is_dir(Tiger_Vendor_Environment::appRoot() . '/vendor/' . $name);
    }

    /**
     * Ensure a PHP library is present + autoloadable, choosing the best tier. Never throws — returns
     * a status the installer can act on (and, for an optional dep, ignore).
     *
     * @param  array $dep {name, constraint?, bundle?, sha256?, tarball?} from module.json dependencies.php
     * @return array{ok:bool,tier:string,name:string,message:string}
     */
    public static function ensure(array $dep)
    {
        $name = (string) ($dep['name'] ?? '');
        if ($name === '') {
            return self::_status(false, 'none', $name, 'No dependency name given.');
        }
        $constraint = (string) ($dep['constraint'] ?? '');

        // Already present? The store holds ONE copy per package (keyed by name), so a second module
        // asking for the same lib reuses it — the dedup that guarantees there's only ever one Stripe.
        // But honor the ONE-VERSION rule: reuse only if the installed version satisfies THIS module's
        // constraint; a genuine disagreement is REPORTED, never silently double-installed. (§6)
        if (self::isInstalled($name)) {
            $have = self::_installedVersion($name);
            if ($constraint === '' || $have === null || self::_satisfies($have, $constraint)) {
                return self::_status(true, 'present', $name, 'Already installed' . ($have !== null ? " ({$have})" : '') . '.');
            }
            return self::_status(false, 'conflict', $name,
                "Version conflict: {$name} {$have} is already installed but this module needs {$constraint}. "
                . 'Tiger keeps one shared version per install — reconcile the two modules\' constraints.');
        }

        // Tier 1 — Composer, only if it can genuinely run.
        if ($constraint !== '' && Tiger_Vendor_Environment::composerUsable()) {
            if (self::_composerRequire($name, $constraint)['ok']) {
                return self::_status(true, 'composer', $name, 'Installed via Composer.');
            }
            // fall through — try a bundle rather than failing outright
        }

        // Tier 2 — a pre-resolved bundle. Prefer the REGISTRY INDEX (nightly-fresh, resolved by
        // name+constraint) so a module never pins a stale URL; fall back to a bundle URL the module
        // declared explicitly (off-registry / air-gapped mirror).
        if ($constraint !== '') {
            $hit = self::_resolveFromIndex($name, $constraint);
            if ($hit !== null && !empty($hit['url'])) {
                $r = self::installTarball((string) $hit['url'], $name, $hit['sha256'] ?? null);
                if ($r['ok']) {
                    return self::_status(true, 'bundle', $name, "Installed bundle {$hit['version']} from the registry.");
                }
            }
        }
        if (!empty($dep['bundle'])) {
            $r = self::installTarball((string) $dep['bundle'], $name, $dep['sha256'] ?? null);
            if ($r['ok']) {
                return self::_status(true, 'bundle', $name, 'Installed from the declared bundle.');
            }
            return self::_status(false, 'bundle', $name, $r['message']);
        }

        // Tier 3 — raw source tarball (only sane for a dependency-free library).
        if (!empty($dep['tarball'])) {
            $r = self::installTarball((string) $dep['tarball'], $name, $dep['sha256'] ?? null, ['generate_autoload' => true]);
            return $r['ok']
                ? self::_status(true, 'tarball', $name, 'Installed from source tarball.')
                : self::_status(false, 'tarball', $name, $r['message']);
        }

        return self::_status(false, 'none', $name,
            'No usable Composer, and no registry bundle or tarball source for this host — a pre-built bundle is needed.');
    }

    /**
     * The version of an installed library — from the store bundle's `bundle.json`, or Composer's
     * `installed.json`. Used to enforce the one-version rule. Null if unknown.
     *
     * @param  string $name the package name
     * @return string|null
     */
    public static function installedVersion($name)
    {
        return self::_installedVersion($name);
    }

    // ---- registry index resolution --------------------------------------------

    /**
     * Resolve the newest published bundle for a package whose version satisfies the constraint, from
     * the registry index (bundles.json). Returns {version, url, sha256} or null.
     */
    protected static function _resolveFromIndex($name, $constraint)
    {
        $entries = self::_registryIndex()['bundles'][$name] ?? null;
        if (!is_array($entries)) {
            return null;
        }
        $best = null;
        foreach ($entries as $e) {
            if (!isset($e['version'], $e['url']) || !self::_satisfies((string) $e['version'], $constraint)) {
                continue;
            }
            if ($best === null || version_compare((string) $e['version'], (string) $best['version'], '>')) {
                $best = $e;
            }
        }
        return $best;
    }

    /** Fetch + cache the registry index for this request. Fails soft to an empty index. */
    protected static function _registryIndex()
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $body = self::_httpGet(self::_registryUrl());
        $data = is_string($body) ? json_decode($body, true) : null;
        return $cache = (is_array($data) && isset($data['bundles'])) ? $data : ['bundles' => []];
    }

    /** The registry index URL — config `tiger.vendor.registry`, else env, else the default. */
    protected static function _registryUrl()
    {
        try {
            $u = (string) (Zend_Registry::get('Zend_Config')->tiger->vendor->registry ?? '');
            if ($u !== '') {
                return $u;
            }
        } catch (Throwable $e) {
        }
        $env = getenv('TIGER_VENDOR_REGISTRY');
        return ($env !== false && $env !== '') ? $env : self::REGISTRY_INDEX_URL;
    }

    protected static function _installedVersion($name)
    {
        $meta = Tiger_Vendor_Environment::storeDir() . '/' . self::_slug($name) . '/bundle.json';
        if (is_file($meta)) {
            $j = json_decode((string) @file_get_contents($meta), true);
            if (isset($j['version'])) {
                return (string) $j['version'];
            }
        }
        $installed = Tiger_Vendor_Environment::appRoot() . '/vendor/composer/installed.json';
        if (is_file($installed)) {
            $j = json_decode((string) @file_get_contents($installed), true);
            foreach (($j['packages'] ?? $j ?? []) as $p) {
                if (($p['name'] ?? null) === $name && isset($p['version'])) {
                    return ltrim((string) $p['version'], 'v');
                }
            }
        }
        return null;
    }

    /**
     * Does a concrete version satisfy a Composer-style constraint? Supports `^`, `~`, comparators
     * (`>= > <= < = ==`), `*`, `x.*`, comma/space AND, and `||` OR — enough for bundle matching. Not
     * a full resolver (that's Composer's job, off-box); a genuine miss just means "no matching bundle".
     *
     * @param  string $version    a concrete version (e.g. "3.301.5")
     * @param  string $constraint e.g. "^3", "~3.2", ">=3.1 <4.0", "3.*"
     * @return bool
     */
    public static function satisfies($version, $constraint)
    {
        return self::_satisfies($version, $constraint);
    }

    protected static function _satisfies($version, $constraint)
    {
        $version = self::_normVersion($version);
        foreach (preg_split('/\s*\|\|\s*/', trim((string) $constraint)) as $group) {
            if ($group === '') {
                continue;
            }
            $all = true;
            foreach (preg_split('/\s*,\s*|\s+/', trim($group)) as $c) {
                if ($c !== '' && !self::_satisfiesOne($version, $c)) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return true;
            }
        }
        return false;
    }

    protected static function _satisfiesOne($version, $c)
    {
        if ($c === '' || $c === '*') {
            return true;
        }
        if (preg_match('/^([\^~])\s*(.+)$/', $c, $m)) {
            $parts = explode('.', self::_normVersion($m[2]));
            $lower = self::_pad($m[2]);
            $upper = ($m[1] === '^') ? self::_caretUpper($parts) : self::_tildeUpper($parts);
            return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
        }
        if (preg_match('/^(>=|<=|>|<|==|=)?\s*(.+)$/', $c, $m)) {
            $op  = ($m[1] === '' || $m[1] === '==') ? '=' : $m[1];
            $val = self::_normVersion($m[2]);
            if (strpos($val, '*') !== false) {              // 3.* / 3.2.*
                $prefix = rtrim(substr($val, 0, strpos($val, '*')), '.');
                return $prefix === '' || strpos($version, $prefix . '.') === 0 || $version === $prefix;
            }
            return version_compare($version, self::_pad($val), $op);
        }
        return false;
    }

    protected static function _normVersion($v)
    {
        return ltrim(trim((string) $v), 'vV');
    }

    /** Pad to 3 numeric segments so version_compare is consistent (3 → 3.0.0). */
    protected static function _pad($v)
    {
        $p = explode('.', preg_replace('/[^0-9.].*$/', '', self::_normVersion($v)));
        while (count($p) < 3) {
            $p[] = '0';
        }
        return implode('.', array_slice($p, 0, 3));
    }

    /** Caret upper bound: ^3→4.0.0, ^3.2→4.0.0, ^0.3→0.4.0, ^0.0.3→0.0.4. */
    protected static function _caretUpper(array $p)
    {
        $p = array_map('intval', array_pad($p, 3, 0));
        if ($p[0] > 0) { return ($p[0] + 1) . '.0.0'; }
        if ($p[1] > 0) { return '0.' . ($p[1] + 1) . '.0'; }
        return '0.0.' . ($p[2] + 1);
    }

    /** Tilde upper bound: ~3→4.0.0, ~3.2→4.0.0, ~3.2.1→3.3.0. */
    protected static function _tildeUpper(array $p)
    {
        $n = array_map('intval', array_pad($p, 3, 0));
        return (count($p) >= 3) ? $n[0] . '.' . ($n[1] + 1) . '.0' : ($n[0] + 1) . '.0.0';
    }

    protected static function _httpGet($url)
    {
        if (strncmp($url, 'file://', 7) === 0 || (isset($url[0]) && $url[0] === '/')) {
            $body = @file_get_contents($url);
            return $body === false ? null : $body;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_USERAGENT      => 'Tiger_Vendor',
            ]);
            $body = curl_exec($ch);
            return is_string($body) ? $body : null;
        }
        $body = @file_get_contents($url);
        return $body === false ? null : $body;
    }

    /**
     * Download a tarball → verify sha256 (if given) → unpack into the store → ensure an autoloader.
     * Atomic: stages in a temp dir and swaps into place, so a half-download never leaves a broken lib.
     *
     * @param  string      $url    the tarball URL (a bundle asset or a source archive)
     * @param  string      $name   the package name (→ the store subdir)
     * @param  string|null $sha256 expected hash; verified and enforced when provided
     * @param  array       $opts   {generate_autoload?:bool} build a PSR-4 autoloader for a raw lib
     * @return array{ok:bool,message:string,path?:string}
     */
    public static function installTarball($url, $name, $sha256 = null, array $opts = [])
    {
        $slug  = self::_slug($name);
        $store = Tiger_Vendor_Environment::storeDir();
        if (!is_dir($store) && !@mkdir($store, 0775, true) && !is_dir($store)) {
            return ['ok' => false, 'message' => 'Library store is not writable: ' . $store];
        }
        $tmp = $store . '/.tmp-' . $slug . '-' . getmypid();
        self::_rrmdir($tmp);
        if (!@mkdir($tmp, 0775, true)) {
            return ['ok' => false, 'message' => 'Could not create a temp dir in the store.'];
        }
        try {
            $tar = $tmp . '/pkg.tar.gz';
            if (!self::_download($url, $tar)) {
                return ['ok' => false, 'message' => 'Download failed: ' . $url];
            }
            if ($sha256 !== null && !hash_equals(strtolower((string) $sha256), strtolower((string) hash_file('sha256', $tar)))) {
                return ['ok' => false, 'message' => 'Checksum mismatch — refusing to install ' . $name . '.'];
            }
            $ex = $tmp . '/x';
            @mkdir($ex, 0775, true);
            if (!self::_extract($tar, $ex)) {
                return ['ok' => false, 'message' => 'Could not extract the archive (no PharData/tar).'];
            }
            // GitHub/source tarballs wrap everything in one top dir — unwrap it.
            $root = self::_singleChild($ex) ?: $ex;

            if (!empty($opts['generate_autoload']) && !is_file($root . '/autoload.php')) {
                self::_generateAutoloader($root);
            }
            $target = $store . '/' . $slug;
            self::_rrmdir($target);
            if (!@rename($root, $target)) {
                self::_rcopy($root, $target);
            }
            return ['ok' => true, 'message' => 'Installed.', 'path' => $target];
        } finally {
            self::_rrmdir($tmp);
        }
    }

    // ---- tiers -----------------------------------------------------------------

    protected static function _composerRequire($name, $constraint)
    {
        $bin = Tiger_Vendor_Environment::composerBinary();
        if ($bin === null || !function_exists('proc_open')) {
            return ['ok' => false];
        }
        $cmd  = $bin . ' require ' . escapeshellarg($name . ':' . $constraint) . ' --no-interaction --no-progress 2>&1';
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $desc, $pipes, Tiger_Vendor_Environment::appRoot(), self::_composerEnv());
        if (!is_resource($proc)) {
            return ['ok' => false];
        }
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['ok' => proc_close($proc) === 0, 'output' => $out];
    }

    /** Composer needs HOME/COMPOSER_HOME to write its cache — many web SAPIs have neither set. */
    protected static function _composerEnv()
    {
        $env  = $_ENV ?: [];
        $home = Tiger_Vendor_Environment::appRoot() . '/var/composer-home';
        @mkdir($home, 0775, true);
        $env['COMPOSER_HOME'] = $home;
        if (empty($env['HOME'])) { $env['HOME'] = $home; }
        return $env;
    }

    /**
     * Generate an autoload.php for a raw (Composer-less) library from its composer.json autoload
     * block — PSR-4 + files, which covers virtually every dependency-free modern lib (Tier 3). A
     * pre-built bundle (Tier 2) ships its own autoloader and never reaches here.
     */
    protected static function _generateAutoloader($dir)
    {
        $cj    = json_decode((string) @file_get_contents($dir . '/composer.json'), true);
        $auto  = (is_array($cj) && isset($cj['autoload']) && is_array($cj['autoload'])) ? $cj['autoload'] : [];
        $psr4  = isset($auto['psr-4']) && is_array($auto['psr-4']) ? $auto['psr-4'] : [];
        $files = isset($auto['files']) && is_array($auto['files']) ? $auto['files'] : [];

        $php  = "<?php\n";
        $php .= "// Auto-generated by Tiger_Vendor for a raw (Composer-less) library. See DEPENDENCIES.md.\n";
        $php .= '$base = __DIR__;' . "\n";
        foreach ($files as $f) {
            $php .= 'require_once $base . ' . var_export('/' . ltrim((string) $f, '/'), true) . ";\n";
        }
        $php .= 'spl_autoload_register(function ($class) use ($base) {' . "\n";
        $php .= '    static $psr4 = ' . var_export($psr4, true) . ";\n";
        $php .= '    foreach ($psr4 as $prefix => $paths) {' . "\n";
        $php .= '        if ($prefix !== "" && strncmp($class, $prefix, strlen($prefix)) !== 0) { continue; }' . "\n";
        $php .= '        $rel = str_replace("\\\\", "/", substr($class, strlen($prefix)));' . "\n";
        $php .= '        foreach ((array) $paths as $p) {' . "\n";
        $php .= '            $file = rtrim($base . "/" . trim((string) $p, "/"), "/") . "/" . $rel . ".php";' . "\n";
        $php .= '            if (is_file($file)) { require_once $file; return; }' . "\n";
        $php .= '        }' . "\n";
        $php .= '    }' . "\n";
        $php .= '});' . "\n";
        @file_put_contents($dir . '/autoload.php', $php);
    }

    // ---- io helpers ------------------------------------------------------------

    protected static function _download($url, $dest)
    {
        if (function_exists('curl_init')) {
            $fp = @fopen($dest, 'w');
            if (!$fp) { return false; }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 180,
                CURLOPT_FAILONERROR    => true,
                CURLOPT_USERAGENT      => 'Tiger_Vendor',
            ]);
            $ok = curl_exec($ch);
            fclose($fp);   // curl handle is freed by GC (curl_close is a deprecated no-op since 8.0)
            return $ok !== false && is_file($dest) && filesize($dest) > 0;
        }
        $data = @file_get_contents($url);
        return $data !== false && @file_put_contents($dest, $data) !== false;
    }

    protected static function _extract($tar, $into)
    {
        try {
            (new PharData($tar))->extractTo($into, null, true);
            return true;
        } catch (Throwable $e) {
            // fall through to shell tar
        }
        if (Tiger_Vendor_Environment::execEnabled() && function_exists('exec')) {
            $rc = 1;
            @exec('tar -xzf ' . escapeshellarg($tar) . ' -C ' . escapeshellarg($into) . ' 2>&1', $o, $rc);
            return $rc === 0;
        }
        return false;
    }

    protected static function _singleChild($dir)
    {
        $items = glob($dir . '/*') ?: [];
        return (count($items) === 1 && is_dir($items[0])) ? $items[0] : null;
    }

    protected static function _slug($name)
    {
        return trim(preg_replace('/[^a-z0-9._-]+/', '-', strtolower((string) $name)), '-');
    }

    protected static function _status($ok, $tier, $name, $message)
    {
        return ['ok' => (bool) $ok, 'tier' => $tier, 'name' => $name, 'message' => $message];
    }

    protected static function _rrmdir($dir)
    {
        if (!is_dir($dir)) { if (is_file($dir)) { @unlink($dir); } return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            is_dir($p) && !is_link($p) ? self::_rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    protected static function _rcopy($src, $dst)
    {
        @mkdir($dst, 0775, true);
        foreach (scandir($src) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $s = $src . '/' . $f;
            $d = $dst . '/' . $f;
            is_dir($s) ? self::_rcopy($s, $d) : @copy($s, $d);
        }
    }
}
