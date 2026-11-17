<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Analytics_Adapter_Matomo — self-hosted Matomo reporting behind the provider-agnostic registry.
 *
 * Speaks Matomo's HTTP Reporting API only (Matomo is a separate GPLv3 application; nothing of it is
 * embedded). ONE request per report: `API.getBulkRequest` POSTed to `<url>/index.php` with the
 * `token_auth` in the POST BODY — never the query string, so it never lands in an access log, a proxy
 * log or a Referer (and Matomo 4.12+ refuses a "secure only" token sent any other way). 5-second
 * timeout; a slow or dead Matomo yields a clean `{error}`, never a stalled dashboard.
 *
 * Config, for the org being reported on:
 *   tiger.matomo.url             the Matomo base URL (plain setting — org → install → ini)
 *   tiger.matomo.site_id         the idSite            (plain setting)
 *   tiger.matomo.token_auth_enc  the API token, encrypted with Tiger_Crypto — ORG-LEVEL ONLY: the
 *                                org's own row (the install's own org may also read the install
 *                                tier). Another org never borrows the install's token.
 *
 * Bulk sub-requests → normalized shape:
 *   VisitsSummary.get  (range) → totals.visits / visitors / bounce_rate / avg_time
 *   Actions.get        (range) → totals.pageviews
 *   VisitsSummary.get  (day)   → series[].visitors (unique visitors, else visits)
 *   Actions.get        (day)   → series[].pageviews
 *   Actions.getPageUrls (range, flat) → top_pages[] {label, hits}
 *   Referrers.getAll   (range) → top_referrers[] {label, visits}
 *
 * Not cached: a self-hosted Matomo has no quota and archives server-side; the widget fetches async.
 *
 * @api
 */
class Tiger_Analytics_Adapter_Matomo extends Tiger_Analytics_Adapter_Abstract implements Tiger_Analytics_Tracker_Interface
{
    /**
     * Server-side events need only the URL + site id (Matomo's tracking API is unauthenticated). With the
     * org's token the event also carries the visitor's IP (`cip`), which Matomo accepts only authenticated.
     *
     * @param  string $orgId the org
     * @return bool
     */
    public function canTrack(string $orgId): bool
    {
        return $this->url($orgId) !== '' && $this->siteId($orgId) > 0;
    }

    /**
     * Send one event to Matomo's HTTP tracking API (`matomo.php`) as an Event: category `tiger`, action = the
     * event name, name = the `label` param (else the first param). Token (when set) in the POST body only.
     *
     * @param  string $orgId the org
     * @param  array  $event the normalized event (Tiger_Analytics_Tracker_Interface)
     * @return bool
     */
    public function track(string $orgId, array $event): bool
    {
        $url  = $this->url($orgId);
        $site = $this->siteId($orgId);
        if ($url === '' || $site < 1) {
            return false;
        }
        $params = (array) ($event['params'] ?? []);
        $label  = (string) ($params['label'] ?? (reset($params) !== false ? reset($params) : ''));
        $fields = [
            'idsite' => $site, 'rec' => 1, 'apiv' => 1, 'send_image' => 0,
            'e_c'    => 'tiger', 'e_a' => (string) $event['name'], 'e_n' => $label,
            '_id'    => substr(hash('sha256', (string) ($event['client_id'] ?? '')), 0, 16),
            'url'    => (string) ($event['url'] ?? ''), 'urlref' => (string) ($event['referrer'] ?? ''),
            'ua'     => (string) ($event['ua'] ?? ''),
        ];
        if (isset($params['value']) && is_numeric($params['value'])) {
            $fields['e_v'] = $params['value'];
        }
        $token = $this->_secret($orgId, self::CFG_TOKEN);
        if ($token !== '' && !empty($event['ip'])) {
            $fields['cip']        = (string) $event['ip'];
            $fields['token_auth'] = $token;
        }
        $code = $this->_httpPost($url . 'matomo.php', http_build_query($fields), ['Content-Type: application/x-www-form-urlencoded']);
        return $code >= 200 && $code < 300;
    }

    const CFG_URL     = 'tiger.matomo.url';
    const CFG_SITE_ID = 'tiger.matomo.site_id';
    const CFG_TOKEN   = 'tiger.matomo.token_auth_enc';

    /** Seconds before a Matomo call is abandoned. */
    const TIMEOUT = 5;

    /**
     * @var callable|null test seam: fn(string $url, array $postFields): array{0:int,1:string|false}.
     * Replaces the curl POST so the adapter runs with no network. Always null in production.
     */
    protected static $_transport = null;

    /**
     * Install (or clear, with null) the HTTP seam. Tests only.
     *
     * @internal
     * @param  callable|null $fn fn(string $url, array $postFields): array{0:int,1:string|false}
     * @return void
     */
    public static function setTransport(?callable $fn): void
    {
        self::$_transport = $fn;
    }

    /**
     * @return string the registry key
     */
    public function name(): string
    {
        return 'matomo';
    }

    /**
     * @return string the picker label
     */
    public function label(): string
    {
        return 'Matomo';
    }

