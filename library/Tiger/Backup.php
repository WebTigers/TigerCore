<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Backup — create and restore site backups (a downloadable/portable zip).
 *
 * A backup is a single `TigerBackup-YYYY-MM-DD-HH-MM.zip` holding any of four components — the
 * **database** (a portable shell-free SQL dump), **media** (uploaded files), **modules** (your app
 * modules), and **platform** (the rest of your app code + config) — plus a `manifest.json`. It's
 * stored on a disk (the local server, or any configured cloud media disk — S3/GCS/Azure, reusing the
 * media adapters, no extra SDK) and cataloged in the `backup` table so listing, rolling retention,
 * download, and restore never need a remote directory listing.
 *
 * Design notes:
 * - **Metadata in the DB, bytes on a disk** (the Tiger media pattern) — the row knows where its zip
 *   lives, so a cloud backup is located by a DB read, not a `list()`.
 * - **Retention** prunes the oldest *scheduled* backups past a max; *manual* backups are pinned and
 *   never auto-removed ("manual remove only").
 * - **Restore is destructive and guarded** — it runs under the maintenance flag, takes an automatic
 *   pre-restore safety backup, imports the DB first (before touching files), then extracts files
 *   into place. The safety backup is the recovery path if anything fails mid-restore.
 *
 * @api
 */
class Tiger_Backup
{
    const DATABASE = 'database';
    const MEDIA    = 'media';
    const MODULES  = 'modules';
    const PLATFORM = 'platform';

    const COMPONENTS = [self::DATABASE, self::MEDIA, self::MODULES, self::PLATFORM];

    /** Paths (relative to the app root) never included in any backup. */
    const ALWAYS_EXCLUDE = ['vendor', 'var', '.git', 'node_modules', 'storage/backups', 'public/_theme'];

    // ---------------------------------------------------------------- create

    /**
     * Create a backup archive.
     *
     * @param  array $components  any of Tiger_Backup::COMPONENTS
     * @param  array $opts        disk, source (manual|scheduled), include_secrets (bool), notify (bool)
     * @return array ['status' => ok|error, 'backup_id' => ?, 'filename' => ?, 'size' => ?, 'error' => ?]
     */
    public static function create(array $components, array $opts = [])
    {
        $components = array_values(array_intersect(self::COMPONENTS, $components));
        if (!$components) { return ['status' => 'error', 'error' => 'No components selected.']; }

        $disk    = (string) ($opts['disk'] ?? 'local');
        $source  = ($opts['source'] ?? 'manual') === 'scheduled' ? 'scheduled' : 'manual';
        $secrets = array_key_exists('include_secrets', $opts) ? (bool) $opts['include_secrets'] : true;

        $filename = self::_archiveName();
        $model    = new Tiger_Model_Backup();
        $id       = $model->begin($filename, $disk, $components, $source);
        $start    = microtime(true);

        $staging = self::_stagingDir();
        $zipPath = $staging . '/' . $filename;
        $sqlPath = $staging . '/database.sql';

        try {
            $manifest = [
                'tiger_version' => class_exists('Tiger_Version') ? Tiger_Version::VERSION : null,
                'php_version'   => PHP_VERSION,
                'created_at'    => date('c'),
                'hostname'      => $_SERVER['HTTP_HOST'] ?? gethostname(),
                'components'    => $components,
                'include_secrets' => $secrets,
                'source'        => $source,
            ];

            if (in_array(self::DATABASE, $components, true)) {
                $db = Tiger_Backup_Database::dump($sqlPath);
                $manifest['database'] = ['tables' => $db['tables'], 'rows' => $db['rows']];
            }

            $files = self::_collectFiles($components, $secrets);
            $manifest['files'] = ['count' => count($files)];

            self::_buildZip($zipPath, $sqlPath, $files, $manifest, in_array(self::DATABASE, $components, true));
            @unlink($sqlPath);

            $checksum = hash_file('sha256', $zipPath);
            $size     = (int) filesize($zipPath);
            $key      = self::_store($disk, $filename, $zipPath);
            @unlink($zipPath);

            $model->finish($id, 'ok', [
                'storage_key' => $key,
                'size_bytes'  => $size,
                'checksum'    => $checksum,
                'manifest'    => json_encode($manifest, JSON_UNESCAPED_SLASHES),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ]);

            if ($source === 'scheduled') { self::prune(); }
            self::_notify(true, $filename, ['size' => $size, 'components' => $components], $opts);

            return ['status' => 'ok', 'backup_id' => $id, 'filename' => $filename, 'size' => $size];
        } catch (Throwable $e) {
            @unlink($sqlPath); @unlink($zipPath);
            $model->finish($id, 'error', ['error' => substr($e->getMessage(), 0, 1000)]);
            Tiger_Log::error('backup.failed', ['id' => $id, 'error' => $e->getMessage()]);
            self::_notify(false, $filename, ['error' => $e->getMessage()], $opts);
            return ['status' => 'error', 'backup_id' => $id, 'error' => $e->getMessage()];
        }
    }

