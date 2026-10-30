<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Theme_Remote — fetch a theme's content pages from a single remote source of truth.
 *
 * The problem this solves: a theme's content pages ship *inside* the theme (and, for a
 * release-managed site theme, inside a machine image), so a copy fix can only land by rebuilding
 * that thing — a theme release or an AMI bake. A marketing page is content, not presentation, so a
 * typo fix should not need either, and a fleet of nodes should not be able to sit on different
 * versions of the same page while every one of them returns 200.
 *
 * So the content lives at ONE path (an S3 prefix, optionally behind a CDN) and every node fetches
 * from it into its own `var/theme-remote`, which {@see Tiger_Theme::contentFile()} then prefers over
 * the theme's baked `content/` dir. There is no versioning at the source, no per-node build step,
 * and no coordination between nodes: each independently mirrors the same prefix.
 *
 * This is the theme-content sibling of Docs_Model_Remote, and the fetch mechanics are identical —
 * a manifest with per-file sha256, a diff-only download, atomic install, prune, manifest-last. The
 * ONE deliberate difference is {@see self::ALLOWED_EXT}: a theme content page is a **`.phtml`**, and
 * that is allowed here precisely because, unlike a TigerDocs doc, it is never rendered as code —
 * `Tiger_Theme::contentFile()` returns the path, `PageController::themeContentAction` reads it,
 * strips the `tiger:page` hint and hands the BODY to `page/view.phtml`, which `echo`s it verbatim.
 * A theme `.phtml` is static-HTML data plus an SEO/layout hint, so the extension is irrelevant to
 * safety — the body is never `include`/`eval`'d. Raw `.php` is still refused: nothing a theme serves
 * as content is a script.
 *
 * How a fetch works — one HTTP request when nothing has changed:
 *   1. GET `<base>/index.json` — the manifest: every content file with its sha256 and size.
 *   2. Compare it against the manifest the last successful fetch left behind.
 *   3. GET only the files whose hash differs (in parallel), verify each against the manifest,
 *      write it to a temp file and rename it into place — so a reader never sees a partial file.
 *   4. Remove local files the manifest no longer lists, then write the manifest last: an
 *      interrupted fetch leaves the old manifest, so the next run simply redoes the work.
 *
 * Fetching is NEVER done on a page render — it is network I/O, and a page must not wait on it. The
 * triggers are the scheduled job (`theme.content_fetch`, registered in Tiger_Application_Bootstrap),
 * the admin button, and a deploy hook. Until the first fetch lands, `contentFile()` falls through to
 * the theme's baked `content/`, so a cold node is never blank.
 *
 * @api
 */
class Tiger_Theme_Remote
{
    /** The published index of the source, relative to the base URL. */
    const MANIFEST = 'index.json';

    /** The manifest the last successful fetch left in the mirror (hidden from any content scan). */
    const LOCAL_MANIFEST = '.manifest.json';