    /**
     * The org's Matomo base URL (trailing-slashed), or '' when unset/not http(s).
     *
     * @param  string $orgId the org
     * @return string
     */
    public function url(string $orgId): string
    {
        $url = $this->_setting($orgId, self::CFG_URL);
        if ($url === '' || !preg_match('#^https?://[^\s"\'<>]+$#i', $url)) {
            return '';
        }
        return rtrim($url, '/') . '/';
    }

    /**
     * The org's Matomo site id (idSite), or 0 when unset.
     *
     * @param  string $orgId the org
     * @return int
     */
    public function siteId(string $orgId): int
    {
        return max(0, (int) $this->_setting($orgId, self::CFG_SITE_ID, '0'));
    }

    /**
     * Does this org hold its own Matomo API token? (Never the token itself.)
     *
     * @param  string $orgId the org
     * @return bool
     */
    public function hasToken(string $orgId): bool
    {
        return $this->_secret($orgId, self::CFG_TOKEN) !== '';
    }

    /**
     * Reporting is set up: a valid URL, a site id, and the org's own token.
     *
     * @param  string $orgId the org
     * @return bool
     */
    public function isConfigured(string $orgId): bool
    {
        return $this->url($orgId) !== '' && $this->siteId($orgId) > 0 && $this->hasToken($orgId);
    }

    /**
     * The org's Matomo traffic, normalized.
     *
     * @param  string $orgId  the org
     * @param  array  $period `days` (`fresh` is accepted and ignored — nothing is cached)
     * @return array
     */
    public function report(string $orgId, array $period = []): array
    {
        if (!$this->isConfigured($orgId)) {
            return $this->_error('analytics.reports.not_configured', 'not_configured');
        }
        $days  = $this->_days($period);
        $start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $end   = date('Y-m-d');
        $site  = $this->siteId($orgId);
        $range = ['idSite' => $site, 'period' => 'range', 'date' => $start . ',' . $end];
        $daily = ['idSite' => $site, 'period' => 'day', 'date' => $start . ',' . $end];

        $res = $this->_bulk($orgId, [
            ['method' => 'VisitsSummary.get'] + $range,
            ['method' => 'Actions.get'] + $range,
            ['method' => 'VisitsSummary.get'] + $daily,
            ['method' => 'Actions.get'] + $daily,
            ['method' => 'Actions.getPageUrls', 'flat' => 1, 'filter_limit' => 8,
                'filter_sort_column' => 'nb_hits', 'filter_sort_order' => 'desc'] + $range,
            ['method' => 'Referrers.getAll', 'filter_limit' => 6,
                'filter_sort_column' => 'nb_visits', 'filter_sort_order' => 'desc'] + $range,
        ]);
        if (isset($res['error'])) {
            return $res;
        }
        return $this->fromBulk($days, $res['results']);
    }

    /**
     * Map the six bulk results onto the normalized shape. Public so the mapping is testable directly.
     *
     * @param  int   $days    the window
     * @param  array $results the decoded getBulkRequest array, in request order
     * @return array the normalized report
     */
    public function fromBulk(int $days, array $results): array
    {
        $summary = is_array($results[0] ?? null) ? $results[0] : [];
        $actions = is_array($results[1] ?? null) ? $results[1] : [];
        $vDaily  = is_array($results[2] ?? null) ? $results[2] : [];
        $aDaily  = is_array($results[3] ?? null) ? $results[3] : [];

        $totals = [
            'visits'      => $summary['nb_visits'] ?? null,
            'visitors'    => $summary['nb_uniq_visitors'] ?? null,   // absent when range uniques aren't archived
            'pageviews'   => $actions['nb_pageviews'] ?? null,
            'bounce_rate' => isset($summary['bounce_rate']) ? rtrim((string) $summary['bounce_rate'], '%') : null,
            'avg_time'    => $summary['avg_time_on_site'] ?? null,
        ];

        $series = [];
        foreach ($vDaily as $date => $row) {
            $row  = is_array($row) ? $row : [];
            $acts = (isset($aDaily[$date]) && is_array($aDaily[$date])) ? $aDaily[$date] : [];
            $series[] = [
                'date'      => (string) $date,
                'visitors'  => $row['nb_uniq_visitors'] ?? ($row['nb_visits'] ?? 0),
                'pageviews' => $acts['nb_pageviews'] ?? 0,
            ];
        }

        $pages = [];
        foreach ((is_array($results[4] ?? null) ? $results[4] : []) as $r) {
            if (!is_array($r)) { continue; }
            $pages[] = ['label' => $r['label'] ?? ($r['url'] ?? ''), 'hits' => $r['nb_hits'] ?? 0];
        }
        $refs = [];
        foreach ((is_array($results[5] ?? null) ? $results[5] : []) as $r) {
            if (!is_array($r)) { continue; }
            $refs[] = ['label' => $r['label'] ?? '', 'visits' => $r['nb_visits'] ?? 0];
        }
        return $this->_normalize($days, $totals, $series, $pages, $refs);
    }

