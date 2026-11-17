<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Analytics_Adapter_Abstract — base for analytics reporting adapters.
 *
 * Carries what every adapter needs and must get identical: the NORMALIZER (so a widget renders any
 * provider from one shape), the error envelope, and the two org-scoped config reads —
 *
 *  - `_setting()` — a plain setting (URL, site id, property id): the org's row → the install-wide row
 *    → the ini file. Not a secret, so inheriting the install's value is fine.
 *  - `_secret()`  — a credential (`*_enc`, decrypted): the org's OWN row only. The install's own org
 *    may also read the install-wide tier (those are its settings); any other org never does, so an
 *    org that set no token has none — it never borrows the install's (TIGER-511, the 1.60 rule).
 *
 * @api
 */
abstract class Tiger_Analytics_Adapter_Abstract implements Tiger_Analytics_Adapter_Interface
{
    /**
     * A human label (default: the provider key, capitalized). Override for a proper name.
     *
     * @return string
     */
    public function label(): string
    {
        return ucfirst($this->name());
    }

    /**
     * A plain (non-secret) setting for one org: org row → global row → ini → $default.
     *
     * @param  string $orgId   the org
     * @param  string $key     the dot-notation config key
     * @param  string $default returned when nothing sets it
     * @return string
     */
    protected function _setting(string $orgId, string $key, string $default = ''): string
    {
        $v = Tiger_Model_Config::orgValue($orgId, $key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    /**
     * A decrypted credential for one org — org-level only (the install org may read the install tier).
     * '' when unset, when crypto is unavailable, or when it will not decrypt.
     *
     * @param  string $orgId  the org
     * @param  string $encKey the dot-notation key of the ENCRYPTED value (`…_enc`)
     * @return string
     */
    protected function _secret(string $orgId, string $encKey): string
    {
        if ($orgId === '') {
            // No org: only an install with no org at all (nothing to bleed into) reads the global tier.
            $enc = Tiger_Model_Org::installOrgId() === '' ? (string) Tiger_Model_Config::orgValue('', $encKey, '') : '';
        } else {
            $enc = Tiger_Model_Org::isInstallOrg($orgId)
                ? (string) Tiger_Model_Config::orgValue($orgId, $encKey, '')
                : (string) Tiger_Model_Config::orgOwnValue($orgId, $encKey, '');
        }
        if ($enc === '' || !class_exists('Tiger_Crypto') || !Tiger_Crypto::isConfigured()) {
            return '';
        }
        try { return (string) Tiger_Crypto::decrypt($enc); } catch (Throwable $e) { return ''; }
    }

    /** Seconds before a server-side tracking call is abandoned — it runs inside a visitor's request. */
    const TRACK_TIMEOUT = 2;

    /**
     * POST a tracking payload (Tiger_Analytics_Tracker_Interface::track). The one network seam a tracker
     * uses, so a test can override it. Fail-soft: 0 on any transport failure, never an exception.
     *
     * @param  string   $url     the endpoint
     * @param  string   $body    the request body
     * @param  string[] $headers raw header lines
     * @return int               the HTTP status, or 0 when the call failed
     */
    protected function _httpPost(string $url, string $body, array $headers = []): int
    {
        if (!function_exists('curl_init')) {
            return 0;
        }
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,   // a secret in the URL/body must never be replayed elsewhere
                CURLOPT_USERAGENT      => 'Tiger_Analytics/' . Tiger_Version::VERSION,   // edge WAFs refuse a blank UA
                CURLOPT_TIMEOUT        => self::TRACK_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => self::TRACK_TIMEOUT,
            ]);
            $ok   = curl_exec($ch) !== false;
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);   // the handle is freed by GC (curl_close is a deprecated no-op)
            return $ok ? $code : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * The error envelope a failed report returns.
     *
     * @param  string $message an i18n key the UI resolves (e.g. `analytics.reports.error`)
     * @param  string $code    a machine code the UI can branch on (e.g. `not_configured`)
     * @return array{error:string,code:string}
     */
    protected function _error(string $message, string $code): array
    {
        return ['error' => $message, 'code' => $code];
    }

    /**
     * Build the normalized report from loose provider data — every key present, every value typed, so
     * a renderer never has to guess. A metric the provider does not report stays NULL (not 0): "no
     * number" and "zero" are different things on a dashboard.
     *
     * @param  int   $days   the window length
     * @param  array $totals visits / visitors / pageviews / bounce_rate / avg_time (any may be absent)
     * @param  array $series [['date' => 'YYYY-MM-DD', 'visitors' => n, 'pageviews' => n], …]
     * @param  array $pages  [['label' => …, 'hits' => n], …]
     * @param  array $refs   [['label' => …, 'visits' => n], …]
     * @return array the normalized report (see Tiger_Analytics::report())
     */
    protected function _normalize(int $days, array $totals, array $series, array $pages, array $refs): array
    {
        $int = static function ($v) { return ($v === null || $v === '') ? null : (int) round((float) $v); };
        $out = [
            'provider'      => $this->name(),
            'range'         => [
                'days'  => $days,
                'start' => date('Y-m-d', strtotime('-' . ($days - 1) . ' days')),
                'end'   => date('Y-m-d'),
            ],
            'totals'        => [
                'visits'      => $int($totals['visits'] ?? null),
                'visitors'    => $int($totals['visitors'] ?? null),
                'pageviews'   => $int($totals['pageviews'] ?? null),
                'bounce_rate' => isset($totals['bounce_rate']) && $totals['bounce_rate'] !== '' ? round((float) $totals['bounce_rate'], 1) : null,
                'avg_time'    => $int($totals['avg_time'] ?? null),
            ],
            'series'        => [],
            'top_pages'     => [],
            'top_referrers' => [],
            'fetched_at'    => date('c'),
        ];
        foreach ($series as $p) {
            $out['series'][] = [
                'date'      => (string) ($p['date'] ?? ''),
                'visitors'  => (int) ($p['visitors'] ?? 0),
                'pageviews' => (int) ($p['pageviews'] ?? 0),
            ];
        }
        foreach ($pages as $p) {
            $out['top_pages'][] = ['label' => (string) ($p['label'] ?? ''), 'hits' => (int) ($p['hits'] ?? 0)];
        }
        foreach ($refs as $r) {
            $out['top_referrers'][] = ['label' => (string) ($r['label'] ?? ''), 'visits' => (int) ($r['visits'] ?? 0)];
        }
        return $out;
    }

    /**
     * Clamp the requested window to 1..365 days (default 28).
     *
     * @param  array $period `days`
     * @return int
     */
    protected function _days(array $period): int
    {
        return max(1, min(365, (int) ($period['days'] ?? 28)));
    }
}
