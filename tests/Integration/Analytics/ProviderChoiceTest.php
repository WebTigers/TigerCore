<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Analytics;

use Analytics_Form_Provider;
use Analytics_Form_Settings;
use Analytics_Service_Analytics;
use Analytics_Service_Reports;
use Matomo_Service_Matomo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Analytics;
use Tiger_Analytics_Adapter_Ga;
use Tiger_Analytics_Adapter_Matomo;
use Tiger_Crypto;
use Tiger_Google_Analytics;
use Tiger_Model_Config;
use Zend_Config;
use Zend_Registry;
use Zend_Session;

/**
 * Analytics provider choice (1.61): the Tiger_Analytics registry, its two adapters, the provider
 * resolution, org isolation of the choice + the credentials, and the /api surface that drives it.
 *
 * The install's own org is `org-install` (tiger.site.org_id); `org-a` and `org-b` are other tenants.
 * Matomo's HTTP is a fake (Tiger_Analytics_Adapter_Matomo::setTransport) that records every call, so a
 * test can prove WHICH org's token went where — and that it went in the POST body.
 */
#[CoversClass(Tiger_Analytics::class)]
#[CoversClass(Tiger_Analytics_Adapter_Ga::class)]
#[CoversClass(Tiger_Analytics_Adapter_Matomo::class)]
#[CoversClass(Analytics_Service_Analytics::class)]
#[CoversClass(Analytics_Service_Reports::class)]
#[CoversClass(Matomo_Service_Matomo::class)]
final class ProviderChoiceTest extends IntegrationTestCase
{
    private const KEY     = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';   // 32 zero bytes, base64
    private const INSTALL = 'org-install';
    private const TOKEN_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const TOKEN_I = 'iiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii';

    /** @var array<int,array{0:string,1:array}> every fake Matomo call: [url, postFields] */
    private array $calls = [];
    private $priorConfig = null;
    private bool $hadConfig = false;

    protected function setUp(): void
    {
        parent::setUp();
        $reg = Zend_Registry::getInstance();
        $this->hadConfig   = $reg->offsetExists('Zend_Config');
        $this->priorConfig = $this->hadConfig ? Zend_Registry::get('Zend_Config') : null;
        $this->config([]);
        Zend_Registry::set('tiger.auth.stateless', true);   // a CLI call is CSRF-immune unless a test says otherwise
        $this->fakeMatomo(200, $this->bulkFixture());
    }

    protected function tearDown(): void
    {
        Tiger_Analytics_Adapter_Matomo::setTransport(null);
        (new ReflectionProperty(Tiger_Google_Analytics::class, '_transport'))->setValue(null, null);
        (new ReflectionProperty(Tiger_Google_Analytics::class, '_access'))->setValue(null, null);
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        if ($this->hadConfig) { Zend_Registry::set('Zend_Config', $this->priorConfig); }
        elseif ($reg->offsetExists('Zend_Config')) { $reg->offsetUnset('Zend_Config'); }
        parent::tearDown();
    }

    // =================================================================================================
    //  Provider resolution
    // =================================================================================================

    #[Test]
    public function an_existing_ga_install_resolves_to_ga_with_zero_clicks(): void
    {
        $this->connectGaInstallWide();
        $this->assertSame('', Tiger_Analytics::chosen(self::INSTALL), 'nothing was chosen');
        $this->assertSame('ga', Tiger_Analytics::provider(self::INSTALL), 'GA connected → ga');
    }

    #[Test]
    public function a_matomo_only_org_resolves_to_matomo(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $this->assertSame('matomo', Tiger_Analytics::provider('org-a'));
    }

    #[Test]
    public function matomo_without_a_token_is_not_configured_so_nothing_resolves(): void
    {
        $this->matomo('org-a', null);
        $this->assertFalse((new Tiger_Analytics_Adapter_Matomo())->isConfigured('org-a'));
        $this->assertSame('', Tiger_Analytics::provider('org-a'));
    }

    #[Test]
    public function with_nothing_set_up_there_is_no_provider_and_report_says_so(): void
    {
        $this->assertSame('', Tiger_Analytics::provider('org-a'));
        $r = Tiger_Analytics::report('org-a');
        $this->assertSame('no_provider', $r['code']);
        $this->assertSame('analytics.reports.no_provider', $r['error']);
        $this->assertSame([], $this->calls, 'no provider → no outbound call');
    }