    /**
     * Extensions a fetch will install. INCLUDES `phtml`/`html` because a theme content page is a
     * static-HTML body that is echoed verbatim, never executed (see the class docblock) — the
     * opposite of Docs_Model_Remote, which bans `phtml` because TigerDocs renders a doc as code.
     * `php` is still refused: nothing a theme serves as content is a script.
     */
    const ALLOWED_EXT = ['phtml', 'html', 'htm', 'md', 'markdown', 'txt', 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'];

    /** Caps, so a hostile or broken manifest cannot fill the disk. */
    const MAX_FILE  = 4194304;      // 4 MB per file
    const MAX_TOTAL = 268435456;    // 256 MB per fetch
    const MAX_FILES = 20000;

    /** Parallel downloads per round — enough to make a cold fetch quick, few enough to be polite. */
    const CONCURRENCY = 12;

    /** @var array<string,array>|null memo of the last-read local manifest */
    protected $_local;

    // =============================================================================================
    //  Where it reads from, and writes to
    // =============================================================================================

    /**
     * The base URL of the content source, without a trailing slash, or '' when unconfigured.
     *
     * Unset means the remote tier is simply absent — every existing install keeps resolving theme
     * content exactly as before (from the theme's baked `content/`), so turning this on is a
     * deliberate act rather than a surprise in an update.
     *
     * @return string the base URL, or '' when no remote source is configured
     */
    public function baseUrl()
    {
        $url = trim((string) $this->_cfg('content_url', ''));
        if ($url === '') { return ''; }

        // https only. A content tree fetched over plain http can be rewritten in flight, and the
        // files it carries are rendered into every public page of the site.
        if (stripos($url, 'https://') !== 0) { return ''; }

        return rtrim($url, '/');
    }

    /** Is a remote content source configured? @return bool */
    public function isEnabled()
    {
        return $this->baseUrl() !== '';
    }

    /**
     * The local mirror directory — an app-root build area, never committed, safe to delete.
     *
     * Sits in `var/` for the same reason the docs mirror does: it is a regenerable artifact of this
     * one server, and on a cPanel account `var/` is the dependable writable path.
     *
     * @return string the absolute mirror path
     */
    public function dir()
    {
        $dir = trim((string) $this->_cfg('content_mirror', ''));
        if ($dir !== '') { return rtrim($dir, '/'); }

        $base = defined('APPLICATION_PATH') ? dirname(APPLICATION_PATH) : dirname(__DIR__, 4);
        return $base . '/var/theme-remote';
    }

    /**
     * The mirror path, but only once it actually holds content — what contentFile() prefers over the
     * theme's baked `content/`.
     *
     * A mirror that exists but is empty (a fetch that has never succeeded) must NOT win, or it would
     * shadow the baked pages with nothing. Returning '' leaves the baked content/ in charge, which
     * is the floor a cold node renders from.
     *
     * @return string the mirror path when it holds content, else ''
     */
    public function contentRoot()
    {
        if (!$this->isEnabled()) { return ''; }

        $dir = $this->dir();
        if (!is_dir($dir)) { return ''; }

        $m = $this->localManifest();
        return !empty($m['files']) ? $dir : '';
    }

    // =============================================================================================
    //  Status
    // =============================================================================================

    /**
     * What this server currently holds, for an admin screen and the content-version tag.
     *
     * @return array ['enabled','url','dir','files','bytes','generated','synced_at','error']
     */
    public function status()
    {
        $m = $this->localManifest();

        return [
            'enabled'   => $this->isEnabled(),
            'url'       => $this->baseUrl(),
            'dir'       => $this->dir(),
            'files'     => count($m['files'] ?? []),
            'bytes'     => (int) ($m['bytes'] ?? 0),
            'generated' => (string) ($m['generated'] ?? ''),
            'synced_at' => (int) ($m['synced_at'] ?? 0),
            'error'     => (string) ($m['error'] ?? ''),
        ];
    }

    /**
     * The manifest the last successful fetch recorded, or an empty array.
     *
     * @return array the stored manifest
     */
    public function localManifest()
    {
        if ($this->_local !== null) { return $this->_local; }

        $path = $this->dir() . '/' . self::LOCAL_MANIFEST;
        if (!is_file($path)) { return $this->_local = []; }

        $data = json_decode((string) @file_get_contents($path), true);
        return $this->_local = is_array($data) ? $data : [];
    }

    /**
     * A short, honest label for what this server is serving.
     *
     * @return string e.g. `remote 2026-10-30T18:04:11Z`, or 'remote' with no stamp
     */
    public function version()
    {
        $m   = $this->localManifest();
        $gen = trim((string) ($m['generated'] ?? ''));
        return $gen !== '' ? 'remote ' . $gen : 'remote';
    }

    // =============================================================================================
    //  The fetch
    // =============================================================================================

    /**
     * The scheduled-job entry point. Throws on failure so the scheduler records it as a failed run
     * (and can email) rather than logging a silent success.
     *
     * @return void
     * @throws RuntimeException when the fetch fails
     */
    public static function syncJob()
    {
        $self = new self();
        if (!$self->isEnabled()) { return; }   // not configured on this install — nothing to do

        $res = $self->sync();
        if (!empty($res['error'])) {
            throw new RuntimeException('Theme content fetch failed: ' . $res['error']);
        }
    }

    /**
     * Mirror the remote content into the local directory.
     *
     * Safe to call concurrently: a lock file makes a second caller (the admin button landing on top
     * of the cron tick) return `busy` instead of fighting over the same files.
     *
     * @param  bool  $force fetch every file even when its hash already matches
     * @return array ['ok','changed','removed','files','bytes','generated','busy','error']
     */
    public function sync($force = false)
    {
        $out = ['ok' => false, 'changed' => 0, 'removed' => 0, 'files' => 0, 'bytes' => 0,
                'generated' => '', 'busy' => false, 'error' => ''];

        $base = $this->baseUrl();
        if ($base === '') {
            $out['error'] = 'No remote content source is configured.';
            return $out;
        }

        $dir = $this->dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $out['error'] = 'The content mirror directory is not writable: ' . $dir;
            return $out;
        }

        $lock = @fopen($dir . '/.lock', 'c');
        if (!$lock) {
            $out['error'] = 'Could not open the fetch lock.';
            return $out;
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            $out['busy'] = true;
            $out['error'] = 'A fetch is already running.';
            return $out;
        }

        try {
            $out = $this->_sync($base, $dir, (bool) $force) + $out;
        } catch (Throwable $e) {
            $out['error'] = $e->getMessage();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        if ($out['error'] !== '') {
            $this->_log('theme.remote.failed', ['url' => $base, 'error' => $out['error']]);
        } else {
            $this->_log('theme.remote.synced', [
                'url'     => $base,
                'changed' => $out['changed'],
                'removed' => $out['removed'],
                'files'   => $out['files'],
            ]);
        }

        return $out;
    }

    /** The fetch proper, inside the lock. */
    protected function _sync($base, $dir, $force)
    {
        // 1) The manifest. This is the only request a no-op fetch makes.
        $raw = $this->_get($base . '/' . self::MANIFEST, 20);
        if ($raw === null) {
            throw new RuntimeException('Could not read ' . self::MANIFEST . ' from the content source.');
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
            throw new RuntimeException(self::MANIFEST . ' is not a content manifest.');
        }

        $want = $this->_normalize($manifest['files']);
        if (!$want) {
            throw new RuntimeException(self::MANIFEST . ' lists no usable content files.');
        }

        // 2) What has changed since the last successful fetch. A hash that matches is skipped
        //    without touching the file, so an unchanged tree keeps its mtimes.
        $have    = $this->_normalize(($this->localManifest()['files'] ?? []));
        $fetch   = [];
        $bytes   = 0;
        foreach ($want as $rel => $meta) {
            $bytes += $meta['size'];
            $local  = $dir . '/' . $rel;
            $same   = !$force
                   && isset($have[$rel])
                   && $have[$rel]['sha256'] === $meta['sha256']
                   && is_file($local)
                   && filesize($local) === $meta['size'];
            if (!$same) { $fetch[$rel] = $meta; }
        }
        if ($bytes > self::MAX_TOTAL) {
            throw new RuntimeException('The content manifest totals ' . $bytes . ' bytes, over the limit.');
        }

        // 3) Download the changed files, verify, install.
        $changed = $this->_download($base, $dir, $fetch);

        // 4) Drop what the manifest no longer lists, then prune the dirs it emptied.
        $removed = $this->_prune($dir, $want);

        // 5) The manifest last: it is the record that all of the above succeeded.
        $this->_local = [
            'generated' => (string) ($manifest['generated'] ?? ''),
            'synced_at' => time(),
            'files'     => $want,
            'bytes'     => $bytes,
            'error'     => '',
        ];
        if (@file_put_contents($dir . '/' . self::LOCAL_MANIFEST, json_encode($this->_local)) === false) {
            throw new RuntimeException('Could not write the local manifest.');
        }

        return [
            'ok'        => true,
            'changed'   => $changed,
            'removed'   => $removed,
            'files'     => count($want),
            'bytes'     => $bytes,
            'generated' => (string) ($manifest['generated'] ?? ''),
            'busy'      => false,
            'error'     => '',
        ];
    }

    /**
     * Validate + normalize a manifest's file map to `rel => ['sha256','size']`.
     *
     * Every path is checked here rather than at download time, because this is the one place a
     * remote party chooses a local filename. Anything suspicious is dropped rather than repaired.
     */
    protected function _normalize(array $files)
    {
        $out = [];
        foreach ($files as $rel => $meta) {
            if (count($out) >= self::MAX_FILES) { break; }

            $rel = $this->_safePath((string) $rel);
            if ($rel === '') { continue; }

            // Tolerate the compact form (`"path": "<sha256>"`) alongside the full one.
            $sha  = is_array($meta) ? (string) ($meta['sha256'] ?? '') : (string) $meta;
            $size = is_array($meta) ? (int) ($meta['size'] ?? 0) : 0;

            if (!preg_match('/^[a-f0-9]{64}$/', $sha)) { continue; }
            if ($size < 0 || $size > self::MAX_FILE) { continue; }

            $out[$rel] = ['sha256' => $sha, 'size' => $size];
        }
        ksort($out);
        return $out;
    }

    /**
     * A manifest path reduced to something safe to write under the mirror, or '' to refuse it.
     *
     * @param  string $rel the path as the manifest gave it
     * @return string the safe relative path, or '' when it must be refused
     */
    protected function _safePath($rel)
    {
        $rel = str_replace('\\', '/', trim($rel));

        if ($rel === '' || strlen($rel) > 400) { return ''; }
        if ($rel[0] === '/') { return ''; }           // refuse an absolute path outright
        if (strpos($rel, '..') !== false) { return ''; }
        if (strpos($rel, "\0") !== false) { return ''; }

        // Each segment: plain and visible, and may start with an underscore.
        foreach (explode('/', $rel) as $seg) {
            if (!preg_match('/^[A-Za-z0-9_][A-Za-z0-9._-]*$/', $seg)) { return ''; }
        }

        $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) { return ''; }

        return $rel;
    }