    /**
     * Run a scheduled backup from the admin-configured settings (the Tiger_Schedule job target).
     *
     * @return void
     */
    public static function runScheduled()
    {
        if (!self::_cfgBool('tiger.backup.schedule.enabled', false)) { return; }
        $components = array_filter(array_map('trim', explode(',', self::_cfg('tiger.backup.components', 'database,media'))));
        self::create($components, [
            'disk'            => self::_cfg('tiger.backup.disk', 'local'),
            'source'          => 'scheduled',
            'include_secrets' => self::_cfgBool('tiger.backup.include_secrets', true),
            'notify'          => self::_cfgBool('tiger.backup.notify.enabled', false),
        ]);
    }

    // ---------------------------------------------------------------- retention

    /**
     * Prune the oldest scheduled backups beyond the configured max (manual/pinned are never pruned).
     *
     * @return int number of backups removed
     */
    public static function prune()
    {
        $max = (int) self::_cfg('tiger.backup.retention.max', '7');
        if ($max <= 0) { return 0; }
        $model = new Tiger_Model_Backup();
        $rows  = $model->prunable();               // oldest first
        $excess = count($rows) - $max;
        $removed = 0;
        for ($i = 0; $i < $excess; $i++) {
            if (self::delete($rows[$i]['backup_id'])) { $removed++; }
        }
        return $removed;
    }

    /**
     * Delete a backup (its archive bytes + soft-delete the catalog row).
     *
     * @param  string $backupId
     * @return bool
     */
    public static function delete($backupId)
    {
        $model = new Tiger_Model_Backup();
        $row   = $model->findById($backupId);
        if (!$row) { return false; }
        try {
            if (!empty($row['storage_key'])) { self::_remove($row['disk'], $row['storage_key']); }
        } catch (Throwable $e) {
            Tiger_Log::warn('backup.delete.storage', ['id' => $backupId, 'error' => $e->getMessage()]);
        }
        $model->softDelete($model->getAdapter()->quoteInto('backup_id = ?', $backupId));
        return true;
    }

    // ---------------------------------------------------------------- restore