    #[Test]
    public function an_explicit_choice_wins_over_auto_resolution(): void
    {
        $this->connectGaInstallWide();
        $this->matomo(self::INSTALL, self::TOKEN_I);
        $this->assertSame('ga', Tiger_Analytics::provider(self::INSTALL), 'auto: GA first');
        $this->assertTrue(Tiger_Analytics::saveProvider('matomo', self::INSTALL));
        $this->assertSame('matomo', Tiger_Analytics::provider(self::INSTALL));
    }

    #[Test]
    public function an_unregistered_choice_is_refused_and_a_stale_one_is_ignored(): void
    {
        $this->assertFalse(Tiger_Analytics::saveProvider('plausible', 'org-a'), 'unknown provider refused');
        (new Tiger_Model_Config())->set('org', 'org-a', Tiger_Analytics::CFG_PROVIDER, 'removed-module');
        $this->matomo('org-a', self::TOKEN_A);
        $this->assertSame('', Tiger_Analytics::chosen('org-a'), 'a stale choice reads as none');
        $this->assertSame('matomo', Tiger_Analytics::provider('org-a'), '… so auto-resolution still answers');
    }

    #[Test]
    public function switching_provider_never_touches_the_other_providers_settings(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        Tiger_Analytics::saveProvider('ga', 'org-a');
        Tiger_Analytics::saveProvider('matomo', 'org-a');
        Tiger_Analytics::saveProvider('', 'org-a');
        $this->assertSame('https://stats.example.test/', (string) Tiger_Model_Config::orgOwnValue('org-a', 'tiger.matomo.url'));
        $this->assertTrue((new Tiger_Analytics_Adapter_Matomo())->hasToken('org-a'), 'the token survives every switch');
    }

    // =================================================================================================
    //  Org isolation
    // =================================================================================================

    #[Test]
    public function one_orgs_choice_and_token_never_answer_for_another(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        Tiger_Analytics::saveProvider('matomo', 'org-a');

        $this->assertSame('', Tiger_Analytics::chosen('org-b'), "A's choice is A's");
        $this->assertSame('', Tiger_Analytics::provider('org-b'));
        $this->assertFalse((new Tiger_Analytics_Adapter_Matomo())->hasToken('org-b'));
        $this->assertSame('no_provider', Tiger_Analytics::report('org-b')['code']);
        $this->assertSame([], $this->calls, "B's report never sends A's token anywhere");
    }

    #[Test]
    public function an_install_wide_matomo_token_is_never_used_for_a_non_install_org(): void
    {
        $g = Tiger_Model_Config::SCOPE_GLOBAL;
        $cfg = new Tiger_Model_Config();
        $cfg->set($g, '', 'tiger.matomo.url', 'https://stats.example.test/');
        $cfg->set($g, '', 'tiger.matomo.site_id', '3');
        $cfg->set($g, '', Tiger_Analytics_Adapter_Matomo::CFG_TOKEN, Tiger_Crypto::encrypt(self::TOKEN_I));

        $m = new Tiger_Analytics_Adapter_Matomo();
        $this->assertTrue($m->hasToken(self::INSTALL), 'the install org owns the install tier');
        $this->assertFalse($m->hasToken('org-b'), 'any other org: org-level only, no fallback');
        $this->assertSame('https://stats.example.test/', $m->url('org-b'), 'plain settings still inherit');
        $this->assertSame('matomo', Tiger_Analytics::provider(self::INSTALL));
        $this->assertSame('', Tiger_Analytics::provider('org-b'));
    }

    #[Test]
    public function an_install_wide_ga_grant_is_never_used_for_a_non_install_org(): void
    {
        $this->connectGaInstallWide();
        $ga = new Tiger_Analytics_Adapter_Ga();
        $this->assertTrue($ga->isConfigured(self::INSTALL));
        $this->assertFalse($ga->isConfigured('org-b'), "B doesn't borrow the install's Google grant");
        $this->assertSame('', Tiger_Analytics::provider('org-b'));
    }

    // =================================================================================================
    //  Matomo adapter — contract, transport, normalized shape
    // =================================================================================================

    #[Test]
    public function matomo_report_is_one_bulk_post_with_the_token_in_the_body_only(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $r = Tiger_Analytics::report('org-a', ['days' => 7]);

        $this->assertArrayNotHasKey('error', $r);
        $this->assertCount(1, $this->calls, 'one round trip');
        [$url, $fields] = $this->calls[0];
        $this->assertSame('https://stats.example.test/index.php', $url);
        $this->assertStringNotContainsString(self::TOKEN_A, $url, 'never in the URL');
        $this->assertSame(self::TOKEN_A, $fields['token_auth'], 'in the POST body');
        $this->assertSame('API.getBulkRequest', $fields['method']);
        $this->assertSame('JSON', $fields['format']);
        foreach ($fields as $k => $v) {
            if (str_starts_with((string) $k, 'urls[')) {
                $this->assertStringNotContainsString(self::TOKEN_A, (string) $v, 'sub-requests carry no token');
                $this->assertStringContainsString('idSite=3', (string) $v);
            }
        }
        $this->assertSame(5, Tiger_Analytics_Adapter_Matomo::TIMEOUT);
    }

