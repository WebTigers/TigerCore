<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Update_Composer — run `composer update <package>` IN-PROCESS, for hosts where Composer
 * genuinely runs (a binary + proc_open/exec not disabled + a writable vendor/ — see
 * Tiger_Vendor_Environment). This is the shell / VPS / dev-box counterpart to the no-shell vendored-
 * ZIP swap (Tiger_Update_Core): where Composer works, USE it; where it can't, the ZIP swap covers the
 * CMS user on shared hosting. Either way the Updates page APPLIES the update rather than merely
 * advising it.
 *
 * Never throws — returns {ok, version?, log:[{step,ok,detail}]}. The current PHP process keeps the
 * OLD classes it already loaded; the new code serves the NEXT request. So the version check re-reads
 * Version.php from DISK (not the loaded constant).
 *
 * @api
 */
class Tiger_Update_Composer
{
    const TIMEOUT = 600;   // composer update can be slow on a cold cache

    /** Can a composer-driven update run here? (a runnable binary + proc_open + writable vendor/). */
    public static function possible()
    {
        return function_exists('proc_open')
            && Tiger_Vendor_Environment::composerBinary() !== null
            && Tiger_Vendor_Environment::vendorWritable();
    }

    /**
     * Run `composer update <package> --with-all-dependencies` in the app root and verify the result.
     *
     * @param  array $opts {package: string, target?: string}
     * @return array {ok, version?, log}
     */
    public static function update(array $opts)
    {
        $log = [];
        $add = static function ($step, $ok, $detail) use (&$log) {
            $log[] = ['step' => $step, 'ok' => (bool) $ok, 'detail' => $detail];
            if (class_exists('Tiger_Log')) {
                Tiger_Log::info('update.composer', ['step' => $step, 'ok' => (bool) $ok, 'detail' => $detail]);
            }
        };
        $fail = static function ($detail) use (&$log, $add) {
            $add('error', false, $detail);
            return ['ok' => false, 'log' => $log];
        };

        $package = (string) ($opts['package'] ?? '');
        if ($package === '') { return $fail('No package to update.'); }

        $binary = Tiger_Vendor_Environment::composerBinary();
        if ($binary === null || !function_exists('proc_open')) {
            return $fail('Composer is not runnable here (no binary, or proc_open disabled).');
        }
        $root = Tiger_Vendor_Environment::appRoot();
        if (!is_file($root . '/composer.json')) {
            return $fail('No composer.json at ' . $root . ' — not a Composer-managed install.');
        }
        if (!Tiger_Vendor_Environment::vendorWritable()) {
            return $fail('vendor/ is not writable.');
        }
        $add('preflight', true, 'Composer runnable, composer.json present, vendor/ writable.');

        $pkgDir  = $root . '/vendor/' . $package;
        $verFile = $pkgDir . '/library/Tiger/Version.php';   // tiger-core layout
        $before  = self::_versionIn($verFile);

        // DEEP writability preflight. is_writable(vendor) above only checks the TOP dir; the real-world
        // failure (which white-screened a panel) is a NESTED dir the web user doesn't own — deleting a
        // file needs write on its CONTAINING dir, so Composer aborts part-way and leaves a half-installed
        // package. Catch it here and fail CLEAN, before anything is touched.
        $unwritable = self::_firstUnwritableDir($root . '/vendor');
        if ($unwritable !== null) {
            return $fail('Update aborted — nothing was changed. "' . $unwritable . '" is not writable by the '
                . 'web user (' . self::_procUser() . '), so Composer would fail part-way and break the install. '
                . 'Make the vendor tree writable by the web user (e.g. `chown -R <owner>:' . self::_procGroup()
                . ' vendor && find vendor -type d -exec chmod 2775 {} +`), then retry.');
        }

        // A writable HOME/COMPOSER_HOME (web users often have none), unbounded memory, no TTY.
        $composerHome = $root . '/var/composer-home';
        @mkdir($composerHome, 0775, true);
        @putenv('HOME=' . $composerHome);
        @putenv('COMPOSER_HOME=' . $composerHome);
        @putenv('COMPOSER_MEMORY_LIMIT=-1');
        @putenv('COMPOSER_NO_INTERACTION=1');
        @set_time_limit(0);
        // Managed hosting often has a vendor tree owned by a user other than the web user; git then
        // aborts with "dubious ownership" (Composer survives it, but it looks alarming in the operator's
        // log). Seed our HOME's gitconfig to trust any path so the update log stays clean.
        @file_put_contents($composerHome . '/.gitconfig', "[safe]\n\tdirectory = *\n");

        // ATOMIC: stage the current package ASIDE as a rollback point BEFORE Composer runs. This also
        // removes the exact failure mode above — with the old dir gone, Composer does a clean fresh
        // INSTALL into an empty slot (nothing to delete). Any failure below restores it, so the site is
        // never left half-updated.
        $backup = null;
        if (is_dir($pkgDir)) {
            $backup = $root . '/var/update-rollback-' . preg_replace('/[^a-z0-9]+/i', '-', $package) . '-' . time();
            @mkdir(dirname($backup), 0775, true);
            if (!@rename($pkgDir, $backup)) {
                return $fail('Update aborted — nothing was changed. Could not stage a rollback of ' . $package
                    . ' (rename failed); check that ' . $root . '/var is writable by the web user.');
            }
        }

        // --with-all-dependencies so a required tigerzf/polyfill bump comes along; --no-dev for a
        // production-shaped tree; --no-scripts so a post-update hook can't fail the update mid-request.
        $cmd = $binary . ' update ' . escapeshellarg($package)
             . ' --with-all-dependencies --no-dev --no-interaction --no-progress --no-scripts --no-ansi 2>&1';
        list($code, $out) = self::_run($cmd, $root, self::TIMEOUT);
        $tail = self::_tail($out, 4000);

        // Restore-on-failure: Composer errored, OR it "succeeded" but the package is missing/incomplete
        // (the half-extracted state). Either way, put the previous version back so the site stays up.
        if ($code !== 0 || !self::_packageIntact($pkgDir, $verFile)) {
            $restored = self::_restore($pkgDir, $backup);
            $why = ($code !== 0)
                ? "Composer exited with code {$code}."
                : 'Composer finished but ' . $package . ' is missing or incomplete on disk.';
            return $fail($why . ($restored ? ' Rolled back to the previous version — the site is unchanged.' : '')
                . ($tail !== '' ? "\n" . $tail : ''));
        }

        // Success — drop the rollback copy.
        if ($backup !== null) { self::_rmrf($backup); }
        $add('composer', true, 'composer update ' . $package . ' finished.' . ($tail !== '' ? "\n" . $tail : ''));

        // Re-read from disk — the running process still holds the old Version constant.
        $after = self::_versionIn($verFile);
        $add('done', true, ($after !== null
                ? 'Now at ' . $after . ($before !== null && $before !== $after ? ' (was ' . $before . ')' : '')
                : 'Composer reported success')
            . '. The new code serves the next request.');
        return ['ok' => true, 'version' => $after, 'log' => $log];
    }