    /**
     * Live self-test: can this org's token read its site? (SitesManager.getSiteFromId.) Messages are
     * i18n keys; the token is never part of the outcome.
     *
     * @param  string $orgId the org
     * @return array{ok:bool,code:string,message:string,detail?:string}
     */
    public function test(string $orgId): array
    {
        if ($this->url($orgId) === '' || $this->siteId($orgId) <= 0) {
            return ['ok' => false, 'code' => 'not_configured', 'message' => 'analytics.matomo.test.not_configured'];
        }
        if (!$this->hasToken($orgId)) {
            return ['ok' => false, 'code' => 'no_token', 'message' => 'analytics.matomo.test.no_token'];
        }
        $res = $this->_bulk($orgId, [['method' => 'SitesManager.getSiteFromId', 'idSite' => $this->siteId($orgId)]]);
        if (isset($res['error'])) {
            $out = ['ok' => false, 'code' => $res['code'], 'message' => 'analytics.matomo.test.' . $res['code']];
            if (!empty($res['detail'])) { $out['detail'] = $res['detail']; }
            return $out;
        }
        $site = $res['results'][0] ?? null;
        if (!is_array($site) || empty($site['idsite'])) {
            return ['ok' => false, 'code' => 'no_site', 'message' => 'analytics.matomo.test.no_site'];
        }
        return ['ok' => true, 'code' => 'ok', 'message' => 'analytics.matomo.test.ok', 'site' => (string) ($site['name'] ?? '')];
    }

    // -- transport -----------------------------------------------------------------------------------

    /**
     * POST one API.getBulkRequest. Returns ['results' => [...]] or an error envelope with a `code`
     * (unreachable | http | auth | api) and a credential-free `detail`.
     *
     * @param  string $orgId the org (its URL + token)
     * @param  array  $calls each a sub-request's query params
     * @return array
     */
    protected function _bulk(string $orgId, array $calls): array
    {
        $token = $this->_secret($orgId, self::CFG_TOKEN);
        $fields = ['module' => 'API', 'method' => 'API.getBulkRequest', 'format' => 'JSON', 'token_auth' => $token];
        foreach (array_values($calls) as $i => $c) {
            $fields['urls[' . $i . ']'] = http_build_query($c);
        }
        try {
            [$code, $body] = $this->_post($this->url($orgId) . 'index.php', $fields);
        } catch (Throwable $e) {
            [$code, $body] = [0, false];
        }
        $scrub = static function ($s) use ($token) {
            $s = (string) $s;
            return $token !== '' ? str_replace($token, '[redacted]', $s) : $s;
        };
        if ($code === 0 || $body === false) {
            return ['error' => 'analytics.reports.matomo_unreachable', 'code' => 'unreachable'];
        }
        $decoded = json_decode((string) $body, true);
        $apiError = static function ($d) {
            return is_array($d) && ($d['result'] ?? null) === 'error' ? (string) ($d['message'] ?? 'error') : null;
        };
        $msg = $apiError($decoded);
        if ($msg === null && is_array($decoded) && isset($decoded[0])) {
            $msg = $apiError($decoded[0]);   // a failed first sub-request means the whole report is unusable
        }
        if ($code === 401 || $code === 403 || ($msg !== null && preg_match('/token_auth|access|authenticat|permission/i', $msg))) {
            return ['error' => 'analytics.reports.matomo_auth', 'code' => 'auth', 'detail' => $scrub($msg ?? ('HTTP ' . $code))];
        }
        if ($code < 200 || $code >= 300) {
            return ['error' => 'analytics.reports.error', 'code' => 'http', 'detail' => 'HTTP ' . $code];
        }
        if ($msg !== null) {
            return ['error' => 'analytics.reports.error', 'code' => 'api', 'detail' => $scrub($msg)];
        }
        if (!is_array($decoded) || !array_key_exists(0, $decoded)) {
            return ['error' => 'analytics.reports.error', 'code' => 'api', 'detail' => 'Unexpected response'];
        }
        return ['results' => $decoded];
    }

    /**
     * The curl options for one Matomo API POST. The client names itself, like every other core outbound
     * call (Tiger_Update, Tiger_Vendor): edge WAFs commonly refuse a request with no User-Agent — the AWS
     * managed Common Rule Set's NoUserAgent_HEADER rule blocked these calls in front of a real Matomo.
     *
     * @param  array $fields the POST fields
     * @return array the curl option map
     */
    public static function curlOptions(array $fields): array
    {
        return [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($fields),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'Tiger_Analytics/' . Tiger_Version::VERSION,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_FOLLOWLOCATION => false,   // a redirect would re-send the body somewhere else
        ];
    }

    /**
     * The low-level POST (curl), or the test seam. Returns [httpCode, body]; [0, false] on transport
     * failure. The token travels in the form body only.
     *
     * @param  string $url    the Matomo index.php URL
     * @param  array  $fields the POST fields
     * @return array{0:int,1:string|false}
     */
    protected function _post(string $url, array $fields): array
    {
        if (self::$_transport !== null) {
            return (self::$_transport)($url, $fields);
        }
        if (!function_exists('curl_init')) {
            return [0, false];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, static::curlOptions($fields));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return [$code, $body];
    }
}