    #[Test]
    public function matomo_report_is_normalized(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $r = Tiger_Analytics::report('org-a', ['days' => 2]);
        $this->assertNormalizedShape($r);
        $this->assertSame('matomo', $r['provider']);
        $this->assertSame(['visits' => 120, 'visitors' => 90, 'pageviews' => 300, 'bounce_rate' => 41.5, 'avg_time' => 75], $r['totals']);
        $this->assertSame([
            ['date' => '2026-11-15', 'visitors' => 40, 'pageviews' => 100],
            ['date' => '2026-11-16', 'visitors' => 50, 'pageviews' => 200],
        ], $r['series']);
        $this->assertSame([['label' => '/blog', 'hits' => 80], ['label' => 'index', 'hits' => 60]], $r['top_pages']);
        $this->assertSame([['label' => 'Search Engines', 'visits' => 70], ['label' => 'Direct Entry', 'visits' => 30]], $r['top_referrers']);
        $this->assertSame('day', $r['granularity']);
    }

    #[Test]
    public function a_one_day_window_asks_for_the_hourly_report_and_a_longer_one_does_not(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $hourly = function (): bool {
            foreach ($this->calls[0][1] as $k => $v) {
                if (str_starts_with((string) $k, 'urls[') && str_contains((string) $v, 'VisitTime.getVisitInformationPerServerTime')) {
                    return true;
                }
            }
            return false;
        };
        $day = date('Y-m-d', strtotime('-1 day'));
        Tiger_Analytics::report('org-a', ['start' => $day, 'end' => $day]);
        $this->assertTrue($hourly(), 'one day → hourly');
        $this->calls = [];
        Tiger_Analytics::report('org-a', ['days' => 7]);
        $this->assertFalse($hourly(), 'a week stays daily');
    }

    #[Test]
    public function the_hourly_report_replaces_the_single_daily_point(): void
    {
        $base = json_decode($this->bulkFixture(), true);
        $base[2] = ['2026-11-16' => ['nb_visits' => 60, 'nb_uniq_visitors' => 50]];
        $base[3] = ['2026-11-16' => ['nb_pageviews' => 200]];
        $base[6] = [];
        $base[7] = [];
        $base[8] = [
            ['label' => '9', 'nb_visits' => 4, 'nb_uniq_visitors' => 3, 'nb_actions' => 11],
            ['label' => '0', 'nb_visits' => 1, 'nb_actions' => 2],          // no uniques → visits
            ['label' => 'junk', 'nb_visits' => 99],                         // not an hour → ignored
        ];
        $r = (new Tiger_Analytics_Adapter_Matomo())->fromBulk(1, $base, ['start' => '2026-11-16', 'end' => '2026-11-16']);
        $this->assertSame('hour', $r['granularity']);
        $this->assertSame([
            ['date' => '2026-11-16 00:00', 'visitors' => 1, 'pageviews' => 2],
            ['date' => '2026-11-16 09:00', 'visitors' => 3, 'pageviews' => 11],
        ], $r['series'], 'sorted by hour; the second value is Matomo actions');

        $base[8] = ['result' => 'error', 'message' => 'no VisitTime'];
        $r = (new Tiger_Analytics_Adapter_Matomo())->fromBulk(1, $base, ['start' => '2026-11-16', 'end' => '2026-11-16']);
        $this->assertSame('day', $r['granularity'], 'a failed hourly report keeps the daily point');
        $this->assertSame([['date' => '2026-11-16', 'visitors' => 50, 'pageviews' => 200]], $r['series']);
    }

    #[Test]
    public function matomo_failures_are_clean_errors_and_never_leak_the_token(): void
    {
        $this->matomo('org-a', self::TOKEN_A);

        $this->fakeMatomo(0, false);
        $this->assertSame('unreachable', Tiger_Analytics::report('org-a')['code']);

        $this->fakeMatomo(200, json_encode(['result' => 'error', 'message' => 'You must be logged in. token_auth=' . self::TOKEN_A . ' is invalid']));
        $r = Tiger_Analytics::report('org-a');
        $this->assertSame('auth', $r['code']);
        $this->assertSame('analytics.reports.matomo_auth', $r['error']);
        $this->assertStringNotContainsString(self::TOKEN_A, json_encode($r), 'a token echoed by Matomo is redacted');

        $this->fakeMatomo(500, 'Internal Server Error');
        $this->assertSame('http', Tiger_Analytics::report('org-a')['code']);

        $this->fakeMatomo(200, 'not json');
        $this->assertSame('api', Tiger_Analytics::report('org-a')['code']);
    }