    /**
     * Restore from a backup archive. DESTRUCTIVE — runs under the maintenance flag, takes an automatic
     * pre-restore safety backup, imports the DB first, then extracts files into place.
     *
     * @param  string $zipPath     a local path to the archive (streamed from storage, or an upload)
     * @param  array  $components   which components to restore (default: all present in the archive)
     * @param  array  $opts         safety (bool, default true)
     * @return array  ['status' => ok|error, 'restored' => [...], 'safety_id' => ?, 'error' => ?]
     */
    public static function restore($zipPath, array $components = [], array $opts = [])
    {
        if (!is_file($zipPath)) { return ['status' => 'error', 'error' => 'Archive not found.']; }

        $manifestJson = Tiger_Backup_Archive::read($zipPath, 'manifest.json');
        if ($manifestJson === false) { return ['status' => 'error', 'error' => 'Not a TigerBackup archive (no manifest).']; }
        $manifest = json_decode((string) $manifestJson, true) ?: [];
        $present  = $manifest['components'] ?? self::COMPONENTS;
        $want     = $components ? array_values(array_intersect($present, $components)) : array_values($present);
        if (!$want) { return ['status' => 'error', 'error' => 'Nothing to restore.']; }

        $flag    = self::_maintenanceFlag();
        @is_dir(dirname($flag)) || @mkdir(dirname($flag), 0775, true);
        @file_put_contents($flag, (string) time());

        $restored = []; $safetyId = null;
        try {
            // Pre-restore safety net: a local backup of the same components we're about to overwrite.
            if (($opts['safety'] ?? true)) {
                $safety = static::create($want, ['disk' => 'local', 'source' => 'manual', 'notify' => false]);
                // create() returns a backup_id on its ERROR path too (the catalog row is opened before
                // the archive is built), so a non-null id proved nothing. Restore used to read only the
                // id and carry on — destroying the installation with no recovery point behind it.
                // The safety net either exists or the destructive work does not start.
                if (($safety['status'] ?? '') !== 'ok') {
                    @unlink($flag);
                    Tiger_Log::error('backup.restore.safety_failed', [
                        'error' => $safety['error'] ?? 'safety backup did not complete',
                    ]);
                    return ['status' => 'error', 'safety_id' => $safety['backup_id'] ?? null,
                            'error'  => 'Safety backup failed (' . ($safety['error'] ?? 'unknown error')
                                      . ') — refusing to restore without a recovery point. '
                                      . 'Fix the backup destination, or re-run with safety disabled if you accept the risk.'];
                }
                $safetyId = $safety['backup_id'];
            }

            $stage = self::_stagingDir() . '/restore-' . bin2hex(random_bytes(4));
            @mkdir($stage, 0775, true);
            Tiger_Backup_Archive::extract($zipPath, $stage);

            // DB first — if it fails, no files have been touched yet.
            if (in_array(self::DATABASE, $want, true) && is_file($stage . '/database.sql')) {
                Tiger_Backup_Database::import($stage . '/database.sql');
                $restored[] = self::DATABASE;
            }

            // Then files — ONLY the components that were asked for. This used to copy the archive's
            // whole files/ tree whenever any file component was selected, so a media-only restore
            // from a mixed archive overwrote application code and configuration. Those components
            // were also absent from the safety backup (which covers $want), so the blast radius was
            // wider than the recovery. Scoping the copy makes safety coverage equal to what can be
            // overwritten, by construction.
            $fileComps = array_intersect([self::MEDIA, self::MODULES, self::PLATFORM], $want);
            if ($fileComps && is_dir($stage . '/files')) {
                $paths   = self::_componentPaths($fileComps, true);
                $failed  = [];
                $copied  = self::_copyTree($stage . '/files', self::_root(), $paths, '', $failed);
                if ($failed) {
                    // Unchecked mkdir/copy meant a permissions or disk failure produced a PARTIALLY
                    // restored installation reported as success. Report it instead.
                    throw new RuntimeException(sprintf(
                        '%d file(s) could not be restored (first: %s) — the installation is partially restored; '
                        . 'the safety backup %s holds the previous state.',
                        count($failed), $failed[0], $safetyId ?: '(none)'
                    ));
                }
                Tiger_Log::info('backup.restore.files', ['copied' => $copied, 'components' => array_values($fileComps)]);
                foreach ($fileComps as $c) { $restored[] = $c; }
            }

            // Published assets are excluded from the archive (see _walk), so a copy-mode install
            // would come back with no CSS/JS. Re-publish them — a no-op on a symlinked install, and
            // fail-soft: a restored site must not be reported as failed over re-creatable files.
            if ($fileComps) {
                try {
                    $assets = Tiger_Install::republishAssets(self::_root());
                    if ($assets['error'] !== null) {
                        Tiger_Log::warn('backup.restore.assets', ['error' => $assets['error']]);
                    }
                } catch (Throwable $e) {
                    Tiger_Log::warn('backup.restore.assets', ['error' => $e->getMessage()]);
                }
            }

            self::_rrmdir($stage);
            @unlink($flag);
            Tiger_Log::info('backup.restored', ['components' => $restored, 'safety_id' => $safetyId]);
            return ['status' => 'ok', 'restored' => array_values(array_unique($restored)), 'safety_id' => $safetyId];
        } catch (Throwable $e) {
            @unlink($flag);
            Tiger_Log::error('backup.restore.failed', ['error' => $e->getMessage(), 'safety_id' => $safetyId]);
            return ['status' => 'error', 'error' => $e->getMessage(), 'safety_id' => $safetyId];
        }
    }

