<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Environment — detect which transport this instance can use (TIGER-334).
 *
 * The piece that makes shared hosting work. It answers three questions about the running host and
 * recommends a transport:
 *   1. Is the `git` binary present AND is proc_open/exec actually usable (not on disable_functions,
 *      not blocked by the open_basedir/sandbox)?
 *   2. Can PHP reach github.com outbound (cURL present; a cheap HEAD succeeds)?
 * ShellTransport needs (1)+(2); ApiTransport needs only (2) — no git binary, no `.git` directory. When
 * neither is possible the UI shows remediation, never a silent failure.
 *
 * Detection is cheap but not free (it may touch the network), so callers memoize the result on the
 * connection and offer a "Re-check environment" action rather than probing every request. The network
 * probe is behind a seam (`setProbe`) so tests assert the decision logic without hitting github.com.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Git_Environment
{
    /** @var null|callable(string):bool override for the outbound reachability probe (tests) */
    private static $probe = null;

    /**
     * Swap the outbound-reachability probe. The callable receives a host and returns whether it's
     * reachable. Null (default) = the real cURL HEAD probe.
     */
    public static function setProbe(?callable $probe): void
    {
        self::$probe = $probe;
    }

    /**
     * Detect this host's git capabilities and the recommended transport.
     *
     * @return array{
     *   git_binary:bool, exec_usable:bool, outbound:bool,
     *   transport:string, usable:bool, remediation:?string, git_version:?string
     * }
     */
    public static function detect(): array
    {
        $execUsable = self::execUsable();
        $gitVersion = $execUsable ? self::gitVersion() : null;
        $gitBinary  = $gitVersion !== null;
        $outbound   = self::outboundReachable('github.com');

        $shell = $gitBinary && $execUsable && $outbound;
        $api   = $outbound;   // ApiTransport only needs outbound + a credential (checked at connect)

        $transport = $shell ? Tiger_Git::TRANSPORT_SHELL : ($api ? Tiger_Git::TRANSPORT_API : '');
        $usable    = $shell || $api;

        $remediation = null;
        if (!$usable) {
            $remediation = !$outbound
                ? 'This server cannot reach github.com. Ask your host to allow outbound HTTPS to github.com and api.github.com, then re-check.'
                : 'No usable git transport was detected. Re-check after confirming outbound access to GitHub.';
        }

        return [
            'git_binary'  => $gitBinary,
            'exec_usable' => $execUsable,
            'outbound'    => $outbound,
            'transport'   => $transport,
            'usable'      => $usable,
            'remediation' => $remediation,
            'git_version' => $gitVersion,
        ];
    }

    /** Are proc_open AND exec-family calls actually callable here (not disabled)? */
    public static function execUsable(): bool
    {
        if (!function_exists('proc_open')) { return false; }
        $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        return !in_array('proc_open', $disabled, true);
    }

    /** `git --version` string when the binary runs, else null. */
    public static function gitVersion(): ?string
    {
        if (!self::execUsable()) { return null; }
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open(['git', '--version'], $descriptors, $pipes);
        if (!is_resource($proc)) { return null; }
        $out = stream_get_contents($pipes[1]) ?: '';
        foreach ($pipes as $p) { if (is_resource($p)) { fclose($p); } }
        $exit = proc_close($proc);
        if ($exit !== 0) { return null; }
        return preg_match('/\d+\.\d+(\.\d+)?/', $out, $m) ? $m[0] : trim($out);
    }

    /** Is $host reachable over HTTPS right now? Behind the test seam; the real path is a cheap cURL HEAD. */
    public static function outboundReachable(string $host): bool
    {
        if (!Tiger_Git::isAllowedHost($host)) { return false; }
        if (self::$probe !== null) {
            try { return (bool) (self::$probe)($host); } catch (\Throwable $e) { return false; }
        }
        if (!function_exists('curl_init')) { return false; }
        $ch = curl_init('https://' . $host);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'Tiger-Git',
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $errno === 0 && $code > 0;
    }
}