    #[Test]
    public function matomo_test_connection_reports_ok_and_auth_failures(): void
    {
        $m = new Tiger_Analytics_Adapter_Matomo();
        $this->assertSame('not_configured', $m->test('org-a')['code']);
        $this->matomo('org-a', null);
        $this->assertSame('no_token', $m->test('org-a')['code']);

        $this->matomo('org-a', self::TOKEN_A);
        $this->fakeMatomo(200, json_encode([['idsite' => '3', 'name' => 'Example']]));
        $t = $m->test('org-a');
        $this->assertTrue($t['ok']);
        $this->assertSame('Example', $t['site']);
        $this->assertStringContainsString('SitesManager.getSiteFromId', (string) $this->calls[0][1]['urls[0]']);

        $this->fakeMatomo(401, json_encode(['result' => 'error', 'message' => 'token_auth is not valid']));
        $t = $m->test('org-a');
        $this->assertFalse($t['ok']);
        $this->assertSame('auth', $t['code']);
    }

    // =================================================================================================
    //  GA adapter — wraps Tiger_Google_Analytics, normalized
    // =================================================================================================

    #[Test]
    public function the_ga_summary_maps_onto_the_normalized_shape(): void
    {
        $r = (new Tiger_Analytics_Adapter_Ga())->fromSummary(28, [
            'totals'      => [10.0, 15.0, 42.0],
            'series'      => [['date' => '2026-11-16', 'users' => 3, 'views' => 9]],
            'top_pages'   => [['label' => '/', 'value' => 30]],
            'top_sources' => [['label' => 'Organic Search', 'value' => 12]],
        ]);
        $this->assertNormalizedShape($r);
        $this->assertSame(['visits' => 15, 'visitors' => 10, 'pageviews' => 42, 'bounce_rate' => null, 'avg_time' => null], $r['totals']);
        $this->assertSame([['date' => '2026-11-16', 'visitors' => 3, 'pageviews' => 9]], $r['series']);
        $this->assertSame([['label' => '/', 'hits' => 30]], $r['top_pages']);
        $this->assertSame([['label' => 'Organic Search', 'visits' => 12]], $r['top_referrers']);
    }

    #[Test]
    public function the_ga_adapter_reports_from_the_existing_ga_cache_and_reconnect_signal(): void
    {
        $this->connectGaInstallWide();
        $file = $this->seedGaCache(28, ['totals' => [5, 6, 7], 'series' => [], 'top_pages' => [], 'top_sources' => []]);
        try {
            $r = Tiger_Analytics::report(self::INSTALL);
            $this->assertSame('ga', $r['provider']);
            $this->assertSame(7, $r['totals']['pageviews']);

            (new ReflectionProperty(Tiger_Google_Analytics::class, '_transport'))
                ->setValue(null, static fn () => [401, json_encode(['error' => 'reconnect_required'])]);
            $r = Tiger_Analytics::report(self::INSTALL, ['fresh' => true]);
            $this->assertSame('reconnect_required', $r['code']);
        } finally {
            @unlink($file);
        }
    }

    // =================================================================================================
    //  /api surface
    // =================================================================================================

    #[Test]
    public function reports_report_returns_the_normalized_report_for_the_callers_org(): void
    {
        $this->matomo('org-test', self::TOKEN_A);
        $this->loginAs('admin');
        $res = (new Analytics_Service_Reports(['action' => 'report', 'days' => 2]))->getResponse();
        $this->assertSame(1, (int) $res->result);
        $this->assertNormalizedShape($res->data['report']);
        $this->assertStringNotContainsString(self::TOKEN_A, json_encode($res), 'the token never leaves the server');
    }

    #[Test]
    public function reports_report_with_no_provider_is_an_actionable_error(): void
    {
        $this->loginAs('admin');
        $res = (new Analytics_Service_Reports(['action' => 'report']))->getResponse();
        $this->assertSame(0, (int) $res->result);
        $this->assertSame('no_provider', $res->data['code']);
        $this->assertStringContainsString('analytics.reports.no_provider', json_encode($res->messages));
    }