    /**
     * Stream a stored backup's bytes to a local temp file (for download or restore).
     *
     * @param  array $row a backup row
     * @return string the local temp path (caller unlinks)
     * @throws RuntimeException when the archive can't be located
     */
    public static function fetchToTemp(array $row)
    {
        $disk = (string) $row['disk'];
        $key  = (string) $row['storage_key'];
        if ($disk === 'local') {
            $path = self::_localDir() . '/' . $key;
            if (!is_file($path)) { throw new RuntimeException('Backup file missing on disk.'); }
            return $path;   // already local — return in place
        }
        $tmp = self::_stagingDir() . '/' . basename($row['filename']);
        $in  = Tiger_Media_Storage::disk($disk)->stream($key, 'private');
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out); @fclose($in);
        return $tmp;
    }

    // ---------------------------------------------------------------- destinations

    /**
     * Available backup destinations: the local server plus any configured cloud media disks.
     *
     * @return array list of ['name' => ..., 'label' => ...]
     */
    public static function disks()
    {
        $out = [['name' => 'local', 'label' => 'Local (this server)']];
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $media = $cfg ? $cfg->get('media') : null;
        $disks = $media ? $media->get('disks') : null;
        if ($disks instanceof Zend_Config) {
            foreach ($disks as $name => $conf) {
                $adapter = strtolower((string) $conf->get('adapter'));
                if ($adapter === '' || $adapter === 'filesystem' || $adapter === 'local') { continue; }
                $out[] = ['name' => (string) $name, 'label' => strtoupper($adapter) . ' — ' . $name];
            }
        }
        return $out;
    }

    // ---------------------------------------------------------------- internals

    /** Gather the absolute→archive-relative file map for the selected file components. */
    /**
     * The ROOT-RELATIVE paths a set of components owns, and the paths that must be kept out of them.
     *
     * ONE authority, deliberately: create() used to derive this and restore() had no equivalent at
     * all, which is exactly how a media-only restore came to overwrite application code. Both sides
     * now read the same answer, so they cannot drift.
     *
     * @param  array $components component constants
     * @param  bool  $secrets    include application/configs/local.ini
     * @return array{include:string[],exclude:string[]} root-relative paths
     */
    /**
     * A unique, immutable archive name.
     *
     * Minute resolution meant two backups in the same minute shared one filename AND one storage key,
     * so distinct catalog rows pointed at a single overwritten archive. Worse on restore: the automatic
     * safety backup could land on the very archive being restored and replace it before extraction —
     * you would restore the safety snapshot instead of the archive you asked for. Second resolution
     * plus a random suffix makes every archive distinct and immutable.
     *
     * @return string
     */
    protected static function _archiveName()
    {
        return 'TigerBackup-' . date('Y-m-d-H-i-s') . '-' . bin2hex(random_bytes(3)) . '.zip';
    }

    protected static function _componentPaths(array $components, $secrets = true)
    {
        $root = self::_root();
        $rel  = static function ($abs) use ($root) { return ltrim(substr($abs, strlen($root)), '/'); };

        $include = [];
        if (in_array(self::MEDIA, $components, true)) {
            foreach (self::_mediaRoots() as $r) { $include[] = $rel($r); }
        }
        if (in_array(self::MODULES, $components, true)) {
            $include[] = 'application/modules';
        }
        if (in_array(self::PLATFORM, $components, true)) {
            foreach (['application', 'public', 'composer.json', 'composer.lock'] as $p) { $include[] = $p; }
        }

        $exclude = self::ALWAYS_EXCLUDE;
        // PLATFORM pulls all of public/ + application/, which contain the media roots. Media is its
        // own component, so keep it out unless it was asked for.
        if (!in_array(self::MEDIA, $components, true)) {
            foreach (self::_mediaRoots() as $r) { $exclude[] = $rel($r); }
        }
        if (!$secrets) { $exclude[] = 'application/configs/local.ini'; }

        return ['include' => array_values(array_unique(array_filter($include))),
                'exclude' => array_values(array_unique(array_filter($exclude)))];
    }

    /** Does a root-relative path sit under any of these root-relative prefixes? */
    protected static function _underAny($rel, array $prefixes)
    {
        $rel = ltrim((string) $rel, '/');
        foreach ($prefixes as $p) {
            $p = ltrim((string) $p, '/');
            if ($p === '') { continue; }
            if ($rel === $p || strpos($rel, $p . '/') === 0) { return true; }
        }
        return false;
    }

    protected static function _collectFiles(array $components, $secrets)
    {
        $root  = self::_root();
        $roots = [];
        if (in_array(self::MEDIA, $components, true)) {
            foreach (self::_mediaRoots() as $r) { $roots[$r] = true; }
        }
        if (in_array(self::MODULES, $components, true)) {
            $roots[$root . '/application/modules'] = true;
        }
        if (in_array(self::PLATFORM, $components, true)) {
            foreach (['application', 'public', 'composer.json', 'composer.lock'] as $p) {
                $roots[$root . '/' . $p] = true;
            }
        }

        // When PLATFORM is included it pulls all of application/ (which contains modules/ and
        // configs/); the exclude predicate keeps media out. Nothing more to special-case.
        $excludeAbs = array_map(fn($p) => $root . '/' . $p, self::ALWAYS_EXCLUDE);
        // Media lives under public/_media + storage/media; keep them out of PLATFORM (media is its
        // own component) unless MEDIA is selected.
        if (!in_array(self::MEDIA, $components, true)) {
            foreach (self::_mediaRoots() as $r) { $excludeAbs[] = $r; }
        }
        if (!$secrets) { $excludeAbs[] = $root . '/application/configs/local.ini'; }

        $files = [];
        foreach (array_keys($roots) as $srcRoot) {
            if (is_file($srcRoot)) {
                $rel = ltrim(substr($srcRoot, strlen($root)), '/');
                $files['files/' . $rel] = $srcRoot;
                continue;
            }
            if (!is_dir($srcRoot)) { continue; }
            self::_walk($srcRoot, $root, $excludeAbs, $files);
        }
        return $files;
    }

    /** Recursively map files under $dir into $files[archiveKey] = absPath, honoring excludes. */
    protected static function _walk($dir, $root, array $excludeAbs, array &$files)
    {
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $abs = $dir . '/' . $f;
            if (is_link($abs)) { continue; }                 // never follow symlinks (e.g. public/_theme)
            // Published assets are DERIVED from vendor/ and are re-creatable with `tiger link:assets`.
            // A symlinked install skips them via the check above; on a host without symlink() they are
            // real directories, and without this they would bloat every backup with a copy of the
            // theme + framework assets. Excluded by their marker, so a user's own dir is untouched.
            if (is_dir($abs) && is_file($abs . '/' . Tiger_Install::ASSET_COPY_MARKER)) { continue; }
            foreach ($excludeAbs as $ex) {
                if ($abs === $ex || strpos($abs, $ex . '/') === 0) { continue 2; }
            }
            if (is_dir($abs)) { self::_walk($abs, $root, $excludeAbs, $files); continue; }
            if ($f === '.DS_Store') { continue; }
            $rel = ltrim(substr($abs, strlen($root)), '/');
            $files['files/' . $rel] = $abs;
        }
    }

    /** Build the zip: manifest + (optional) database.sql + the file map. */
    protected static function _buildZip($zipPath, $sqlPath, array $files, array $manifest, $withDb)
    {
        $entries = [['name' => 'manifest.json', 'data' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]];
        if ($withDb && is_file($sqlPath)) { $entries[] = ['name' => 'database.sql', 'file' => $sqlPath]; }
        foreach ($files as $archiveName => $abs) { $entries[] = ['name' => $archiveName, 'file' => $abs]; }
        Tiger_Backup_Archive::build($zipPath, $entries);
    }

    /** Store the staged zip on a disk; returns the storage key. */
    protected static function _store($disk, $filename, $zipPath)
    {
        if ($disk === 'local') {
            $dir = self::_localDir();
            @is_dir($dir) || @mkdir($dir, 0775, true);
            self::_protectDir($dir);
            if (!@copy($zipPath, $dir . '/' . $filename)) { throw new RuntimeException('Cannot write local backup.'); }
            return $filename;
        }
        $key = 'backups/' . $filename;
        Tiger_Media_Storage::disk($disk)->put($key, $zipPath, 'private', 'application/zip');
        return $key;
    }

    /** Remove a stored archive. */
    protected static function _remove($disk, $key)
    {
        if ($disk === 'local') { @unlink(self::_localDir() . '/' . $key); return; }
        Tiger_Media_Storage::disk($disk)->delete($key, 'private');
    }

    /** Copy a directory tree $src/* into $dst (overwriting). */
    /**
     * Copy a staged tree onto the live root — scoped to the selected components, and CHECKED.
     *
     * Both properties were missing. It copied everything it was handed (TIGER-84) and suppressed every
     * mkdir/copy result (TIGER-85), so a partial restore silently overwrote unselected components and
     * a failed write was reported as success.
     *
     * @param  string $src    staged dir
     * @param  string $dst    live destination
     * @param  array  $paths  ['include'=>[], 'exclude'=>[]] root-relative, from _componentPaths()
     * @param  string $rel    root-relative path of $src (recursion state)
     * @param  array  $failed collects paths that could not be written
     * @return int    files copied
     */
    protected static function _copyTree($src, $dst, array $paths = [], $rel = '', array &$failed = [])
    {
        if (!is_dir($src)) { return 0; }
        $include = $paths['include'] ?? [];
        $exclude = $paths['exclude'] ?? [];
        $copied  = 0;

        foreach (scandir($src) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $s      = $src . '/' . $f;
            $d      = $dst . '/' . $f;
            $childR = $rel === '' ? $f : $rel . '/' . $f;

            if ($exclude && self::_underAny($childR, $exclude)) { continue; }
            // Descend into a dir that either sits inside a selected component or is a prefix of one
            // (application/ on the way to application/modules); only FILES are gated on inclusion.
            if (is_dir($s)) {
                if ($include && !self::_underAny($childR, $include) && !self::_leadsTo($childR, $include)) { continue; }
                if (!is_dir($d) && !@mkdir($d, 0775, true) && !is_dir($d)) { $failed[] = $childR; continue; }
                $copied += self::_copyTree($s, $d, $paths, $childR, $failed);
                continue;
            }
            if ($include && !self::_underAny($childR, $include)) { continue; }
            if (!@copy($s, $d)) { $failed[] = $childR; continue; }
            $copied++;
        }
        return $copied;
    }

    /** Is this dir an ancestor of any selected path? (application/ leads to application/modules) */
    protected static function _leadsTo($rel, array $prefixes)
    {
        $rel = ltrim((string) $rel, '/');
        foreach ($prefixes as $p) {
            if (strpos(ltrim((string) $p, '/'), $rel . '/') === 0) { return true; }
        }
        return false;
    }

    protected static function _mediaRoots()
    {
        $root = self::_root();
        $out  = [];
        $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $local = $cfg ? $cfg->get('media') : null;
        $disks = $local ? $local->get('disks') : null;
        $conf  = $disks ? $disks->get('local') : null;
        $pub   = $conf ? (string) $conf->get('public_root') : 'public/_media';
        $priv  = $conf ? (string) $conf->get('private_root') : 'storage/media';
        foreach ([$pub, $priv] as $r) {
            $abs = $r !== '' && $r[0] === '/' ? $r : $root . '/' . $r;
            if (is_dir($abs)) { $out[] = rtrim($abs, '/'); }
        }
        return $out;
    }

    protected static function _root()
    {
        return defined('APPLICATION_ROOT') ? APPLICATION_ROOT : dirname(APPLICATION_PATH);
    }

    protected static function _localDir()      { return self::_root() . '/storage/backups'; }
    protected static function _stagingDir()
    {
        $d = self::_root() . '/var/backup';
        @is_dir($d) || @mkdir($d, 0775, true);
        return $d;
    }
    protected static function _maintenanceFlag()
    {
        return class_exists('Tiger_Update_Core') ? Tiger_Update_Core::maintenanceFlag() : self::_root() . '/var/update/.maintenance';
    }

    /** Drop a deny-all .htaccess in the local backup dir (defense-in-depth; it's outside docroot too). */
    protected static function _protectDir($dir)
    {
        $ht = $dir . '/.htaccess';
        if (!is_file($ht)) { @file_put_contents($ht, "Require all denied\nDeny from all\n"); }
    }

    protected static function _cfg($key, $default = '')
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        if (!$cfg) { return $default; }
        $node = $cfg;
        foreach (explode('.', $key) as $seg) {
            $node = $node instanceof Zend_Config ? $node->get($seg) : null;
            if ($node === null) { return $default; }
        }
        return is_scalar($node) ? (string) $node : $default;
    }

    protected static function _cfgBool($key, $default = false)
    {
        $v = self::_cfg($key, $default ? '1' : '0');
        return $v === '1' || $v === 'true' || $v === 'on';
    }

    protected static function _notify($ok, $filename, array $data, array $opts)
    {
        $enabled = array_key_exists('notify', $opts) ? (bool) $opts['notify'] : self::_cfgBool('tiger.backup.notify.enabled', false);
        if (!$enabled) { return; }
        $to = array_filter(array_map('trim', explode(',', self::_cfg('tiger.backup.notify.email', ''))));
        if (!$to) { return; }
        try {
            $host = $_SERVER['HTTP_HOST'] ?? gethostname();
            $subject = ($ok ? '✅ Backup succeeded' : '⚠️ Backup FAILED') . ' — ' . $host;
            $mail = new Tiger_Mail();
            foreach ($to as $addr) { $mail->to($addr); }
            $mail->subject($subject)
                 ->template('backup', [
                     'ok'         => $ok,
                     'filename'   => $filename,
                     'size'       => self::hsize((int) ($data['size'] ?? 0)),
                     'components' => implode(', ', $data['components'] ?? []),
                     'error'      => (string) ($data['error'] ?? ''),
                 ])
                 ->send();
        } catch (Throwable $e) {
            Tiger_Log::warn('backup.notify.failed', ['error' => $e->getMessage()]);
        }
    }

    /** Human-readable byte size. */
    public static function hsize($bytes)
    {
        $bytes = (int) $bytes;
        return $bytes >= 1073741824 ? round($bytes / 1073741824, 2) . ' GB'
            : ($bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB'
            : ($bytes >= 1024 ? round($bytes / 1024) . ' KB' : $bytes . ' B'));
    }

    protected static function _rrmdir($dir)
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            (is_dir($p) && !is_link($p)) ? self::_rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
