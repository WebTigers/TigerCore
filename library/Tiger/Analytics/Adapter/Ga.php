<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Analytics_Adapter_Ga — Google Analytics 4 reporting behind the provider-agnostic registry.
 *
 * A thin wrapper: Tiger_Google_Analytics already owns the OAuth connection, the GA4 Data API calls and
 * the 30-minute report cache, so this only (a) runs it for the NAMED org (Tiger_Google_Analytics::
 * forOrg) and (b) maps its summary onto the normalized shape:
 *
 *   GA summary                         normalized
 *   totals[0] activeUsers          →   totals.visitors
 *   totals[1] sessions             →   totals.visits
 *   totals[2] screenPageViews      →   totals.pageviews
 *   (not fetched)                  →   totals.bounce_rate / avg_time = null
 *   series[].users / .views        →   series[].visitors / .pageviews
 *   top_pages[].value              →   top_pages[].hits
 *   top_sources[] (channel groups) →   top_referrers[].visits (sessions per channel)
 *
 * Nothing about GA changes: the same calls, the same cache, the same reconnect signal.
 *
 * @api
 */
class Tiger_Analytics_Adapter_Ga extends Tiger_Analytics_Adapter_Abstract
{
    /**
     * @return string the registry key
     */
    public function name(): string
    {
        return 'ga';
    }

    /**
     * @return string the picker label
     */
    public function label(): string
    {
        return 'Google Analytics';
    }

    /**
     * Connected for this org: a GA4 property + a stored (org-level) refresh token (+ BYO client creds).
     *
     * @param  string $orgId the org
     * @return bool
     */
    public function isConfigured(string $orgId): bool
    {
        if (!class_exists('Tiger_Google_Analytics')) {
            return false;
        }
        try {
            return (bool) Tiger_Google_Analytics::forOrg($orgId, static function () {
                return Tiger_Google_Analytics::isConnected();
            });
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * The org's GA4 summary, normalized. `reconnect_required` when the stored grant is dead.
     *
     * @param  string $orgId  the org
     * @param  array  $period `days`, `fresh`
     * @return array
     */
    public function report(string $orgId, array $period = []): array
    {
        if (!$this->isConfigured($orgId)) {
            return $this->_error('analytics.reports.not_configured', 'not_configured');
        }
        $days  = $this->_days($period);
        $fresh = !empty($period['fresh']);
        try {
            [$raw, $reconnect] = Tiger_Google_Analytics::forOrg($orgId, static function () use ($days, $fresh) {
                $s = Tiger_Google_Analytics::summary($days, $fresh);
                return [$s, $s === null && Tiger_Google_Analytics::reconnectRequired()];
            });
        } catch (Throwable $e) {
            return $this->_error('analytics.reports.error', 'error');
        }
        if (!is_array($raw)) {
            return $reconnect
                ? $this->_error('analytics.reports.reconnect_required', 'reconnect_required')
                : $this->_error('analytics.reports.error', 'error');
        }
        return $this->fromSummary($days, $raw);
    }

    /**
     * Map a Tiger_Google_Analytics::summary() array onto the normalized shape. Public so the mapping
     * is testable without a GA round-trip.
     *
     * @param  int   $days the window
     * @param  array $raw  the GA summary
     * @return array the normalized report
     */
    public function fromSummary(int $days, array $raw): array
    {
        $t = array_values((array) ($raw['totals'] ?? []));
        $series = [];
        foreach ((array) ($raw['series'] ?? []) as $p) {
            $series[] = ['date' => $p['date'] ?? '', 'visitors' => $p['users'] ?? 0, 'pageviews' => $p['views'] ?? 0];
        }
        $pages = [];
        foreach ((array) ($raw['top_pages'] ?? []) as $r) {
            $pages[] = ['label' => $r['label'] ?? '', 'hits' => $r['value'] ?? 0];
        }
        $refs = [];
        foreach ((array) ($raw['top_sources'] ?? []) as $r) {
            $refs[] = ['label' => $r['label'] ?? '', 'visits' => $r['value'] ?? 0];
        }
        return $this->_normalize($days, [
            'visitors'  => $t[0] ?? null,
            'visits'    => $t[1] ?? null,
            'pageviews' => $t[2] ?? null,
        ], $series, $pages, $refs);
    }

    /**
     * GA's own live self-test (Tiger_Google_Analytics::testConnection), for the named org.
     *
     * @param  string $orgId the org
     * @return array
     */
    public function test(string $orgId): array
    {
        if (!class_exists('Tiger_Google_Analytics')) {
            return ['ok' => false, 'code' => 'unavailable', 'message' => 'Google Analytics support is not available.'];
        }
        try {
            return (array) Tiger_Google_Analytics::forOrg($orgId, static function () {
                return Tiger_Google_Analytics::testConnection();
            });
        } catch (Throwable $e) {
            return ['ok' => false, 'code' => 'error', 'message' => 'The connection test failed to run.'];
        }
    }
}
