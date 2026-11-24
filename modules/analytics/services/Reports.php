<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics_Service_Reports — the /api service the analytics dashboard (and widget) fetch from. `report`
 * returns the NORMALIZED traffic report from the org's chosen provider (Tiger_Analytics — GA, Matomo, …);
 * `summary` still returns the raw file-cached GA4 summary for compatibility. Admin-only; read-only, so it
 * follows the client/server pattern (the view renders an empty shell, the data arrives over /api).
 *
 * @api
 */
class Analytics_Service_Reports extends Tiger_Service_Service
{
    /**
     * The GA4 traffic summary for the requested window (default 28 days).
     *
     * @param  array $params optional `days` (1..365) and `fresh` (bypass cache)
     * @apiParam integer days The report window in days (1..365, default 28).
     * @apiParam boolean fresh Bypass the cache and fetch live.
     * @return void
     */
    public function summary(array $params): void
    {
        if (!$this->_isAllowed('summary')) {
            $this->_error('core.api.error.not_allowed');
            return;
        }
        if (!class_exists('Tiger_Google_Analytics') || !Tiger_Google_Analytics::isConnected()) {
            $this->_error('analytics.reports.not_connected');
            return;
        }
        $days = isset($params['days']) ? (int) $params['days'] : 28;
        $data = Tiger_Google_Analytics::summary($days, !empty($params['fresh']));
        if ($data === null) {
            // The connection exists (isConnected passed) but the stored grant is dead — Google/the broker
            // answered the token mint with 401 reconnect_required. Surface that as a distinct, actionable
            // outcome (`code=reconnect_required`) so the UI can offer "Reconnect", instead of the generic
            // "couldn't load" that made an expired connection look like a mystery (TIGER-115).
            if (Tiger_Google_Analytics::reconnectRequired()) {
                $this->_error('analytics.reports.reconnect_required', ['code' => 'reconnect_required']);
                return;
            }
            $this->_error('analytics.reports.error');
            return;
        }
        $this->_success(['summary' => $data]);
    }

    /**
     * The caller's org's traffic report from its CHOSEN provider (Tiger_Analytics), in the normalized
     * shape every provider shares — what the Traffic widget and the dashboard render. On failure:
     * result=0 with `data.code` (`no_provider` | `not_configured` | `reconnect_required` | `unreachable`
     * | `auth` | `error`) and `data.provider`, so the UI can say exactly what to do.
     *
     * @param  array $params optional `days` (1..365, default 28) or `start`/`end`, the filters, and `fresh`
     * @apiParam integer days The report window in days (1..365, default 28); ignored when start and end are given.
     * @apiParam string start The window's first day (YYYY-MM-DD), where the provider supports ranges.
     * @apiParam string end The window's last day (YYYY-MM-DD).
     * @apiParam string campaign Only visits from this campaign (e.g. facebook-groups).
     * @apiParam string(direct|search|website|social|campaign) channel Only visits from this channel.
     * @apiParam string device Only this device type (e.g. desktop, smartphone, tablet).
     * @apiParam string country Only visitors from this country (ISO-3166 alpha-2, e.g. us).
     * @apiParam boolean fresh Bypass the cache and fetch live.
     * @return void
     */
    public function report(array $params): void
    {
        if (!$this->_isAllowed('report')) {
            $this->_error('core.api.error.not_allowed');
            return;
        }
        $orgId = (string) Tiger_Model_Config::currentOrgId();
        $out   = Tiger_Analytics::report($orgId, $this->_period($params) + ['fresh' => !empty($params['fresh'])]);
        if (isset($out['error'])) {
            $this->_error((string) $out['error'], ['code' => (string) $out['code'], 'provider' => (string) ($out['provider'] ?? '')]);
            return;
        }
        $this->_success(['report' => $out]);
    }

