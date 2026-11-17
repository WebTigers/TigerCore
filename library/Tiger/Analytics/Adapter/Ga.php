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
class Tiger_Analytics_Adapter_Ga extends Tiger_Analytics_Adapter_Abstract implements Tiger_Analytics_Tracker_Interface
{
    /** The GA4 property's web-stream measurement id (the same one the tag uses). */
    const CFG_MEASUREMENT_ID = 'tiger.analytics.ga4.measurement_id';

    /** A Measurement Protocol API secret (GA Admin → Data streams → Measurement Protocol), encrypted. */
    const CFG_API_SECRET = 'tiger.analytics.ga4.api_secret_enc';

    /** The Measurement Protocol collect endpoint. */
    const MP_ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    /**
     * Server-side events need the measurement id + an org-level Measurement Protocol API secret.
     *
     * @param  string $orgId the org
     * @return bool
     */
    public function canTrack(string $orgId): bool
    {
        return $this->_measurementId($orgId) !== '' && $this->_secret($orgId, self::CFG_API_SECRET) !== '';
    }

    /**
     * Send one event via the GA4 Measurement Protocol. Fail-soft, short timeout.
     *
     * @param  string $orgId the org
     * @param  array  $event the normalized event (Tiger_Analytics_Tracker_Interface)
     * @return bool
     */
    public function track(string $orgId, array $event): bool
    {
        $mid    = $this->_measurementId($orgId);
        $secret = $this->_secret($orgId, self::CFG_API_SECRET);
        if ($mid === '' || $secret === '') {
            return false;
        }
        $params = (array) ($event['params'] ?? []);
        if (!empty($event['url']))      { $params['page_location'] = (string) $event['url']; }
        if (!empty($event['referrer'])) { $params['page_referrer'] = (string) $event['referrer']; }
        $body = json_encode([
            'client_id' => (string) ($event['client_id'] ?? ''),
            'events'    => [['name' => (string) $event['name'], 'params' => $params ?: new stdClass()]],
        ]);
        $url  = self::MP_ENDPOINT . '?measurement_id=' . rawurlencode($mid) . '&api_secret=' . rawurlencode($secret);
        $code = $this->_httpPost($url, (string) $body, ['Content-Type: application/json']);
        return $code >= 200 && $code < 300;
    }

    /** The org's GA4 measurement id (`G-…`), or ''. */
    protected function _measurementId(string $orgId): string
    {
        $id = $this->_setting($orgId, self::CFG_MEASUREMENT_ID);
        return preg_match('/^G-[A-Z0-9]+$/i', $id) ? $id : '';
    }

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
