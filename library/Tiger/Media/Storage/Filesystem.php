<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Media_Storage_Filesystem — local-disk media storage.
 *
 * Two roots (both resolved relative to APPLICATION_ROOT so config stays portable):
 *   - PUBLIC files under the docroot (`public_root`, e.g. public/_media) → served directly
 *     at `public_url` with no PHP in the path.
 *   - PRIVATE files OUTSIDE the docroot (`private_root`, e.g. storage/media) → not
 *     web-reachable; url() returns '' so the media layer streams them through the
 *     ACL-checked /media/file/<id> route.
 *
 * Keys are relative paths; the adapter refuses `..` traversal.
 *
 * @api
 */
class Tiger_Media_Storage_Filesystem implements Tiger_Media_Storage_Interface
{
    protected $_publicRoot;
    protected $_privateRoot;
    protected $_publicUrl;

    /**
     * Resolve the public/private roots and public URL base from the disk config.
     *
     * @param  array $config the `media.disks.<name>.*` settings
     * @return void
     */
    public function __construct(array $config)
    {
        $base = defined('APPLICATION_ROOT') ? rtrim(APPLICATION_ROOT, '/') : rtrim(getcwd(), '/');
        $this->_publicRoot  = $this->_absolute($base, $config['public_root']  ?? 'public/_media');
        $this->_privateRoot = $this->_absolute($base, $config['private_root'] ?? 'storage/media');
        $this->_publicUrl   = rtrim((string) ($config['public_url'] ?? '/_media'), '/');
    }

    /**
     * Store bytes from a source file path (copies the file to its target).
     *
     * @param  string  $key        the adapter-relative storage key
     * @param  string  $sourcePath the source file on disk to copy from
     * @param  string  $visibility public|private
     * @param  ?string $mime       the content MIME type (unused by this adapter)
     * @return void
     * @throws RuntimeException when the target can't be written
     */
    public function put($key, $sourcePath, $visibility, $mime = null)
    {
        $target = $this->_path($key, $visibility);
        $this->_ensureDir(dirname($target));
        if (!@copy($sourcePath, $target)) {
            throw new RuntimeException('Tiger_Media_Storage_Filesystem: could not store ' . $key);
        }
        @chmod($target, 0644);
    }

    /**
     * Store raw bytes to the target file.
     *
     * @param  string  $key        the adapter-relative storage key
     * @param  string  $bytes      the raw bytes to store
     * @param  string  $visibility public|private
     * @param  ?string $mime       the content MIME type (unused by this adapter)
     * @return void
     * @throws RuntimeException when the target can't be written
     */
    public function write($key, $bytes, $visibility, $mime = null)
    {
        $target = $this->_path($key, $visibility);
        $this->_ensureDir(dirname($target));
        if (@file_put_contents($target, $bytes) === false) {
            throw new RuntimeException('Tiger_Media_Storage_Filesystem: could not write ' . $key);
        }
        @chmod($target, 0644);
    }

    /**
     * Read all bytes of a file.
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @return string the file's bytes
     * @throws RuntimeException when the file is not found
     */
    public function get($key, $visibility)
    {
        $path = $this->_path($key, $visibility);
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new RuntimeException('Tiger_Media_Storage_Filesystem: not found ' . $key);
        }
        return $bytes;
    }

    /**
     * Open a read stream for a file.
     *
     * @param  string   $key        the adapter-relative storage key
     * @param  string   $visibility public|private
     * @return resource a readable file handle
     * @throws RuntimeException when the file is not found
     */
    public function stream($key, $visibility)
    {
        $fh = @fopen($this->_path($key, $visibility), 'rb');
        if (!$fh) {
            throw new RuntimeException('Tiger_Media_Storage_Filesystem: not found ' . $key);
        }
        return $fh;
    }

    /**
     * Remove a file (idempotent — a missing file is not an error).
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @return void
     */
    public function delete($key, $visibility)
    {
        $path = $this->_path($key, $visibility);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Does the file exist?
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @return bool   true when the file exists
     */
    public function exists($key, $visibility)
    {
        return is_file($this->_path($key, $visibility));
    }

    /**
     * Size of a file in bytes (0 if missing).
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @return int    the file size in bytes, or 0 if missing
     */
    public function size($key, $visibility)
    {
        $path = $this->_path($key, $visibility);
        return is_file($path) ? (int) filesize($path) : 0;
    }

    /**
     * A directly-usable URL for a file (a docroot URL for public; '' for private).
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @param  ?int   $ttl        unused by this adapter (no signed URLs)
     * @return string a public URL, or '' when the app must stream the bytes itself
     */
    public function url($key, $visibility, $ttl = null)
    {
        // Public: a direct docroot URL. Private: '' -> the media layer uses the streamer route.
        return ($visibility === 'public') ? $this->_publicUrl . '/' . ltrim($key, '/') : '';
    }

    /** Absolute path on disk for a key + visibility (traversal-guarded). */
    protected function _path($key, $visibility)
    {
        $key = ltrim(str_replace('\\', '/', (string) $key), '/');
        if ($key === '' || strpos($key, '..') !== false) {
            throw new RuntimeException('Tiger_Media_Storage_Filesystem: invalid key');
        }
        $root = ($visibility === 'public') ? $this->_publicRoot : $this->_privateRoot;
        return $root . '/' . $key;
    }

    protected function _ensureDir($dir)
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Tiger_Media_Storage_Filesystem: could not create ' . $dir);
        }
    }

    /** Resolve a configured root to an absolute path (relative ones hang off the app root). */
    protected function _absolute($base, $path)
    {
        $path = (string) $path;
        return ($path !== '' && $path[0] === '/') ? rtrim($path, '/') : $base . '/' . rtrim($path, '/');
    }
}