    #[Test]
    public function reports_test_with_a_provider_tests_that_provider(): void
    {
        $this->matomo('org-test', self::TOKEN_A);
        $this->fakeMatomo(200, json_encode([['idsite' => '3', 'name' => 'Example']]));
        $this->loginAs('admin');
        $res = (new Analytics_Service_Reports(['action' => 'test', 'provider' => 'matomo']))->getResponse();
        $this->assertSame(1, (int) $res->result);
        $this->assertTrue($res->data['test']['ok']);
        $this->assertSame('matomo', $res->data['test']['provider']);
        $this->assertStringNotContainsString(self::TOKEN_A, json_encode($res));
    }

    #[Test]
    public function a_non_admin_cannot_report_test_or_choose(): void
    {
        $this->loginAs('user');
        foreach (['report', 'test'] as $a) {
            $res = (new Analytics_Service_Reports(['action' => $a]))->getResponse();
            $this->assertStringContainsString('not_allowed', json_encode($res->messages), $a);
        }
        $svc = new Analytics_Service_Analytics();
        $svc->saveProvider(['provider' => 'matomo']);
        $this->assertStringContainsString('not_allowed', json_encode($svc->getResponse()->messages));
        $this->assertSame('', Tiger_Analytics::chosen('org-test'));
    }

    #[Test]
    public function save_provider_writes_only_the_callers_org(): void
    {
        $this->loginAs('admin');
        $svc = new Analytics_Service_Analytics();
        $svc->saveProvider(['provider' => 'matomo']);
        $res = $svc->getResponse();
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame('matomo', (string) Tiger_Model_Config::orgOwnValue('org-test', Tiger_Analytics::CFG_PROVIDER));
        $this->assertSame('', Tiger_Analytics::chosen('org-b'), 'nothing written for another org or install-wide');

        $svc = new Analytics_Service_Analytics();
        $svc->saveProvider(['provider' => 'nope']);
        $this->assertSame(0, (int) $svc->getResponse()->result, 'an unregistered provider is a form error');
    }

    #[Test]
    public function save_provider_refuses_a_tokenless_request_and_accepts_the_screens_shared_token(): void
    {
        $prior = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
        Zend_Registry::set('tiger.auth.stateless', false);   // cookie mode: CSRF applies
        try {
            $this->loginAs('admin');
            $svc = new Analytics_Service_Analytics();
            $svc->saveProvider(['provider' => 'matomo']);
            $this->assertSame(0, (int) $svc->getResponse()->result);
            $this->assertStringContainsString('core.api.error.csrf', json_encode($svc->getResponse()->messages));
            $this->assertSame('', Tiger_Analytics::chosen('org-test'), 'not saved');

            // The screen renders ONE token — from the GA settings form. The provider form shares its salt.
            $el = (new Analytics_Form_Settings())->getElement('_csrf');
            $el->initCsrfToken();
            $svc = new Analytics_Service_Analytics();
            $svc->saveProvider(['provider' => 'matomo', '_csrf' => (string) $el->getHash()]);
            $this->assertSame(1, (int) $svc->getResponse()->result, json_encode($svc->getResponse()->messages));
            $this->assertSame('matomo', Tiger_Analytics::chosen('org-test'));
            $this->assertInstanceOf(Analytics_Form_Provider::class, new Analytics_Form_Provider());
        } finally {
            Zend_Registry::set('tiger.auth.stateless', true);
            Zend_Session::$_unitTestEnabled = $prior;
            $_SESSION = [];
        }
    }

    #[Test]
    public function matomo_save_stores_the_token_encrypted_at_the_org_and_never_returns_it(): void
    {
        $this->loginAs('admin');
        $svc = new Matomo_Service_Matomo();
        $svc->save(['matomo_url' => 'https://stats.example.test', 'matomo_site_id' => '3', 'matomo_token_auth' => self::TOKEN_A]);
        $res = $svc->getResponse();
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertTrue($res->data['has_token']);
        $this->assertStringNotContainsString(self::TOKEN_A, json_encode($res), 'write-only: never echoed');

        $stored = (string) Tiger_Model_Config::orgOwnValue('org-test', Tiger_Analytics_Adapter_Matomo::CFG_TOKEN);
        $this->assertNotSame('', $stored);
        $this->assertNotSame(self::TOKEN_A, $stored, 'encrypted at rest');
        $this->assertSame(self::TOKEN_A, Tiger_Crypto::decrypt($stored));
        $this->assertSame('', (string) Tiger_Model_Config::orgValue('', Tiger_Analytics_Adapter_Matomo::CFG_TOKEN, ''), 'nothing install-wide');

        // A blank token on the next save keeps the stored one.
        $svc = new Matomo_Service_Matomo();
        $svc->save(['matomo_url' => 'https://stats.example.test', 'matomo_site_id' => '4', 'matomo_token_auth' => '']);
        $this->assertSame($stored, (string) Tiger_Model_Config::orgOwnValue('org-test', Tiger_Analytics_Adapter_Matomo::CFG_TOKEN));
        $this->assertTrue($svc->getResponse()->data['has_token']);
    }