    /**
     * Download + install each wanted file. Returns how many were installed.
     *
     * A file whose bytes do not match the manifest's hash is refused — the hash is the only reason
     * to trust what came back, and a CDN serving a stale or truncated object is the ordinary case
     * this catches.
     */
    protected function _download($base, $dir, array $fetch)
    {
        if (!$fetch) { return 0; }

        $installed = 0;
        foreach (array_chunk($fetch, self::CONCURRENCY, true) as $batch) {
            $urls = [];
            foreach ($batch as $rel => $meta) {
                $urls[$rel] = $base . '/' . $this->_encodePath($rel);
            }

            foreach ($this->_getMany($urls) as $rel => $body) {
                if ($body === null) {
                    throw new RuntimeException('Could not fetch ' . $rel . ' from the content source.');
                }
                $meta = $batch[$rel];
                if (hash('sha256', $body) !== $meta['sha256']) {
                    throw new RuntimeException('Hash mismatch for ' . $rel . ' — refusing to install it.');
                }
                if (!$this->_install($dir, $rel, $body)) {
                    throw new RuntimeException('Could not write ' . $rel . ' into the content mirror.');
                }
                $installed++;
            }
        }

        return $installed;
    }

    /** Write one file into the mirror via a temp file + rename, so a reader never sees it partial. */
    protected function _install($dir, $rel, $body)
    {
        $path = $dir . '/' . $rel;
        $sub  = dirname($path);
        if (!is_dir($sub) && !@mkdir($sub, 0775, true) && !is_dir($sub)) { return false; }

        $tmp = $path . '.part';
        if (@file_put_contents($tmp, $body) === false) { return false; }
        if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
        @chmod($path, 0664);

        return true;
    }

