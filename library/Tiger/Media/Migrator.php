<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Media_Migrator — relocate stored media from one disk (storage adapter) to another.
 *
 * Switching `media.default_disk` only changes where NEW uploads land; existing rows keep pointing at
 * their old disk. This moves the actual bytes so an admin can adopt (or leave) a cloud backend, or come
 * back to the local filesystem. It is **adapter-agnostic** — every read/write goes through
 * `Tiger_Media_Storage_Interface`, so local↔S3↔GCS↔Azure (any → any) is the same code.
 *
 * Safe by construction:
 *   - **Copy → verify → flip → (optionally) delete.** Each object is streamed to the target and
 *     size-verified BEFORE the row's `disk` is flipped; the source object is deleted only in **move**
 *     mode, and only after the flip. A failure never loses a file or breaks a live URL.
 *   - **Live-safe & resumable/idempotent.** Rows flip one at a time (the site keeps serving throughout);
 *     a row already on the target is skipped, and an object already present + same-size on the target is
 *     not re-copied — so a re-run resumes where it left off (handy if a big library outran a timeout).
 *   - **Memory-safe.** Objects stream source → a temp file → the target's `put()`, never fully into RAM.
 *   - **Non-fatal errors.** One bad object records an error and leaves that row on its source disk (to
 *     retry on the next run); the migration continues.
 *
 * @api
 */
class Tiger_Media_Migrator
{
    /** Content types worth preserving on copy (variant thumbnails are jpg); else the row's own mime. */
    const EXT_MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'webp' => 'image/webp', 'gif' => 'image/gif', 'avif' => 'image/avif',
    ];

    /**
     * Move (or copy) every media object NOT already on `$toDisk` onto `$toDisk`.
     *
     * @param  string        $toDisk the target disk name (must be configured in media.disks.*)
     * @param  bool          $move   true = delete each source object after a verified copy (default: copy)
     * @param  callable|null $log    optional fn(string $message) progress callback
     * @return array{rows:int,objects:int,bytes:int,skipped:int,move:bool,errors:array<int,string>}
     * @throws RuntimeException if the target disk is not configured
     */
    public static function migrate($toDisk, $move = false, $log = null)
    {
        $toDisk = (string) $toDisk;
        $dst    = Tiger_Media_Storage::disk($toDisk);   // throws if the disk isn't configured

        $model = new Tiger_Model_Media();
        $db    = $model->getAdapter();
        $select = $db->select()
            ->from($model->info(Zend_Db_Table_Abstract::NAME),
                ['media_id', 'disk', 'storage_key', 'visibility', 'mime_type', 'variants', 'extension'])
            ->where('disk != ?', $toDisk);
        $rows = $db->fetchAll($select);

        $out = ['rows' => 0, 'objects' => 0, 'bytes' => 0, 'skipped' => 0, 'move' => (bool) $move, 'errors' => []];

        foreach ($rows as $row) {
            $fromDisk = (string) $row['disk'];
            try {
                $src = Tiger_Media_Storage::disk($fromDisk);
            } catch (Throwable $e) {
                $out['errors'][] = "media {$row['media_id']}: source disk '{$fromDisk}' unavailable — skipped";
                continue;
            }
            $vis  = (string) $row['visibility'];
            // Every object this row owns: the primary key + each variant (thumbnail/preview) key.
            $objects = [['key' => (string) $row['storage_key'], 'mime' => (string) $row['mime_type']]];
            foreach (self::_variants($row['variants']) as $v) {
                if (!empty($v['key'])) {
                    $objects[] = ['key' => (string) $v['key'], 'mime' => self::_mime((string) $v['key'], (string) $row['mime_type'])];
                }
            }

            $copied = [];   // keys confirmed on the target (for this row) — used for the move-delete pass
            $failed = false;
            foreach ($objects as $o) {
                $key = $o['key'];
                if ($key === '') { continue; }
                try {
                    $srcSize = $src->size($key, $vis);
                    // Already there + same size → resumable skip (don't re-transfer).
                    if ($dst->exists($key, $vis) && $dst->size($key, $vis) === $srcSize) {
                        $copied[] = $key;
                        $out['skipped']++;
                        continue;
                    }
                    self::_copyObject($src, $dst, $key, $vis, $o['mime']);
                    if (!$dst->exists($key, $vis) || $dst->size($key, $vis) !== $srcSize) {
                        throw new RuntimeException('post-copy size mismatch');
                    }
                    $copied[] = $key;
                    $out['objects']++;
                    $out['bytes'] += $srcSize;
                } catch (Throwable $e) {
                    $failed = true;
                    $out['errors'][] = "media {$row['media_id']} object '{$key}': " . $e->getMessage();
                    break;   // don't flip a row whose objects aren't all safely on the target
                }
            }

            if ($failed) { continue; }

            // Flip the row to the target disk (its bytes are all verified there now).
            try {
                $model->update(['disk' => $toDisk], $db->quoteInto('media_id = ?', $row['media_id']));
                $out['rows']++;
            } catch (Throwable $e) {
                $out['errors'][] = "media {$row['media_id']}: disk flip failed — " . $e->getMessage();
                continue;   // leave the source objects in place; the row still points at the source
            }

            // MOVE mode: only now (row flipped) is it safe to remove the source objects.
            if ($move) {
                foreach ($copied as $key) {
                    try { $src->delete($key, $vis); } catch (Throwable $e) {
                        $out['errors'][] = "media {$row['media_id']} source '{$key}': delete failed — " . $e->getMessage();
                    }
                }
            }
            self::_emit($log, "media {$row['media_id']}: {$fromDisk} → {$toDisk} (" . count($objects) . ' object(s))');
        }

        self::_emit($log, "done — {$out['rows']} row(s), {$out['objects']} object(s), " . round($out['bytes'] / 1048576, 1) . ' MB'
            . ($out['errors'] ? ', ' . count($out['errors']) . ' error(s)' : ''));
        return $out;
    }

    /** Stream one object source → a temp file → the target's put(), so large files never load into RAM. */
    protected static function _copyObject(Tiger_Media_Storage_Interface $src, Tiger_Media_Storage_Interface $dst, $key, $vis, $mime)
    {
        $in = $src->stream($key, $vis);
        if (!is_resource($in)) {
            // No stream (or a missing object) — fall back to a bytes copy.
            $dst->write($key, (string) $src->get($key, $vis), $vis, $mime !== '' ? $mime : null);
            return;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tigermig');
        try {
            $tmpH = fopen($tmp, 'wb');
            if ($tmpH === false) { throw new RuntimeException('cannot open temp file'); }
            stream_copy_to_stream($in, $tmpH);
            fclose($tmpH);
            $dst->put($key, $tmp, $vis, $mime !== '' ? $mime : null);
        } finally {
            if (is_resource($in)) { fclose($in); }
            @unlink($tmp);
        }
    }

    /** Decode the variants JSON map (name => {key, …}); tolerant of null/array/string input. */
    protected static function _variants($variants)
    {
        if (is_array($variants)) { return $variants; }
        $v = json_decode((string) $variants, true);
        return is_array($v) ? $v : [];
    }

    /** A content type for a variant object from its key's extension, falling back to the row's mime. */
    protected static function _mime($key, $fallback)
    {
        $ext = strtolower((string) pathinfo($key, PATHINFO_EXTENSION));
        return self::EXT_MIME[$ext] ?? $fallback;
    }

    /** @param callable|null $log */
    protected static function _emit($log, $message)
    {
        if (is_callable($log)) { $log($message); }
    }
}
