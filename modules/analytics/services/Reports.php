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
     * @param  array $params optional `days` (1..365, default 28) and `fresh` (bypass cache)
     * @apiParam integer days The report window in days (1..365, default 28).
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
        $out   = Tiger_Analytics::report($orgId, [
            'days'  => isset($params['days']) ? (int) $params['days'] : 28,
            'fresh' => !empty($params['fresh']),
        ]);
        if (isset($out['error'])) {
            $this->_error((string) $out['error'], ['code' => (string) $out['code'], 'provider' => (string) ($out['provider'] ?? '')]);
            return;
        }
        $this->_success(['report' => $out]);
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
