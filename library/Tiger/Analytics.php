<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Analytics — the provider-agnostic analytics REPORTING registry.
 *
 * One entry point for "how is this site's traffic?", whatever measures it. Each provider is an adapter
 * (Tiger_Analytics_Adapter_Interface) registered by name — the same name → class registry as
 * Tiger_Location — and each org chooses one as its reporting provider:
 *
 *   tiger.analytics.provider = ""        ; org-scoped: "" (auto) | "ga" | "matomo" | any registered name
 *
 * Built in: `ga` (Google Analytics 4, via Tiger_Google_Analytics) and `matomo` (self-hosted Matomo's
 * Reporting API). A module adds another with Tiger_Analytics::register('plausible', 'My_Adapter').
 *
 * RESOLUTION (provider()) — so an existing install keeps working with zero clicks:
 *   1. the org's chosen provider, if it names a registered adapter;
 *   2. else, auto: GA if the org has GA connected → 'ga';
 *   3. else Matomo if the org has it configured with a token → 'matomo';
 *   4. else '' — no provider; the dashboard shows "Choose an analytics provider".
 * Switching provider never deletes the other provider's settings; it only changes which one reports.
 *
 * TRACKING TAGS ARE INDEPENDENT. The choice here is about setup + reporting. Each provider's module
 * still emits its own tag from its own plugin + enabled flag (tiger.analytics.enabled for GA,
 * tiger.matomo.enabled for Matomo), so a site may tag with both while reporting from one.
 *
 * THE NORMALIZED REPORT (report()) — every adapter returns exactly this, so one widget renders any:
 *
 *   [
 *     'provider'      => 'ga',                                   // the adapter that answered
 *     'range'         => ['days' => 28, 'start' => 'YYYY-MM-DD', 'end' => 'YYYY-MM-DD'],
 *     'totals'        => [
 *         'visits'      => int|null,     // sessions / visits
 *         'visitors'    => int|null,     // (unique / active) visitors
 *         'pageviews'   => int|null,
 *         'bounce_rate' => float|null,   // percent, 0..100
 *         'avg_time'    => int|null,     // average visit length, seconds
 *     ],
 *     'series'        => [['date' => 'YYYY-MM-DD', 'visitors' => int, 'pageviews' => int], …],  // daily, for the chart
 *     'top_pages'     => [['label' => '/path', 'hits' => int], …],
 *     'top_referrers' => [['label' => 'Organic Search', 'visits' => int], …],
 *     'fetched_at'    => ISO-8601,
 *   ]
 *
 * A metric a provider does not report is NULL, not 0. Any failure is `['error' => <i18n key>,
 * 'code' => <machine code>]` (+ an optional credential-free `detail`) — never an exception. Codes:
 * `no_provider`, `not_configured`, `reconnect_required` (GA), `unreachable` / `auth` (Matomo), `error`.
 *
 * Everything is ORG-EXPLICIT: pass the org whose analytics you mean (an admin screen passes
 * Tiger_Model_Config::currentOrgId()). One org's provider choice, settings or token never answer for
 * another org, and a credential is org-level only (the install's own org may read the install tier).
 *
 * @api
 * @since 1.61.0
 */
class Tiger_Analytics
{
    /** The org-scoped reporting-provider setting. */
    const CFG_PROVIDER = 'tiger.analytics.provider';

    /** @var array<string,string> provider name => adapter class */
    protected static $_adapters = [
        'ga'     => 'Tiger_Analytics_Adapter_Ga',
        'matomo' => 'Tiger_Analytics_Adapter_Matomo',
    ];

    /** The auto-resolution order when an org has chosen nothing (existing GA installs stay GA). */
    protected static $_autoOrder = ['ga', 'matomo'];

    /**
     * Register (or replace) a provider name → adapter class.
     *
     * @param  string $name  the provider key (lower-cased; letters, digits, _ and -)
     * @param  string $class a Tiger_Analytics_Adapter_Interface implementation
     * @return void
     */
    public static function register(string $name, string $class): void
    {
        $name = self::_key($name);
        if ($name !== '') {
            self::$_adapters[$name] = $class;
        }
    }

    /**
     * The registered provider keys whose adapter class loads.
     *
     * @return string[]
     */
    public static function names(): array
    {
        return array_keys(array_filter(self::$_adapters, static function ($c) { return class_exists($c, true); }));
    }

    /**
     * Is $name a registered provider whose adapter loads?
     *
     * @param  string $name the provider key
     * @return bool
     */
    public static function has(string $name): bool
    {
        return self::adapter($name) !== null;
    }

    /**
     * A provider's adapter, or null when unregistered / not loadable.
     *
     * @param  string $name the provider key
     * @return Tiger_Analytics_Adapter_Interface|null
     */
    public static function adapter(string $name): ?Tiger_Analytics_Adapter_Interface
    {
        $class = self::$_adapters[self::_key($name)] ?? null;
        if (!$class || !class_exists($class, true)) {
            return null;
        }
        try {
            $a = new $class();
        } catch (Throwable $e) {
            return null;
        }
        return ($a instanceof Tiger_Analytics_Adapter_Interface) ? $a : null;
    }

    /**
     * The provider the org explicitly CHOSE ('' when it chose none, or chose one no longer registered).
     * Read org → install → ini: a provider choice is not a secret, so an org inherits the install's.
     *
     * @param  string $orgId the org
     * @return string
     */
    public static function chosen(string $orgId): string
    {
        $v = self::_key((string) Tiger_Model_Config::orgValue($orgId, self::CFG_PROVIDER, ''));
        return ($v !== '' && self::has($v)) ? $v : '';
    }

    /**
     * The org's EFFECTIVE reporting provider — its choice, else auto-resolved (see the class doc), else ''.
     *
     * @param  string $orgId the org
     * @return string a registered provider key, or '' for none
     */
    public static function provider(string $orgId): string
    {
        $chosen = self::chosen($orgId);
        if ($chosen !== '') {
            return $chosen;
        }
        foreach (self::$_autoOrder as $name) {
            $a = self::adapter($name);
            if ($a && $a->isConfigured($orgId)) {
                return $name;
            }
        }
        return '';
    }

    /**
     * Persist an org's provider choice ('' = back to auto). Writes ONLY the provider key — the other
     * provider's settings are never touched. An unregistered name is refused.
     *
     * @param  string      $provider a registered provider key, or ''
     * @param  string|null $orgId    the org to save for; null = the caller's current org
     * @return bool false when $provider is not registered
     */
    public static function saveProvider(string $provider, $orgId = null): bool
    {
        $provider = self::_key($provider);
        if ($provider !== '' && !self::has($provider)) {
            return false;
        }
        [$scope, $sid] = Tiger_Model_Config::settingScope($orgId);
        (new Tiger_Model_Config())->set($scope, $sid, self::CFG_PROVIDER, $provider);
        return true;
    }

    /**
     * The org's traffic report from its effective provider, normalized (shape in the class doc).
     *
     * @param  string $orgId  the org
     * @param  array  $period `days` (1..365, default 28), `fresh` (bypass cache)
     * @return array the normalized report, or ['error' => …, 'code' => …]
     */
    public static function report(string $orgId, array $period = []): array
    {
        $name = self::provider($orgId);
        $a    = $name !== '' ? self::adapter($name) : null;
        if (!$a) {
            return ['error' => 'analytics.reports.no_provider', 'code' => 'no_provider'];
        }
        try {
            $out = $a->report($orgId, $period);
        } catch (Throwable $e) {
            $out = ['error' => 'analytics.reports.error', 'code' => 'error'];
        }
        if (!isset($out['error'])) {
            $out['provider'] = $name;
        } else {
            $out += ['code' => 'error', 'provider' => $name];
        }
        return $out;
    }

    /**
     * A live connection self-test for one provider (default: the org's effective one).
     *
     * @param  string      $orgId    the org
     * @param  string|null $provider the provider to test; null = provider($orgId)
     * @return array{ok:bool,code:string,message:string}
     */
    public static function test(string $orgId, ?string $provider = null): array
    {
        $name = $provider === null ? self::provider($orgId) : self::_key($provider);
        $a    = $name !== '' ? self::adapter($name) : null;
        if (!$a) {
            return ['ok' => false, 'code' => 'no_provider', 'message' => 'analytics.reports.no_provider'];
        }
        try {
            return $a->test($orgId) + ['provider' => $name];
        } catch (Throwable $e) {
            return ['ok' => false, 'code' => 'error', 'message' => 'analytics.reports.error', 'provider' => $name];
        }
    }

    /**
     * The picker's view of every provider for one org: key, label, configured, plus the org's choice
     * and effective provider. Carries no credential.
     *
     * @param  string $orgId the org
     * @return array{chosen:string,provider:string,providers:array<string,array{name:string,label:string,configured:bool}>}
     */
    public static function overview(string $orgId): array
    {
        $providers = [];
        foreach (self::names() as $name) {
            $a = self::adapter($name);
            if (!$a) { continue; }
            $providers[$name] = ['name' => $name, 'label' => $a->label(), 'configured' => $a->isConfigured($orgId)];
        }
        return ['chosen' => self::chosen($orgId), 'provider' => self::provider($orgId), 'providers' => $providers];
    }

    /** Normalize a provider key (lower-case, [a-z0-9_-]). */
    private static function _key(string $name): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9_-]/i', '', $name));
    }
}
