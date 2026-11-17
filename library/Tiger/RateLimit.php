<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_RateLimit — a small fixed-window rate limiter for public endpoints.
 *
 * One call per request: `hit($name, $key, $max, $window)` counts this hit in the current window and says
 * whether it is still within the limit. `$name` names the thing being limited (`tinylink.redirect`), `$key`
 * the caller (usually `ipKey()`); both are hashed before they touch storage, so no raw IP is ever written.
 *
 *   if (!Tiger_RateLimit::hit('tinylink.redirect', Tiger_RateLimit::ipKey(), 120, 60)) {
 *       $this->getResponse()->setHttpResponseCode(429); return;
 *   }
 *
 * STORAGE is a tiny file per (name, key) under `var/cache/ratelimit/`, updated under an exclusive lock — it
 * needs no cache server, no schema and works on shared hosting. The counter is PER BOX: on a fleet of N
 * web servers behind a load balancer a caller can reach N × max per window. That is the right trade for
 * throttling abuse (the alternative is a database write on every public hit); a limit that must be exact
 * across a fleet needs a shared store.
 *
 * FAIL-OPEN, always: an unwritable directory, a lock failure or any other fault ALLOWS the request. A rate
 * limiter that breaks a site when the disk is full has done more harm than the abuse it was meant to stop.
 *
 * @api
 * @since 1.65.0
 */
class Tiger_RateLimit
{
    /** One stale-file sweep per this many hits, on average. */
    const GC_EVERY = 500;

    /** A bucket file untouched this long is garbage. */
    const GC_AGE = 86400;

    /** @var string|null test seam: the storage directory */
    protected static $_dir = null;

    /**
     * Count one hit and report whether it is within the limit.
     *
     * @param  string $name   what is being limited, e.g. `tinylink.redirect`
     * @param  string $key    who is calling, e.g. ipKey()
     * @param  int    $max    hits allowed per window (< 1 = unlimited)
     * @param  int    $window window length in seconds (>= 1)
     * @return bool           true = allowed (also on any storage fault); false = over the limit
     */
    public static function hit(string $name, string $key, int $max, int $window): bool
    {
        if ($max < 1) {
            return true;
        }
        $window = max(1, $window);
        $bucket = intdiv(time(), $window);
        try {
            $dir = self::dir();
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return true;
            }
            if (mt_rand(1, self::GC_EVERY) === 1) {
                self::gc();
            }
            $fh = @fopen($dir . '/' . hash('sha256', $name . '|' . $key) . '.json', 'c+');
            if (!$fh) {
                return true;
            }
            try {
                if (!flock($fh, LOCK_EX)) {
                    return true;
                }
                $state = json_decode((string) stream_get_contents($fh), true);
                $count = (is_array($state) && (int) ($state['b'] ?? -1) === $bucket) ? (int) ($state['n'] ?? 0) : 0;
                $count++;
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, json_encode(['b' => $bucket, 'n' => $count]));
                fflush($fh);
                flock($fh, LOCK_UN);
                return $count <= $max;
            } finally {
                fclose($fh);
            }
        } catch (Throwable $e) {
            return true;
        }
    }

    /**
     * A key for "this caller" — a hash of the client IP (already normalized behind a proxy by
     * Tiger_Application). Never the raw address.
     *
     * @return string
     */
    public static function ipKey(): string
    {
        return hash('sha256', 'ip|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    /**
     * Where bucket files live.
     *
     * @return string
     */
    public static function dir(): string
    {
        if (self::$_dir !== null) {
            return self::$_dir;
        }
        $root = defined('APPLICATION_ROOT') ? APPLICATION_ROOT : sys_get_temp_dir();
        return $root . '/var/cache/ratelimit';
    }

    /**
     * Test seam: point storage somewhere else (null restores the default).
     *
     * @param  string|null $dir
     * @return void
     */
    public static function setDir(?string $dir): void
    {
        self::$_dir = $dir;
    }

    /**
     * Delete bucket files nobody has touched for a day. Best-effort.
     *
     * @return int files removed
     */
    public static function gc(): int
    {
        $n = 0;
        foreach ((array) glob(self::dir() . '/*.json') as $f) {
            if (is_file($f) && @filemtime($f) < time() - self::GC_AGE && @unlink($f)) {
                $n++;
            }
        }
        return $n;
    }
}