    /**
     * One drill-down for the dashboard: a campaign's keywords, a channel's individual sources, or a page's daily
     * trend and the channels its visitors came from. Same window + filters as `report`.
     *
     * @apiParam string(campaign|channel|page) kind required What to break down.
     * @apiParam string item required The campaign name, channel key (direct|search|website|social|campaign) or page URL.
     * @apiParam integer days The window in days, when start/end aren't given.
     * @apiParam string start The window's first day (YYYY-MM-DD).
     * @apiParam string end The window's last day (YYYY-MM-DD).
     * @apiParam string campaign Filter: only this campaign.
     * @apiParam string channel Filter: only this channel.
     * @apiParam string device Filter: only this device type.
     * @apiParam string country Filter: only this country (alpha-2).
     * @param  array $params `kind`, `item`, the window and the filters
     * @return void
     */
    public function breakdown(array $params): void
    {
        if (!$this->_isAllowed('breakdown')) { $this->_error('core.api.error.not_allowed'); return; }
        $kind = is_string($params['kind'] ?? null) ? $params['kind'] : '';
        $item = is_string($params['item'] ?? null) ? $params['item'] : '';
        if (!in_array($kind, ['campaign', 'channel', 'page'], true) || $item === '') {
            $this->_error('analytics.reports.unsupported', ['code' => 'unsupported']);
            return;
        }
        $out = Tiger_Analytics::breakdown((string) Tiger_Model_Config::currentOrgId(), $kind, $item, $this->_period($params));
        if (isset($out['error'])) {
            $this->_error((string) $out['error'], ['code' => (string) ($out['code'] ?? 'error')]);
            return;
        }
        $this->_success(['breakdown' => $out]);
    }

    /**
     * What the dashboard can offer for this org: the provider's capabilities (date ranges, which filters, which
     * drill-downs) and, for the window, the values each filter can take.
     *
     * @apiParam integer days The window in days, when start/end aren't given.
     * @apiParam string start The window's first day (YYYY-MM-DD).
     * @apiParam string end The window's last day (YYYY-MM-DD).
     * @param  array $params the window
     * @return void
     */
    public function options(array $params): void
    {
        if (!$this->_isAllowed('options')) { $this->_error('core.api.error.not_allowed'); return; }
        $orgId = (string) Tiger_Model_Config::currentOrgId();
        $caps  = Tiger_Analytics::capabilities($orgId);
        $this->_success([
            'capabilities' => $caps,
            'filters'      => $caps['filters'] ? Tiger_Analytics::filterOptions($orgId, $this->_period($params)) : [],
        ]);
    }

    /** The window + filters from the request — a read, so it only coerces; the adapter validates every value. */
    private function _period(array $params): array
    {
        $str = static fn ($k) => is_string($params[$k] ?? null) ? trim($params[$k]) : '';
        $period = ['days' => isset($params['days']) ? (int) $params['days'] : 28];
        if ($str('start') !== '' && $str('end') !== '') {
            $period['start'] = $str('start');
            $period['end']   = $str('end');
        }
        $filters = [];
        foreach (['campaign', 'channel', 'device', 'country'] as $f) {
            if ($str($f) !== '') { $filters[$f] = $str($f); }
        }
        if ($filters) { $period['filters'] = $filters; }
        return $period;
    }

    /**
     * Live connection self-test for the Troubleshooting panel: mints a token + makes one GA4 call and
     * returns a plain diagnosis. A failed *connection* is still a successful *call* (result=1) — the
     * client reads `data.test.ok` and shows the message/hint — so the panel can explain what's wrong.
     *
     * With `provider` (e.g. `matomo`) it tests THAT provider through Tiger_Analytics for the caller's
     * org instead; the message comes back translated. Without it: GA, exactly as before.
     *
     * @param  array $params optional `provider`
     * @apiParam string provider A Tiger_Analytics provider key to test (e.g. matomo); omit to test Google Analytics.
     * @return void
     */
    public function test(array $params): void
    {
        if (!$this->_isAllowed('test')) {
            $this->_error('core.api.error.not_allowed');
            return;
        }
        if (!empty($params['provider']) && is_string($params['provider'])) {
            $t = Tiger_Analytics::test((string) Tiger_Model_Config::currentOrgId(), $params['provider']);
            $t['message'] = $this->_translate((string) ($t['message'] ?? ''));
            $this->_success(['test' => $t]);
            return;
        }
        if (!class_exists('Tiger_Google_Analytics')) {
            $this->_error('analytics.reports.error');
            return;
        }
        $this->_success(['test' => Tiger_Google_Analytics::testConnection()]);
    }

    /** Translate a message key in the caller's locale (a literal passes through unchanged). */
    private function _translate(string $key): string
    {
        try {
            $tr = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
            return ($tr && $tr->isTranslated($key)) ? (string) $tr->translate($key) : $key;
        } catch (Throwable $e) {
            return $key;
        }
    }
}