    // ---- atomicity helpers -----------------------------------------------------

    /**
     * The first directory at/under $root that this process cannot write (so Composer could not delete a
     * file in it), or null if the whole tree is writable. Deletion needs write on the CONTAINING dir, so
     * we test dirs, not files. Symlinked dirs are skipped (not ours to chmod).
     */
    protected static function _firstUnwritableDir($root)
    {
        if (!is_dir($root)) { return null; }
        if (!is_writable($root)) { return $root; }
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $path) {
                if ($path->isDir() && !$path->isLink() && !is_writable((string) $path)) {
                    return (string) $path;
                }
            }
        } catch (Exception $e) {
            return null;   // never let the check itself block an update
        }
        return null;
    }

    /** Whether the package is fully installed on disk (dir + composer.json; +Version/functions for tiger-core). */
    protected static function _packageIntact($pkgDir, $verFile)
    {
        if (!is_dir($pkgDir) || !is_file($pkgDir . '/composer.json')) { return false; }
        if (basename($pkgDir) === 'tiger-core') {          // the classic half-extracted fatal is a missing one of these
            return is_file($verFile) && is_file($pkgDir . '/functions.php');
        }
        return true;
    }

    /** Restore the staged rollback over a (possibly partial) package dir. Returns whether it was restored. */
    protected static function _restore($pkgDir, $backup)
    {
        if ($backup === null || !is_dir($backup)) { return false; }
        self::_rmrf($pkgDir);
        return @rename($backup, $pkgDir);
    }

    /** Recursively remove a path. */
    protected static function _rmrf($path)
    {
        if ($path === '' || !file_exists($path)) { return; }
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $p) { ($p->isDir() && !$p->isLink()) ? @rmdir((string) $p) : @unlink((string) $p); }
        } catch (Exception $e) { /* best effort */ }
        @rmdir($path);
    }

    protected static function _procUser()
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $u = @posix_getpwuid(@posix_geteuid());
            if (is_array($u) && !empty($u['name'])) { return $u['name']; }
        }
        return get_current_user() ?: 'the web user';
    }

    protected static function _procGroup()
    {
        if (function_exists('posix_getegid') && function_exists('posix_getgrgid')) {
            $g = @posix_getgrgid(@posix_getegid());
            if (is_array($g) && !empty($g['name'])) { return $g['name']; }
        }
        return 'apache';
    }

    // ---- helpers ---------------------------------------------------------------

    /** Run a command, capturing merged output, with a wall-clock timeout. Returns [exitCode, output]. */
    protected static function _run($cmd, $cwd, $timeout)
    {
        $desc = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $desc, $pipes, $cwd, null);
        if (!is_resource($proc)) { return [1, 'Could not start Composer (proc_open failed).']; }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $out    = '';
        $start  = time();
        $status = ['running' => true, 'exitcode' => -1];
        while (true) {
            $out   .= (string) stream_get_contents($pipes[1]);
            $out   .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (empty($status['running'])) { break; }
            if (time() - $start > $timeout) { @proc_terminate($proc); $out .= "\n[timed out after {$timeout}s]"; break; }
            usleep(200000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        $out .= (string) stream_get_contents($pipes[2]);
        @fclose($pipes[1]);
        @fclose($pipes[2]);
        $closeCode = proc_close($proc);
        $code = (isset($status['exitcode']) && $status['exitcode'] >= 0) ? $status['exitcode'] : $closeCode;
        return [$code, $out];
    }

    protected static function _versionIn($file)
    {
        if (!is_file($file)) { return null; }
        return preg_match('/VERSION\s*=\s*[\'"]([^\'"]+)[\'"]/', (string) @file_get_contents($file), $m) ? $m[1] : null;
    }

    protected static function _tail($s, $max)
    {
        $s = trim((string) $s);
        return strlen($s) > $max ? '…' . substr($s, -$max) : $s;
    }
}