    /**
     * Delete mirrored files the manifest no longer lists, and the directories that leaves empty.
     * Returns how many files were removed.
     */
    protected function _prune($dir, array $want)
    {
        $removed = 0;
        $dirs    = [];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $rel = ltrim(substr($file->getPathname(), strlen($dir)), '/');
            if ($file->isDir()) { $dirs[] = $file->getPathname(); continue; }
            if ($rel === self::LOCAL_MANIFEST || $rel === '.lock') { continue; }
            if (isset($want[$rel])) { continue; }
            if (@unlink($file->getPathname())) { $removed++; }
        }
        foreach ($dirs as $d) { @rmdir($d); }   // only succeeds when empty

        return $removed;
    }

    // =============================================================================================
    //  HTTP
    // =============================================================================================

    /** Percent-encode each path segment, leaving the separators alone. */
    protected function _encodePath($rel)
    {
        return implode('/', array_map('rawurlencode', explode('/', $rel)));
    }

    /**
     * GET one URL. Returns the body, or null on any failure (including a non-2xx).
     *
     * @param  string  $url
     * @param  int     $timeout seconds
     * @return ?string the body, or null
     */
    protected function _get($url, $timeout = 30)
    {
        if (!function_exists('curl_init')) {
            $body = @file_get_contents($url);
            return $body === false ? null : $body;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $this->_curlOpts($timeout));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch);   // no curl_close(): a no-op since PHP 8.0, deprecated in 8.5

        return ($body === false || $code < 200 || $code > 299) ? null : (string) $body;
    }

    /**
     * GET several URLs at once. Returns `key => body|null`, in the order given.
     *
     * @param  array<string,string> $urls key => URL
     * @return array<string,?string> key => body, or null for a failure
     */
    protected function _getMany(array $urls)
    {
        if (!function_exists('curl_multi_init')) {
            $out = [];
            foreach ($urls as $k => $u) { $out[$k] = $this->_get($u); }
            return $out;
        }

        $mh      = curl_multi_init();
        $handles = [];
        foreach ($urls as $key => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, $this->_curlOpts(60) + [CURLOPT_RETURNTRANSFER => true]);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) { curl_multi_select($mh, 1.0); }
        } while ($running && $status === CURLM_OK);

        $out = [];
        foreach ($handles as $key => $ch) {
            $body = curl_multi_getcontent($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $out[$key] = ($body === null || $body === false || $code < 200 || $code > 299) ? null : (string) $body;
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);

        return $out;
    }

    /** The shared cURL options for every content request. */
    protected function _curlOpts($timeout)
    {
        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => (int) $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'TigerTheme',
            CURLOPT_ENCODING       => '',   // accept gzip — HTML compresses well
        ];
    }

    // =============================================================================================
    //  Config + logging (tolerant of an early/partial boot)
    // =============================================================================================

    /** Read `tiger.theme.<path>` then `theme.<path>` from the config cascade. */
    protected function _cfg($path, $default)
    {
        try {
            $cfg = Zend_Registry::get('Zend_Config');
        } catch (Throwable $e) {
            return $default;
        }
        foreach (['tiger.theme.' . $path, 'theme.' . $path] as $full) {
            $node = $cfg;
            foreach (explode('.', $full) as $k) {
                $node = is_object($node) ? ($node->{$k} ?? null) : null;
                if ($node === null) { break; }
            }
            if ($node !== null && !is_object($node)) { return $node; }
        }
        return $default;
    }

    /** Structured log line, never fatal on an install whose logger is not up. */
    protected function _log($event, array $ctx)
    {
        try {
            if (!class_exists('Tiger_Log')) { return; }
            if (strpos($event, 'failed') !== false) { Tiger_Log::error($event, $ctx); return; }
            Tiger_Log::info($event, $ctx);
        } catch (Throwable $e) { /* logging must never break a fetch */ }
    }
}
