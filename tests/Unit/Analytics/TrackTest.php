<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace {
    /** A tracker fixture: records what it was sent; canTrack per a static switch. */
    if (!class_exists('TrackFix_Adapter')) {
        class TrackFix_Adapter extends \Tiger_Analytics_Adapter_Abstract implements \Tiger_Analytics_Tracker_Interface
        {
            public static $can = true;
            public static $sent = [];
            public static $throw = false;
            public function name(): string { return 'trackfix'; }
            public function isConfigured(string $orgId): bool { return false; }
            public function report(string $orgId, array $period = []): array { return []; }
            public function test(string $orgId): array { return []; }
            public function canTrack(string $orgId): bool { return self::$can; }
            public function track(string $orgId, array $event): bool
            {
                if (self::$throw) { throw new \RuntimeException('boom'); }
                self::$sent[] = [$orgId, $event];
                return true;
            }
        }
    }
    /** A reporting-only fixture (no tracker interface): must be skipped by track(). */
    if (!class_exists('TrackFix_ReportOnly')) {
        class TrackFix_ReportOnly extends \Tiger_Analytics_Adapter_Abstract
        {
            public function name(): string { return 'reportonly'; }
            public function isConfigured(string $orgId): bool { return true; }
            public function report(string $orgId, array $period = []): array { return []; }
            public function test(string $orgId): array { return []; }
        }
    }
    /** GA with its config + network faked: captures the request instead of sending it. */
    if (!class_exists('TrackFix_Ga')) {
        class TrackFix_Ga extends \Tiger_Analytics_Adapter_Ga
        {
            public $req = null; public $status = 204; public $mid = 'G-ABC123'; public $secret = 's3cret';
            protected function _measurementId(string $orgId): string { return $this->mid; }
            protected function _secret(string $orgId, string $encKey): string { return $this->secret; }
            protected function _httpPost(string $url, string $body, array $headers = []): int { $this->req = [$url, $body]; return $this->status; }
        }
    }
    if (!class_exists('TrackFix_Matomo')) {
        class TrackFix_Matomo extends \Tiger_Analytics_Adapter_Matomo
        {
            public $req = null; public $token = '';
            public function url(string $orgId): string { return 'https://stats.example/'; }
            public function siteId(string $orgId): int { return 7; }
            protected function _secret(string $orgId, string $encKey): string { return $this->token; }
            protected function _httpPost(string $url, string $body, array $headers = []): int { $this->req = [$url, $body]; return 204; }
        }
    }
}

namespace Tiger\Tests\Unit\Analytics {

    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\Attributes\Test;
    use ReflectionProperty;
    use Tiger\Tests\Support\UnitTestCase;
    use Tiger_Analytics;

    /**
     * Server-side events (TIGER-525): Tiger_Analytics::track() normalizes an event and sends it to every
     * registered adapter that implements the tracker interface and can track the org — fail-soft — and the
     * GA4 (Measurement Protocol) and Matomo (tracking API) adapters build the right request.
     */
    #[CoversClass(Tiger_Analytics::class)]
    final class TrackTest extends UnitTestCase
    {
        private ReflectionProperty $reg;
        private array $was;

        protected function setUp(): void
        {
            parent::setUp();
            $this->reg = new ReflectionProperty(Tiger_Analytics::class, '_adapters');
            $this->was = $this->reg->getValue();
            $this->reg->setValue(null, ['trackfix' => 'TrackFix_Adapter', 'reportonly' => 'TrackFix_ReportOnly']);
            \TrackFix_Adapter::$can = true; \TrackFix_Adapter::$sent = []; \TrackFix_Adapter::$throw = false;
        }

        protected function tearDown(): void
        {
            $this->reg->setValue(null, $this->was);
            parent::tearDown();
        }

