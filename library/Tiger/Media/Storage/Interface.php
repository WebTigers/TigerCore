<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Media_Storage_Interface — the pluggable storage backend for media bytes.
 *
 * The `media` table holds metadata; the actual bytes live behind one of these adapters
 * (filesystem / S3 / GCS / Azure), selected per-file by the row's `disk`. `visibility`
 * (public|private) is passed to every locating call because it can change WHERE/HOW a
 * backend stores or serves an object (a filesystem adapter keeps public files under the
 * docroot and private files outside it; S3 sets the object ACL). Keys are adapter-relative,
 * tenant-namespaced paths built by the upload service — `<org_id>/<kind-folder>/<rand>.<ext>`
 * (e.g. `3f2504e0-…/images/ab12….jpg`); the adapter prepends the visibility root
 * (`public/` | `private/`). The immutable `<org_id>` segment keeps tenants' bytes separated.
 *
 * @api
 */
interface Tiger_Media_Storage_Interface
{
    /**
     * Store bytes from a source file path (e.g. an upload tmp file).
     *
     * @param  string  $key        the adapter-relative storage key
     * @param  string  $sourcePath the source file on disk to copy from
     * @param  string  $visibility public|private
     * @param  ?string $mime       the content MIME type (may be null)
     * @return void
     */
    public function put($key, $sourcePath, $visibility, $mime = null);

    /**
     * Store raw bytes (e.g. a generated thumbnail held in memory).
     *
     * @param  string  $key        the adapter-relative storage key
     * @param  string  $bytes      the raw bytes to store
     * @param  string  $visibility public|private
     * @param  ?string $mime       the content MIME type (may be null)
     * @return void
     */
    public function write($key, $bytes, $visibility, $mime = null);

    /**
     * Read all bytes.
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @return string the object's bytes
     */
    public function get($key, $visibility);

    /**
     * Open a read stream (for large files — avoids loading them fully into memory).
     *
     * @param  string   $key        the adapter-relative storage key
     * @param  string   $visibility public|private
     * @return resource a readable stream for the object
     */
    public function stream($key, $visibility);

    /**
     * Remove the object (idempotent — missing is not an error).
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @return void
     */
    public function delete($key, $visibility);

    /**
     * Does the object exist?
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @return bool   true when the object exists
     */
    public function exists($key, $visibility);

    /**
     * Size in bytes (0 if missing).
     *
     * @param  string $key        the adapter-relative storage key
     * @param  string $visibility public|private
     * @return int    the object size in bytes, or 0 if missing
     */
    public function size($key, $visibility);

    /**
     * A directly-usable URL, or '' when the caller must serve it itself. Public objects
     * always yield a URL (direct path / CDN); private objects yield a signed URL when the
     * backend supports one (S3 presign), else '' — the media layer then serves it through
     * the ACL-checked streamer route (/media/file/<id>).
     *
     * @param  string   $key        the adapter-relative storage key
     * @param  string   $visibility public|private
     * @param  int|null $ttl        seconds for a signed URL (backend default when null)
     * @return string   a usable URL, or '' when the caller must serve the bytes itself
     */
    public function url($key, $visibility, $ttl = null);
}