    #[Test]
    public function matomo_save_refuses_a_malformed_token(): void
    {
        $this->loginAs('admin');
        $svc = new Matomo_Service_Matomo();
        $svc->save(['matomo_url' => 'https://stats.example.test', 'matomo_site_id' => '3', 'matomo_token_auth' => 'bad token"<x>']);
        $this->assertSame(0, (int) $svc->getResponse()->result);
        $this->assertArrayHasKey('matomo_token_auth', (array) $svc->getResponse()->form);
    }

    // =================================================================================================
    //  helpers
    // =================================================================================================

    /** Register the eager config: crypto key + install org (+ any extra `tiger` nodes). */
    private function config(array $tiger): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => array_replace_recursive([
            'crypto' => ['key' => self::KEY],
            'site'   => ['org_id' => self::INSTALL],
        ], $tiger)], true));
        Tiger_Model_Config::resetCache();
    }

    /** GA connected install-wide (ini tier): a property + an encrypted refresh token, broker mode. */
    private function connectGaInstallWide(): void
    {
        $this->config(['analytics' => [
            'property_id' => '123456789',
            'oauth'       => ['mode' => 'broker', 'refresh_token_enc' => Tiger_Crypto::encrypt('fake-refresh')],
        ]]);
    }

    /** Org-scoped Matomo rows (token optional). */
    // =================================================================================================
    //  Dashboard: date windows, filters, drill-downs (Matomo)
    // =================================================================================================

    /** The decoded query of bulk sub-request $i from the last recorded call. */
    private function sub(int $i): array
    {
        parse_str((string) ($this->calls[count($this->calls) - 1][1]['urls[' . $i . ']'] ?? ''), $q);
        return $q;
    }

    #[Test]
    public function matomo_filters_become_one_anded_segment_and_bad_values_are_dropped(): void
    {
        $m = new Tiger_Analytics_Adapter_Matomo();
        $this->assertSame('', $m->segment([]));
        $this->assertSame(
            'referrerType==campaign;referrerName==facebook-groups;referrerType==social;deviceType==smartphone;countryCode==us',
            $m->segment(['campaign' => 'Facebook-Groups', 'channel' => 'social', 'device' => 'Smartphone', 'country' => 'US'])
        );
        $this->assertSame('referrerType==campaign;referrerName==spring%20sale%3Bx', $m->segment(['campaign' => 'Spring Sale;x']), 'a value is encoded, never able to add a clause');
        $this->assertSame('', $m->segment(['channel' => 'evil', 'country' => 'usa', 'device' => 'x;countryCode==de']), 'unknown shapes are dropped');
    }

    #[Test]
    public function matomo_report_honours_an_explicit_window_and_the_filters(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $r = Tiger_Analytics::report('org-a', ['start' => '2026-11-01', 'end' => '2026-11-10', 'filters' => ['channel' => 'social']]);

        $this->assertArrayNotHasKey('error', $r);
        $this->assertSame(['days' => 10, 'start' => '2026-11-01', 'end' => '2026-11-10'], $r['range']);
        foreach ([0, 2, 6, 7] as $i) {
            $q = $this->sub($i);
            $this->assertSame('2026-11-01,2026-11-10', $q['date']);
            $this->assertSame('referrerType==social', $q['segment'], "sub-request $i is filtered");
        }
        $this->assertSame('range', $this->sub(0)['period']);
        $this->assertSame('day', $this->sub(2)['period']);
    }

    #[Test]
    public function an_explicit_window_never_runs_past_today_and_a_reversed_one_is_swapped(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $today = date('Y-m-d');
        $r = Tiger_Analytics::report('org-a', ['start' => '2026-01-10', 'end' => '2099-12-31']);
        $this->assertSame($today, $r['range']['end']);
        $r = Tiger_Analytics::report('org-a', ['start' => '2026-01-10', 'end' => '2026-01-01']);
        $this->assertSame(['days' => 10, 'start' => '2026-01-01', 'end' => '2026-01-10'], $r['range']);
    }

    #[Test]
    public function matomo_report_carries_channels_and_campaigns_for_drilling(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $fixture = json_decode($this->bulkFixture(), true);
        $fixture[] = [['label' => 'Social Networks', 'segment' => 'referrerType==social', 'nb_visits' => 5],
                      ['label' => 'Direct Entry', 'segment' => 'referrerType==direct', 'nb_visits' => 4]];
        $fixture[] = [['label' => 'facebook-groups', 'nb_visits' => 3]];
        $this->fakeMatomo(200, json_encode($fixture));

        $r = Tiger_Analytics::report('org-a', ['days' => 2]);
        $this->assertSame([['label' => 'Social Networks', 'key' => 'social', 'visits' => 5],
                           ['label' => 'Direct Entry', 'key' => 'direct', 'visits' => 4]], $r['channels']);
        $this->assertSame([['label' => 'facebook-groups', 'visits' => 3]], $r['campaigns']);
    }

    #[Test]
    public function a_campaign_drills_into_its_keywords(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $this->fakeMatomo(200, json_encode([[
            ['label' => 'linkedin', 'nb_visits' => 1, 'subtable' => [['label' => 'post-1', 'nb_visits' => 1]]],
            ['label' => 'facebook-groups', 'nb_visits' => 3, 'subtable' => [
                ['label' => 'vibe-coding-tools', 'nb_visits' => 2], ['label' => 'vibe-coders-app-builders', 'nb_visits' => 1]]],
        ]]));

        $b = Tiger_Analytics::breakdown('org-a', 'campaign', 'Facebook-Groups', ['days' => 28]);
        $this->assertSame([['label' => 'vibe-coding-tools', 'visits' => 2], ['label' => 'vibe-coders-app-builders', 'visits' => 1]], $b['rows']);
        $this->assertSame('Referrers.getCampaigns', $this->sub(0)['method']);
        $this->assertSame('1', $this->sub(0)['expanded']);
    }

    #[Test]
    public function a_channel_drills_into_its_sources_and_direct_has_none(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $this->fakeMatomo(200, json_encode([[['label' => 'Facebook', 'nb_visits' => 7], ['label' => 'LinkedIn', 'nb_visits' => 2]]]));

        $b = Tiger_Analytics::breakdown('org-a', 'channel', 'social', ['days' => 28]);
        $this->assertSame('Referrers.getSocials', $this->sub(0)['method']);
        $this->assertSame([['label' => 'Facebook', 'visits' => 7], ['label' => 'LinkedIn', 'visits' => 2]], $b['rows']);

        $this->calls = [];
        $this->assertSame([], Tiger_Analytics::breakdown('org-a', 'channel', 'direct', ['days' => 28])['rows']);
        $this->assertCount(0, $this->calls, 'direct needs no call');
        $this->assertSame('unsupported', Tiger_Analytics::breakdown('org-a', 'channel', 'nope')['code']);
    }

    #[Test]
    public function a_page_drills_into_its_daily_views_and_its_channels(): void
    {
        $this->matomo('org-a', self::TOKEN_A);
        $this->fakeMatomo(200, json_encode([
            ['2026-11-15' => [['nb_hits' => 4]], '2026-11-16' => []],
            [['label' => 'Social Networks', 'segment' => 'referrerType==social', 'nb_visits' => 3]],
        ]));

        $b = Tiger_Analytics::breakdown('org-a', 'page', 'https://webtigers.com/hosting', ['days' => 2, 'filters' => ['country' => 'us']]);
        $this->assertSame([['date' => '2026-11-15', 'pageviews' => 4], ['date' => '2026-11-16', 'pageviews' => 0]], $b['series']);
        $this->assertSame([['label' => 'Social Networks', 'key' => 'social', 'visits' => 3]], $b['rows']);
        $this->assertSame('countryCode==us;pageUrl==' . rawurlencode('https://webtigers.com/hosting'), $this->sub(1)['segment'],
            'the page drill keeps the dashboard filters and adds the page');
    }

    #[Test]
    public function reports_options_says_what_the_provider_can_do(): void
    {
        $this->loginAs('admin');
        $none = (new Analytics_Service_Reports(['action' => 'options']))->getResponse();
        $this->assertSame(1, (int) $none->result);
        $this->assertFalse($none->data['capabilities']['range'], 'no provider → no extras');
        $this->assertSame([], $none->data['capabilities']['filters']);

        $this->matomo('org-test', self::TOKEN_A);
        $this->fakeMatomo(200, json_encode([
            [['label' => 'facebook-groups']],
            [['label' => 'Smartphone', 'segment' => 'deviceType==smartphone', 'nb_visits' => 3],
             ['label' => 'Car browser', 'segment' => 'deviceType==car+browser', 'nb_visits' => 1],
             ['label' => 'Wearable', 'segment' => 'deviceType==wearable', 'nb_visits' => 0]],
            [['label' => 'United States', 'code' => 'us']],
        ]));
        $res = (new Analytics_Service_Reports(['action' => 'options', 'days' => 28]))->getResponse();
        $this->assertTrue($res->data['capabilities']['range']);
        $this->assertSame(['campaign', 'channel', 'device', 'country'], $res->data['capabilities']['filters']);
        $this->assertSame([['value' => 'facebook-groups', 'label' => 'facebook-groups']], $res->data['filters']['campaign']);
        $this->assertSame([['value' => 'smartphone', 'label' => 'Smartphone'], ['value' => 'car browser', 'label' => 'Car browser']],
            $res->data['filters']['device'], 'decoded, and only types with visits');
        $m = new Tiger_Analytics_Adapter_Matomo();
        $this->assertSame('deviceType==car%20browser', $m->segment(['device' => 'car browser']), 'a listed value filters');
        $this->assertSame([['value' => 'us', 'label' => 'United States']], $res->data['filters']['country']);
    }

    #[Test]
    public function reports_breakdown_refuses_an_unknown_kind(): void
    {
        $this->matomo('org-test', self::TOKEN_A);
        $this->loginAs('admin');
        $res = (new Analytics_Service_Reports(['action' => 'breakdown', 'kind' => 'everything', 'item' => 'x']))->getResponse();
        $this->assertSame(0, (int) $res->result);
        $this->assertSame('unsupported', $res->data['code']);
    }

    private function matomo(string $org, ?string $token): void
    {
        $cfg = new Tiger_Model_Config();
        $cfg->set('org', $org, 'tiger.matomo.url', 'https://stats.example.test/');
        $cfg->set('org', $org, 'tiger.matomo.site_id', '3');
        if ($token !== null) {
            $cfg->set('org', $org, Tiger_Analytics_Adapter_Matomo::CFG_TOKEN, Tiger_Crypto::encrypt($token));
        }
    }

    /** Route Matomo HTTP to a recorder returning [$code, $body]. */
    private function fakeMatomo(int $code, $body): void
    {
        $this->calls = [];
        Tiger_Analytics_Adapter_Matomo::setTransport(function ($url, array $fields) use ($code, $body) {
            $this->calls[] = [$url, $fields];
            return [$code, $body];
        });
    }

    /** A getBulkRequest response for the six report sub-requests. */
    private function bulkFixture(): string
    {
        return json_encode([
            ['nb_visits' => 120, 'nb_uniq_visitors' => 90, 'bounce_rate' => '41.5%', 'avg_time_on_site' => 75],
            ['nb_pageviews' => 300],
            ['2026-11-15' => ['nb_visits' => 50, 'nb_uniq_visitors' => 40], '2026-11-16' => ['nb_visits' => 60, 'nb_uniq_visitors' => 50]],
            ['2026-11-15' => ['nb_pageviews' => 100], '2026-11-16' => ['nb_pageviews' => 200]],
            [['label' => '/blog', 'nb_hits' => 80], ['label' => 'index', 'nb_hits' => 60]],
            [['label' => 'Search Engines', 'nb_visits' => 70], ['label' => 'Direct Entry', 'nb_visits' => 30]],
        ]);
    }

    /** The GA report cache file Tiger_Google_Analytics reads (so no Google round-trip). */
    private function seedGaCache(int $days, array $data): string
    {
        $base = (defined('APPLICATION_PATH') ? dirname(APPLICATION_PATH) : sys_get_temp_dir()) . '/var/cache/analytics';
        if (!is_dir($base)) { @mkdir($base, 0775, true); }
        $file = $base . '/ga-' . substr(md5('123456789'), 0, 10) . '-' . $days . 'd.json';
        file_put_contents($file, json_encode($data));
        return $file;
    }

    /** Every normalized key present and typed. */
    private function assertNormalizedShape(array $r): void
    {
        foreach (['provider', 'range', 'totals', 'series', 'top_pages', 'top_referrers', 'fetched_at'] as $k) {
            $this->assertArrayHasKey($k, $r, "normalized report has `$k`");
        }
        $this->assertSame(['visits', 'visitors', 'pageviews', 'bounce_rate', 'avg_time'], array_keys($r['totals']));
        $this->assertSame(['days', 'start', 'end'], array_keys($r['range']));
        foreach ($r['series'] as $p) { $this->assertSame(['date', 'visitors', 'pageviews'], array_keys($p)); }
        foreach ($r['top_pages'] as $p) { $this->assertSame(['label', 'hits'], array_keys($p)); }
        foreach ($r['top_referrers'] as $p) { $this->assertSame(['label', 'visits'], array_keys($p)); }
    }
}
