<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Google;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Crypto;
use Tiger_Google_Analytics;

/**
 * Tiger_Google_Analytics — the transport-adjacent branches AnalyticsTest leaves to the HTTP boundary,
 * reached WITHOUT real network by two tricks: (1) point the BROKER base at a dead local port so its
 * `_http` POST fails fast (connection refused) and the documented fail-soft branches run
 * (`exchangeHandoff` → error, `accessToken` broker → '', and everything downstream), and (2) pre-seed
 * the report file-cache so `summary()` serves a hit with no call at all. Google's fixed-host hops —
 * `runReport` / `_probeReport` / BYO `_tokenRequest` (accounts.google.com / analyticsdata.googleapis.com,
 * un-redirectable) — remain the genuine network boundary and are NOT exercised here.
 */
#[CoversClass(Tiger_Google_Analytics::class)]
final class AnalyticsHttpTest extends UnitTestCase
{
    private const DEAD_BROKER = 'http://127.0.0.1:1';
    /** Cache files written by a test (removed in tearDown). */
    private array $wrote = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetAccess();
    }

    protected function tearDown(): void
    {
        $this->resetAccess();
        $this->clearTransport();
        foreach ($this->wrote as $f) { @unlink($f); }
        parent::tearDown();
    }

    private function resetAccess(): void
    {
        (new ReflectionProperty(Tiger_Google_Analytics::class, '_access'))->setValue(null, null);
        (new ReflectionProperty(Tiger_Google_Analytics::class, '_reconnect'))->setValue(null, false);
    }

    /** Install a fake transport [httpCode, body] for every HTTP hop (the same seam $_access is reset by). */
    private function setTransport(int $code, $body): void
    {
        (new ReflectionProperty(Tiger_Google_Analytics::class, '_transport'))
            ->setValue(null, static fn ($url, array $opts) => [$code, $body]);
    }

    private function clearTransport(): void
    {
        (new ReflectionProperty(Tiger_Google_Analytics::class, '_transport'))->setValue(null, null);
    }

    /** A crypto key + a config array wiring a broker-mode connection (property + encrypted refresh token). */
    private function connectBroker(string $property = '123456789', string $brokerBase = self::DEAD_BROKER): void
    {
        $key = base64_encode(str_repeat("\x11", 32));
        $this->setConfig(['tiger' => ['crypto' => ['key' => $key]]]);     // key must be live to encrypt
        $enc = Tiger_Crypto::encrypt('refresh-token-value');
        $this->setConfig(['tiger' => [
            'crypto'    => ['key' => $key],
            'analytics' => [
                'property_id' => $property,
                'connect'     => ['base_url' => $brokerBase],
                'oauth'       => ['refresh_token_enc' => $enc],   // mode defaults to broker
            ],
        ]]);
    }

    // ---- exchangeHandoff : the unreachable-broker fail-soft ----------------------

    #[Test]
    public function exchange_handoff_fails_soft_when_the_broker_is_unreachable(): void
    {
        $this->setConfig(['tiger' => ['analytics' => ['connect' => ['base_url' => self::DEAD_BROKER]]]]);

        $r = Tiger_Google_Analytics::exchangeHandoff('handoff-code', 'pkce-verifier');
        $this->assertFalse($r['ok']);
        $this->assertNotNull($r['error'], 'an unreachable broker returns a friendly error, never throws');
    }

    // ---- accessToken : broker branch, dead endpoint ------------------------------

    #[Test]
    public function access_token_broker_branch_is_empty_when_the_broker_is_unreachable(): void
    {
        $this->connectBroker();
        // A stored refresh token gets past the guard; the broker /google/token POST then refuses → ''.
        $this->assertSame('', Tiger_Google_Analytics::accessToken());
    }

    // ---- summary : not-connected fast-out is in AnalyticsTest; here the token-empty + cache paths --

    #[Test]
    public function summary_returns_null_when_no_access_token_can_be_minted(): void
    {
        $this->connectBroker();   // connected, but the dead broker yields no access token
        $this->assertNull(Tiger_Google_Analytics::summary(28, true), 'fresh=true bypasses cache → hits the dead broker → null');
    }

    #[Test]
    public function summary_serves_a_primed_cache_entry_without_any_network(): void
    {
        $property = '987654321';
        $this->connectBroker($property);

        $days      = 14;
        $cacheFile = $this->cacheFile($property, $days);
        $payload   = ['range' => ['days' => $days], 'totals' => [5, 6], 'series' => [], 'top_pages' => [], 'top_sources' => [], 'fetched_at' => '2026-01-01T00:00:00+00:00'];
        file_put_contents($cacheFile, json_encode($payload));
        $this->wrote[] = $cacheFile;

        $out = Tiger_Google_Analytics::summary($days);   // fresh=false → a fresh cache file is served as-is
        // The served value is the JSON round-trip of what's on disk (the class decodes it to assoc array).
        $this->assertSame(json_decode(json_encode($payload), true), $out);
    }

    // ---- reconnect_required : the broker says the stored grant is dead (TIGER-115) ----------------

    #[Test]
    public function access_token_flags_reconnect_on_a_broker_401_reconnect_required(): void
    {
        $this->connectBroker();
        $this->setTransport(401, json_encode(['error' => 'reconnect_required']));

        $this->assertSame('', Tiger_Google_Analytics::accessToken(), 'a dead grant yields no access token');
        $this->assertTrue(Tiger_Google_Analytics::reconnectRequired(), 'the 401 reconnect_required is surfaced, not swallowed');
    }

    #[Test]
    public function summary_flags_reconnect_when_the_grant_is_dead(): void
    {
        $this->connectBroker();
        $this->setTransport(401, json_encode(['error' => 'reconnect_required']));

        $this->assertNull(Tiger_Google_Analytics::summary(28, true), 'fresh bypasses the cache → hits the dead grant → null');
        $this->assertTrue(Tiger_Google_Analytics::reconnectRequired());
    }

    #[Test]
    public function a_transient_outage_is_not_a_reconnect(): void
    {
        // Fail-soft: a 500 (or a refused connection) must NOT masquerade as "reconnect" — the grant is fine.
        $this->connectBroker();
        $this->setTransport(500, 'upstream is having a moment');

        $this->assertSame('', Tiger_Google_Analytics::accessToken());
        $this->assertFalse(Tiger_Google_Analytics::reconnectRequired(), 'an outage stays a generic failure');
    }

    #[Test]
    public function signals_reconnect_only_on_a_401(): void
    {
        $m = new \ReflectionMethod(Tiger_Google_Analytics::class, '_signalsReconnect');
        $this->assertTrue($m->invoke(null, 401, ['error' => 'reconnect_required']));
        $this->assertTrue($m->invoke(null, 401, ['error' => 'invalid_grant']));
        $this->assertTrue($m->invoke(null, 401, null), 'a bare 401 on a token mint means the grant is gone');
        $this->assertFalse($m->invoke(null, 500, ['error' => 'reconnect_required']), 'only a 401 counts');
        $this->assertFalse($m->invoke(null, 0, false), 'a transport failure is not a reconnect');
        $this->assertFalse($m->invoke(null, 200, ['access_token' => 'x']));
    }

    // ---- testConnection : the connected-but-no-token diagnosis -------------------

    #[Test]
    public function test_connection_reports_no_token_when_the_broker_wont_issue_one(): void
    {
        $this->connectBroker();
        $r = Tiger_Google_Analytics::testConnection();
        $this->assertFalse($r['ok']);
        $this->assertSame('no_token', $r['code']);
    }

    /** Mirror Tiger_Google_Analytics::_cacheFile()'s path derivation for a given property + window. */
    private function cacheFile(string $property, int $days): string
    {
        $base = (defined('APPLICATION_PATH') ? dirname(APPLICATION_PATH) : sys_get_temp_dir()) . '/var/cache/analytics';
        if (!is_dir($base)) { @mkdir($base, 0775, true); }
        return $base . '/ga-' . substr(md5($property), 0, 10) . ($days ? '-' . $days . 'd' : '') . '.json';
    }
}