        #[Test]
        public function an_event_is_normalized_and_carries_no_raw_ip_in_its_client_id(): void
        {
            $e = Tiger_Analytics::event('Short Link-Click!', ['Slug' => 'spring', 'list' => ['x'], 'n' => 3],
                ['ip' => '203.0.113.9', 'ua' => 'UA', 'url' => 'https://s/l/spring', 'referrer' => 'https://r/']);
            $this->assertSame('short_link_click', $e['name']);
            $this->assertSame(['slug' => 'spring', 'n' => 3], $e['params'], 'keys normalized, non-scalars dropped');
            $this->assertMatchesRegularExpression('/^\d+\.\d+$/', $e['client_id']);
            $this->assertStringNotContainsString('203.0.113.9', $e['client_id']);
            $this->assertSame($e['client_id'], Tiger_Analytics::event('x', [], ['ip' => '203.0.113.9', 'ua' => 'UA'])['client_id'], 'stable per visitor');
            $this->assertSame(['https://s/l/spring', 'https://r/', '203.0.113.9'], [$e['url'], $e['referrer'], $e['ip']]);
        }

        #[Test]
        public function track_sends_only_to_trackers_that_can_track_and_never_throws(): void
        {
            $this->assertSame(['trackfix' => true], Tiger_Analytics::track('org-1', 'short_link_click', ['slug' => 's']));
            $this->assertSame('org-1', \TrackFix_Adapter::$sent[0][0]);
            $this->assertSame('short_link_click', \TrackFix_Adapter::$sent[0][1]['name']);

            \TrackFix_Adapter::$can = false;
            $this->assertSame([], Tiger_Analytics::track('org-1', 'x'), 'no provider can track = a no-op');

            \TrackFix_Adapter::$can = true; \TrackFix_Adapter::$throw = true;
            $this->assertSame(['trackfix' => false], Tiger_Analytics::track('org-1', 'x'), 'a provider fault is false, not an exception');
            $this->assertSame([], Tiger_Analytics::track('org-1', '!!!'), 'an empty event name sends nothing');
        }

        #[Test]
        public function ga_sends_a_measurement_protocol_event(): void
        {
            $ga = new \TrackFix_Ga();
            $ev = Tiger_Analytics::event('short_link_click', ['slug' => 'spring'], ['url' => 'https://s/l/spring', 'referrer' => 'https://r/', 'client_id' => '1.2']);
            $this->assertTrue($ga->canTrack('o'));
            $this->assertTrue($ga->track('o', $ev));
            [$url, $body] = $ga->req;
            $this->assertStringStartsWith('https://www.google-analytics.com/mp/collect?measurement_id=G-ABC123&api_secret=s3cret', $url);
            $b = json_decode($body, true);
            $this->assertSame('1.2', $b['client_id']);
            $this->assertSame('short_link_click', $b['events'][0]['name']);
            $this->assertSame(['slug' => 'spring', 'page_location' => 'https://s/l/spring', 'page_referrer' => 'https://r/'], $b['events'][0]['params']);

            $ga->status = 400;
            $this->assertFalse($ga->track('o', $ev), 'a refused send is false');
            $ga->secret = '';
            $this->assertFalse($ga->canTrack('o'), 'no API secret = cannot track');
        }

        #[Test]
        public function matomo_sends_an_event_and_passes_the_ip_only_with_its_token(): void
        {
            $m  = new \TrackFix_Matomo();
            $ev = Tiger_Analytics::event('short_link_click', ['label' => 'spring'], ['ip' => '203.0.113.9', 'ua' => 'UA', 'url' => 'https://s/l/spring', 'client_id' => '1.2']);
            $this->assertTrue($m->canTrack('o'), 'url + site id is enough to track');
            $this->assertTrue($m->track('o', $ev));
            parse_str($m->req[1], $f);
            $this->assertSame('https://stats.example/matomo.php', $m->req[0]);
            $this->assertSame(['7', '1', 'tiger', 'short_link_click', 'spring'], [$f['idsite'], $f['rec'], $f['e_c'], $f['e_a'], $f['e_n']]);
            $this->assertArrayNotHasKey('cip', $f, 'no token: the IP is not sent');
            $this->assertArrayNotHasKey('token_auth', $f);

            $m->token = 'tok';
            $m->track('o', $ev);
            parse_str($m->req[1], $f);
            $this->assertSame(['203.0.113.9', 'tok'], [$f['cip'], $f['token_auth']]);
            $this->assertStringNotContainsString('tok', $m->req[0], 'the token rides in the body, never the URL');
        }
    }
}
